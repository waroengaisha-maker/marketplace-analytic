<?php

namespace App\Http\Controllers;

use App\Models\AccountAuditLog;
use App\Models\ShopeeApiConnection;
use App\Services\ShopeeApiClient;
use App\Services\ShopeeApiException;
use App\Services\ShopeeApiResearchService;
use App\Services\ShopeeOAuthService;
use App\Services\ShopeePromotionService;
use App\Services\ShopeeResponseNormalizer;
use App\Services\ShopeeShadowValidationService;
use App\Services\ShopeeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ShopeeApiController extends Controller
{
    // Production error handling is intentionally sanitized at the controller boundary.
    public function index(ShopeeApiResearchService $service, Request $request)
    {
        return Inertia::render('Integrations/ShopeeApi', array_merge(
            $service->payload(),
            ['connection' => $this->connectionFor($request->user()->id)?->safeState()],
        ));
    }

    public function status(Request $request): JsonResponse
    {
        $connection = $this->connectionFor($request->user()->id);

        return response()->json([
            'ok' => true,
            'config' => $connection !== null
                ? ShopeeApiClient::fromConnection($connection)->connectionStatus()
                : [
                    'configured' => false,
                    'missing' => ['connection'],
                    'environment' => 'production',
                    'region' => 'global',
                    'host' => null,
                ],
            'connection' => $connection?->safeState(),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        try {
            return response()->json($this->clientFor($request->user()->id)->testConnection());
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function configure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'partner_id' => ['nullable', 'string', 'max:255'],
            'partner_key' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', 'in:production,sandbox'],
            'region' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        $connection = ShopeeApiConnection::forUser($user->id)->firstOrNew(['user_id' => $user->id]);

        $connection->environment = $validated['environment'];
        $connection->region = $validated['region'] ?? 'global';

        if (filled($validated['partner_id'] ?? null)) {
            $connection->partner_id = $validated['partner_id'];
        }

        if (filled($validated['partner_key'] ?? null)) {
            $connection->partner_key = $validated['partner_key'];
        }

        $connection->save();

        return response()->json(['ok' => true, 'connection' => $connection->safeState()]);
    }

    public function authorize(Request $request, ShopeeOAuthService $oauth): JsonResponse
    {
        $connection = $this->connectionFor($request->user()->id);

        if ($connection === null || blank($connection->partner_id) || blank($connection->partner_key)) {
            return response()->json(['ok' => false, 'error' => 'Configure Shopee partner credentials first.'], 422);
        }

        $state = bin2hex(random_bytes(16));
        $request->session()->put('shopee_oauth_state', $state);

        try {
            return response()->json(['ok' => true, 'url' => $oauth->authorizationUrl($connection, null, $state)]);
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function shopeeCallback(Request $request, ShopeeOAuthService $oauth): RedirectResponse
    {
        $user = $request->user();
        $connection = $user ? $this->connectionFor($user->id) : null;

        // Diagnostic only: never log OAuth codes, state values, tokens, or partner keys.
        Log::debug('[Shopee OAuth] callback reached', [
            'connection_id' => $connection?->id,
            'environment' => $connection?->environment,
            'has_code' => filled($request->query('code')),
            'has_state' => filled($request->query('state')),
            'has_shop_id' => filled($request->query('shop_id')),
            'has_error' => filled($request->query('error')),
            'error' => $request->query('error'),
            'has_user' => $user !== null,
        ]);

        $expectedState = $request->session()->pull('shopee_oauth_state');
        $state = (string) $request->query('state', '');
        $stateMatches = $expectedState !== null && hash_equals($expectedState, $state);

        Log::debug('[Shopee OAuth] state check', [
            'connection_id' => $connection?->id,
            'has_expected_state' => $expectedState !== null,
            'has_received_state' => $state !== '',
            'state_matches' => $stateMatches,
        ]);

        if (!$stateMatches) {
            Log::warning('[Shopee OAuth] state verification failed', [
                'connection_id' => $connection?->id,
                'has_expected_state' => $expectedState !== null,
                'has_received_state' => $state !== '',
            ]);

            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'Shopee authorization state could not be verified. Try again.']);
        }

        $code = (string) $request->query('code', '');
        $shopId = $request->query('shop_id');

        if ($code === '') {
            Log::warning('[Shopee OAuth] callback did not contain authorization code', [
                'connection_id' => $connection?->id,
                'has_error' => filled($request->query('error')),
                'error' => $request->query('error'),
            ]);

            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'Shopee authorization did not return a code.']);
        }

        if ($connection === null || blank($connection->partner_id) || blank($connection->partner_key)) {
            Log::warning('[Shopee OAuth] connection is not configured', [
                'connection_id' => $connection?->id,
                'has_partner_id' => $connection !== null && filled($connection->partner_id),
                'has_partner_key' => $connection !== null && filled($connection->partner_key),
            ]);

            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'No Shopee configuration found for this account.']);
        }

        Log::debug('[Shopee OAuth] exchanging authorization code', [
            'connection_id' => $connection->id,
            'environment' => $connection->environment,
            'has_code' => $code !== '',
        ]);

        try {
            $payload = $oauth->exchangeCode($connection, $code, null, filled($shopId) ? (string) $shopId : null);

            // Sandbox V2 returns OAuth tokens at the top level, while some
            // Shopee responses use a nested "response" envelope.
            $response = is_array(data_get($payload, 'response'))
                ? data_get($payload, 'response')
                : $payload;

            $responseShopId = data_get($response, 'shop_id');
            $responseShopId ??= data_get($response, 'shop_id_list.0');

            Log::debug('[Shopee OAuth] token exchange completed', [
                'connection_id' => $connection->id,
                'payload_keys' => array_keys($payload),
                'response_keys' => array_keys($response),
                'has_access_token' => filled(data_get($response, 'access_token')),
                'has_refresh_token' => filled(data_get($response, 'refresh_token')),
                'has_shop_id' => filled($responseShopId),
            ]);
        } catch (ShopeeApiException $exception) {
            Log::warning('[Shopee OAuth] token exchange failed', [
                'connection_id' => $connection->id,
                'environment' => $connection->environment,
                'status' => $exception->httpStatus(),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', [
                    'status' => 'error',
                    'message' => config('app.debug')
                        ? 'Shopee authorization failed: '.$exception->getMessage()
                        : 'Shopee authorization failed. Please try again later.',
                ]);
        } catch (\Throwable $exception) {
            Log::error('[Shopee OAuth] unexpected token exchange error', [
                'connection_id' => $connection->id,
                'environment' => $connection->environment,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'Shopee authorization failed unexpectedly.']);
        }

        $connection->access_token = data_get($response, 'access_token');
        $connection->refresh_token = data_get($response, 'refresh_token');
        $connection->shop_id = $responseShopId ?? $connection->shop_id;
        $connection->shop_name = data_get($response, 'shop_name') ?? $connection->shop_name;

        $accessTokenLifetime = (int) data_get($response, 'expire_in',
            data_get($response, 'expires_in', 14400)
        );

        $refreshTokenLifetime = (int) data_get($response, 'refresh_token_expire_in',
            data_get($response, 'refresh_expires_in', 31536000)
        );

        $connection->access_token_expires_at = now()->addSeconds(max(1, $accessTokenLifetime));
        $connection->refresh_token_expires_at = now()->addSeconds(max(1, $refreshTokenLifetime));
        $connection->connected_at = $connection->connected_at ?? now();
        $connection->save();

        Log::debug('[Shopee OAuth] connection saved', [
            'connection_id' => $connection->id,
            'has_access_token' => filled($connection->access_token),
            'has_refresh_token' => filled($connection->refresh_token),
            'has_shop_id' => filled($connection->shop_id),
            'has_shop_name' => filled($connection->shop_name),
        ]);

        return redirect()->route('integrations.shopee-api')
            ->with('shopee_flow', ['status' => 'success', 'message' => 'Shopee shop connected.']);
    }

    public function syncOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:sample,production'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return $this->withSyncLock($request, function () use ($request, $validated): JsonResponse {
            try {
                $sync = $this->syncFor($request->user()->id);
                $connection = $this->connectionFor($request->user()->id);

                $result = $sync->syncSampleOrders($connection, $validated);

                $this->auditSync($request->user()->id, 'orders', $result);

                return response()->json($result);
            } catch (ShopeeApiException $exception) {
                return $this->errorResponse($exception);
            }
        });
    }

    public function syncIncome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'mode' => ['nullable', 'string', 'in:sample,production'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->withSyncLock($request, function () use ($request, $validated): JsonResponse {
            try {
                $sync = $this->syncFor($request->user()->id);
                $connection = $this->connectionFor($request->user()->id);

                $result = $sync->syncSampleIncome($connection, $validated);

                $this->auditSync($request->user()->id, 'income', $result);

                return response()->json($result);
            } catch (ShopeeApiException $exception) {
                return $this->errorResponse($exception);
            }
        });
    }

    public function syncEscrow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('shopee-api.sync.escrow_limit_max', 20)],
        ]);

        return $this->withSyncLock($request, function () use ($request, $validated): JsonResponse {
            try {
                $sync = $this->syncFor($request->user()->id);
                $connection = $this->connectionFor($request->user()->id);

                $result = $sync->syncSampleEscrow($connection, $validated);

                $this->auditSync($request->user()->id, 'escrow', $result);

                return response()->json($result);
            } catch (ShopeeApiException $exception) {
                return $this->errorResponse($exception);
            }
        });
    }

    private function withSyncLock(Request $request, Closure $callback): JsonResponse
    {
        $userId = $request->user()->id;
        $lock = Cache::lock(
            'shopee-api:sync:user:'.$userId,
            (int) config('shopee-api.sync.lock_seconds', 1800)
        );

        if (! $lock->get()) {
            return response()->json([
                'ok' => false,
                'error' => 'A Shopee sync is already running for this account.',
            ], 409);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    public function validate(Request $request, ShopeeShadowValidationService $validator): JsonResponse
    {
        $connection = $this->connectionFor($request->user()->id);

        if ($connection === null) {
            return response()->json(['ok' => false, 'error' => 'No Shopee API connection for this account.'], 422);
        }

        return response()->json($validator->report($connection));
    }

    public function promote(Request $request, ShopeePromotionService $promotion): JsonResponse
    {
        $validated = $request->validate([
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $connection = $this->connectionFor($request->user()->id);

        if ($connection === null) {
            return response()->json(['ok' => false, 'error' => 'No Shopee API connection for this account.'], 422);
        }

        if (empty($connection->staging_escrow ?? []) && empty($connection->staging_income ?? []) && empty($connection->staging_orders ?? [])) {
            return response()->json(['ok' => false, 'error' => 'No staged Shopee data to promote. Sync orders, escrow and income first.'], 422);
        }

        $result = $promotion->promote($connection, (bool) ($validated['dry_run'] ?? false));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function orders(Request $request, ShopeeResponseNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_status' => ['nullable', 'string', 'in:UNPAID,READY_TO_SHIP,PROCESSED,SHIPPED,COMPLETED,IN_CANCEL,CANCELLED'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $client = $this->clientFor($request->user()->id);

        $params = [
            'page_size' => $validated['page_size'] ?? 10,
            'time_range_field' => 'create_time',
            'time_from' => strtotime(($validated['date_from'] ?? now()->subDays(7)->toDateString()).' 00:00:00'),
            'time_to' => strtotime(($validated['date_to'] ?? now()->toDateString()).' 23:59:59'),
        ];

        if (! empty($validated['order_status'])) {
            $params['order_status'] = $validated['order_status'];
        }

        return $this->respondWith(
            fn (): array => $client->getOrderList($params),
            function (array $envelope) use ($normalizer): array {
                $list = data_get($envelope, 'response.order_list', []);

                return [
                    'normalized' => $normalizer->normalizeOrderHeaders(is_array($list) ? $list : []),
                    'order_count' => count(is_array($list) ? $list : []),
                    'next_cursor' => data_get($envelope, 'response.next_cursor'),
                    'more' => data_get($envelope, 'response.more'),
                ];
            }
        );
    }

    public function orderDetail(
        string $order_sn,
        Request $request,
        ShopeeResponseNormalizer $normalizer
    ): JsonResponse {
        $orderSn = trim($order_sn);

        if ($orderSn === '' || strlen($orderSn) > 32) {
            return response()->json(['ok' => false, 'error' => 'Invalid order serial number.'], 422);
        }

        try {
            $client = $this->clientFor($request->user()->id);
            $detail = $client->getOrderDetail($orderSn);
            $escrow = $client->getEscrowDetail($orderSn);

            $orderList = data_get($detail, 'response.order_list', []);
            $orderIncome = data_get($escrow, 'response.order_income', []);

            return response()->json([
                'ok' => true,
                'error' => null,
                'rate_limited' => false,
                'order_number' => $orderSn,
                'headers' => $normalizer->normalizeOrderHeaders(is_array($orderList) ? $orderList : []),
                'escrow' => $normalizer->normalizeEscrow(is_array($orderIncome) ? $orderIncome : []),
                'lines' => $normalizer->normalizeOrderLines(is_array($orderIncome) ? $orderIncome : []),
                'raw' => config('app.debug') ? ['detail' => $detail, 'escrow' => $escrow] : null,
            ]);
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function income(Request $request, ShopeeResponseNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'income_status' => ['nullable', 'integer', 'in:0,1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after:date_from'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $client = $this->clientFor($request->user()->id);

        // Shopee requires a date range for get_income_detail. Keep the
        // default window within the documented 14-day maximum.
        $dateTo = $validated['date_to'] ?? now()->toDateString();
        $dateFrom = $validated['date_from'] ?? now()->subDays(13)->toDateString();

        $params = [
            'income_status' => (int) ($validated['income_status'] ?? 1),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page_size' => $validated['page_size'] ?? 20,
        ];

        return $this->respondWith(
            fn (): array => $client->getIncomeDetail($params),
            function (array $envelope) use ($normalizer): array {
                $items = data_get(
                    $envelope,
                    'response.income_detail_list_item',
                    data_get($envelope, 'response.list', [])
                );

                return [
                    'normalized' => $normalizer->normalizeIncomeRows(is_array($items) ? $items : []),
                ];
            }
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $connection = $this->connectionFor($request->user()->id);

        if ($connection === null) {
            return response()->json(['ok' => true, 'cleared' => true, 'staged' => false]);
        }

        $hadStaging = $connection->hasStagedData();
        $connection->clearStaging();

        if ($hadStaging) {
            $this->auditSync($request->user()->id, 'clear', [
                'ok' => true,
                'error' => null,
                'cleared' => true,
            ]);
        }

        return response()->json([
            'ok' => true,
            'cleared' => true,
            'staged' => $hadStaging,
        ]);
    }

    /**
     * Record a sync/clear outcome in the account audit trail. Never stores raw
     * credentials; only counts, cursors and status fields are persisted.
     *
     * @param  array<string, mixed>  $result
     */
    private function auditSync(int $userId, string $operation, array $result): void
    {
        $metadata = [
            'operation' => $operation,
            'ok' => (bool) ($result['ok'] ?? false),
            'error' => config('app.debug')
                ? ($result['error'] ?? null)
                : (($result['ok'] ?? false) ? null : 'Shopee API request failed. Please try again later.'),
            'rate_limited' => (bool) ($result['rate_limited'] ?? false),
        ];

        foreach (['order_count', 'row_count', 'escrow_count', 'staged_total', 'pages', 'total_orders', 'cleared'] as $numeric) {
            if (array_key_exists($numeric, $result)) {
                $metadata[$numeric] = $result[$numeric];
            }
        }

        if (array_key_exists('cursor', $result)) {
            $metadata['cursor'] = $result['cursor'];
        }

        app(AccountAuditLog::class)->insert([
            'user_id' => $userId,
            'actor_id' => $userId,
            'action' => 'shopee_api.sync.'.$operation,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function connectionFor(int $userId): ?ShopeeApiConnection
    {
        return ShopeeApiConnection::forUser($userId)->first();
    }

    private function clientFor(int $userId): ShopeeApiClient
    {
        $connection = $this->connectionFor($userId);

        if ($connection === null) {
            throw ShopeeApiException::notConfigured();
        }

        return ShopeeApiClient::fromConnection($connection);
    }

    private function oauth(): ShopeeOAuthService
    {
        return app(ShopeeOAuthService::class);
    }

    private function syncFor(int $userId): ShopeeSyncService
    {
        $connection = $this->connectionFor($userId);

        if ($connection === null || ! $connection->isConfigured()) {
            throw ShopeeApiException::notConfigured();
        }

        return new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            $this->oauth(),
            app(ShopeeResponseNormalizer::class),
        );
    }

    private function respondWith(callable $call, callable $normalize): JsonResponse
    {
        try {
            $envelope = $call();

            return response()->json(array_merge([
                'ok' => true,
                'error' => null,
                'rate_limited' => false,
                'raw' => config('app.debug') ? $envelope : null,
            ], $normalize($envelope)));
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function errorResponse(ShopeeApiException $exception): JsonResponse
    {
        $status = $exception->httpStatus();

        if ($status === null || $status < 400 || $status > 599) {
            $status = 502;
        }

        if (! config('app.debug')) {
            Log::warning('[Shopee API] request failed', [
                'status' => $status,
                'rate_limited' => $exception->isRateLimited(),
                'exception' => get_class($exception),
            ]);
        }

        return response()->json([
            'ok' => false,
            'error' => config('app.debug')
                ? $exception->getMessage()
                : 'Shopee API request failed. Please try again later.',
            'rate_limited' => $exception->isRateLimited(),
        ], $status);
    }
}
