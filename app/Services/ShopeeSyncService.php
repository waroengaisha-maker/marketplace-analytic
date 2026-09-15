<?php

namespace App\Services;

use App\Models\ShopeeApiConnection;

/**
 * Read-only Shopee Open Platform sync for the Integration Lab.
 *
 * - Orders are pulled via cursor pagination (bounded pages), staged raw, never
 *   written to the production marketplace tables. Sample caps, page sizes and
 *   the escrow fan-out limit come from config('shopee-api.sync.*').
 * - Every page resumes from the last successful cursor on partial failure.
 * - Capping never drops staged data: mergeStaging preserves previously staged
 *   rows, the cursor is persisted for resume, and `capped` flags remaining data.
 * - 429 responses are retried with exponential backoff (injectable sleeper for
 *   deterministic tests) and a bounded retry budget.
 * - The access token is refreshed transparently before syncing when expired.
 */
class ShopeeSyncService
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly ShopeeApiClient $client,
        private readonly ShopeeOAuthService $oauth,
        private readonly ShopeeResponseNormalizer $normalizer,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper;
    }

    /** @var (callable(int): mixed)|null */
    private mixed $sleeper;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function syncSampleOrders(ShopeeApiConnection $connection, array $options = []): array
    {
        $this->ensureFreshAccessToken($connection);

        $pageSizeCap = (int) config('shopee-api.sync.order_page_size_max', 5);
        $pageSize = min((int) ($options['page_size'] ?? $pageSizeCap), $pageSizeCap);
        $from = (string) ($options['date_from'] ?? $connection->connected_at?->toDateString() ?? now()->subDays(7)->toDateString());
        $to = (string) ($options['date_to'] ?? now()->toDateString());
        $cursor = (string) ($options['start_cursor'] ?? $connection->last_sync_order_cursor ?? '');

        $staged = [];
        $pages = 0;
        $nextCursor = $cursor;
        $more = false;
        $failure = null;

        $maxPages = (int) config('shopee-api.sync.max_pages', 10);

        do {
            $params = [
                'page_size' => $pageSize,
                'time_range_field' => 'create_time',
                'time_from' => strtotime($from.' 00:00:00'),
                'time_to' => strtotime($to.' 23:59:59'),
            ];

            if ($nextCursor !== '') {
                $params['cursor'] = $nextCursor;
            }

            try {
                $envelope = $this->withBackoff(fn (): array => $this->client->getOrderList($params));
            } catch (ShopeeApiException $exception) {
                $failure = [
                    'error' => $exception->getMessage(),
                    'rate_limited' => $exception->isRateLimited(),
                ];
                break;
            }

            $page = data_get($envelope, 'response.order_list', []);

            if (is_array($page)) {
                foreach ($page as $row) {
                    if (is_array($row)) {
                        $staged[] = $row;
                    }
                }
            }

            $nextCursor = (string) (data_get($envelope, 'response.next_cursor') ?? '');
            $more = (bool) (data_get($envelope, 'response.more') ?? false);
            $pages++;
        } while ($more && $nextCursor !== '' && $pages < $maxPages);

        $capped = $more && $nextCursor !== '';
        $merged = $this->mergeStaging($connection->staging_orders ?? [], $staged, 'order_sn');
        $connection->staging_orders = $merged;
        $connection->last_sync_order_cursor = $nextCursor;
        $this->markOutcome($connection, $failure);

        if (! empty($merged)) {
            $connection->markStaged();
        }

        return [
            'ok' => $failure === null,
            'order_count' => count($staged),
            'staged_total' => count($merged),
            'pages' => $pages,
            'cursor' => $nextCursor,
            'more' => $more,
            'capped' => $capped,
            'page_size' => $pageSize,
            'error' => $failure['error'] ?? null,
            'rate_limited' => $failure['rate_limited'] ?? false,
            'normalized' => $this->normalizer->normalizeOrderHeaders($staged),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function syncSampleIncome(ShopeeApiConnection $connection, array $options = []): array
    {
        $this->ensureFreshAccessToken($connection);

        $incomeCap = (int) config('shopee-api.sync.income_page_size_max', 20);
        $pageSize = min((int) ($options['page_size'] ?? $incomeCap), $incomeCap);
        $params = [
            'page_size' => $pageSize,
        ];

        foreach (['status', 'date_from', 'date_to'] as $key) {
            if (! empty($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        try {
            $envelope = $this->withBackoff(fn (): array => $this->client->getIncomeDetail($params));
        } catch (ShopeeApiException $exception) {
            $this->markOutcome($connection, ['error' => $exception->getMessage(), 'rate_limited' => $exception->isRateLimited()]);

            return [
                'ok' => false,
                'row_count' => 0,
                'error' => $exception->getMessage(),
                'rate_limited' => $exception->isRateLimited(),
                'normalized' => [],
            ];
        }

        $items = data_get($envelope, 'response.income_detail_list_item', data_get($envelope, 'response.list', []));

        if (! is_array($items)) {
            $items = [];
        }

        $merged = $this->mergeStaging($connection->staging_income ?? [], $items, 'order_sn');
        $connection->staging_income = $merged;
        $this->markOutcome($connection, null);

        if (! empty($merged)) {
            $connection->markStaged();
        }

        return [
            'ok' => true,
            'row_count' => count($items),
            'staged_total' => count($merged),
            'page_size' => $pageSize,
            'error' => null,
            'rate_limited' => false,
            'normalized' => $this->normalizer->normalizeIncomeRows($items),
        ];
    }

    /**
     * Pulls escrow accounting for staged order serials and persists the raw
     * payloads in staging storage. Bounded to avoid fan-out flooding.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function syncSampleEscrow(ShopeeApiConnection $connection, array $options = []): array
    {
        $this->ensureFreshAccessToken($connection);

        $escrowCap = (int) config('shopee-api.sync.escrow_limit_max', 20);
        $serialSList = collect($connection->staging_orders ?? [])
            ->map(fn (mixed $row): string => (string) data_get($row, 'order_sn', ''))
            ->filter(fn (string $sn): bool => $sn !== '')
            ->unique()
            ->values();

        $limit = min((int) ($options['limit'] ?? $escrowCap), $escrowCap);
        $serials = $serialSList->take($limit);

        $staged = [];
        $errors = [];
        $rateLimited = false;

        foreach ($serials as $serial) {
            try {
                $envelope = $this->withBackoff(fn (): array => $this->client->getEscrowDetail($serial));
                $staged[] = [
                    'order_sn' => $serial,
                    'response' => (array) data_get($envelope, 'response', []),
                ];
            } catch (ShopeeApiException $exception) {
                $errors[] = [
                    'order_sn' => $serial,
                    'error' => $exception->getMessage(),
                    'rate_limited' => $exception->isRateLimited(),
                ];
                $rateLimited = $rateLimited || $exception->isRateLimited();
            }
        }

        $merged = $this->mergeStaging($connection->staging_escrow ?? [], $staged, 'order_sn');
        $connection->staging_escrow = $merged;
        $this->markOutcome($connection, $errors === [] ? null : [
            'error' => 'Escrow sync failed for '.count($errors).' order(s).',
            'rate_limited' => $rateLimited,
        ]);

        if (! empty($merged)) {
            $connection->markStaged();
        }

        return [
            'ok' => $errors === [],
            'escrow_count' => count($staged),
            'staged_total' => count($merged),
            'total_orders' => $serials->count(),
            'limit' => $limit,
            'capped' => $serialSList->count() > $limit,
            'error' => $errors === [] ? null : 'Escrow sync failed for '.count($errors).' order(s).',
            'rate_limited' => $rateLimited,
            'errors' => $errors,
            'normalized' => collect($merged)
                ->flatMap(fn (array $stagedRow): array => $this->normalizer->normalizeOrderLines(
                    (array) data_get($stagedRow, 'response.order_income', [])
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * Merge newly fetched staging rows into previously staged rows without
     * overwriting, so a partial/resumed sync never drops data already staged.
     * Rows are keyed by $keyField; later rows win for the same key.
     *
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    private function mergeStaging(array $existing, array $incoming, string $keyField): array
    {
        $merged = [];

        foreach ($existing as $row) {
            if (is_array($row)) {
                $merged[(string) data_get($row, $keyField, '')] = $row;
            }
        }

        foreach ($incoming as $row) {
            if (is_array($row)) {
                $merged[(string) data_get($row, $keyField, '')] = $row;
            }
        }

        return array_values($merged);
    }

    public function accessTokenExpired(ShopeeApiConnection $connection): bool
    {
        return $connection->access_token_expires_at === null || $connection->access_token_expires_at->isPast();
    }

    private function ensureFreshAccessToken(ShopeeApiConnection $connection): void
    {
        if (! $this->accessTokenExpired($connection)) {
            return;
        }

        if (blank($connection->refresh_token)) {
            throw new ShopeeApiException(
                'The Shopee access token has expired and no refresh token is available. Reconnect the shop.',
                status: 401
            );
        }

        $payload = $this->oauth->refreshAccessToken($connection);
        $accessToken = data_get($payload, 'response.access_token');

        if (blank($accessToken)) {
            throw new ShopeeApiException('Shopee token refresh returned no access token.', status: 502);
        }

        $connection->access_token = $accessToken;
        $connection->access_token_expires_at = now()->addSeconds(max(1, (int) data_get($payload, 'response.expires_in', 14400)));

        if (blank($connection->shop_id) && filled(data_get($payload, 'response.shop_id'))) {
            $connection->shop_id = data_get($payload, 'response.shop_id');
        }

        $connection->save();
    }

    /**
     * @param  array<int, mixed>|null  $failure
     */
    private function markOutcome(ShopeeApiConnection $connection, ?array $failure): void
    {
        $connection->last_sync_at = now();

        if ($failure === null) {
            $connection->last_sync_status = 'success';
            $connection->last_sync_error = null;
        } else {
            $connection->last_sync_status = ($failure['rate_limited'] ?? false) ? 'rate_limited' : 'error';
            $connection->last_sync_error = $failure['error'] ?? 'Unknown Shopee sync error.';
        }

        $connection->save();
    }

    private function withBackoff(callable $call): mixed
    {
        $attempt = 1;

        while (true) {
            try {
                return $call();
            } catch (ShopeeApiException $exception) {
                if (! $exception->isRateLimited() || $attempt >= self::MAX_RETRIES) {
                    throw $exception;
                }

                $delay = min(2 ** $attempt, 8);
                $sleeper = $this->sleeper ?? static fn (int $seconds): int => sleep($seconds);
                $sleeper($delay);
                $attempt++;
            }
        }
    }
}
