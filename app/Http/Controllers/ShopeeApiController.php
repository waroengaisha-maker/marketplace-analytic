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
use Inertia\Inertia;

class ShopeeApiController extends Controller
{
    public function index(ShopeeApiResearchService $service, Request $request)
    {
        return Inertia::render('Integrations/ShopeeApi', array_merge(
            $service->payload(),
            ['connection' => $this->connectionFor($request->user()->id)?->safeState()],
        ));
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'config' => $this->clientFor($request->user()->id)->connectionStatus(),
            'connection' => $this->connectionFor($request->user()->id)?->safeState(),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        return response()->json($this->clientFor($request->user()->id)->testConnection());
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
        } elseif (blank($connection->partner_id)) {
            $connection->partner_id = config('shopee-api.partner_id');
        }

        if (filled($validated['partner_key'] ?? null)) {
            $connection->partner_key = $validated['partner_key'];
        } elseif (blank($connection->partner_key)) {
            $connection->partner_key = config('shopee-api.partner_key');
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
        $expectedState = $request->session()->pull('shopee_oauth_state');
        $state = (string) $request->query('state', '');

        if ($expectedState === null || ! hash_equals($expectedState, $state)) {
            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'Shopee authorization state could not be verified. Try again.']);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'Shopee authorization did not return a code.']);
        }

        $user = $request->user();
        $connection = $this->connectionFor($user->id);

        if ($connection === null || blank($connection->partner_id) || blank($connection->partner_key)) {
            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'No Shopee configuration found for this account.']);
        }

        try {
            $payload = $oauth->exchangeCode($connection, $code);
        } catch (ShopeeApiException $exception) {
            return redirect()->route('integrations.shopee-api')
                ->with('shopee_flow', ['status' => 'error', 'message' => 'Shopee authorization failed: '.$exception->getMessage()]);
        }

        $connection->access_token = data_get($payload, 'response.access_token');
        $connection->refresh_token = data_get($payload, 'response.refresh_token');
        $connection->shop_id = data_get($payload, 'response.shop_id') ?? $connection->shop_id;
        $connection->shop_name = data_get($payload, 'response.shop_name') ?? $connection->shop_name;
        $connection->access_token_expires_at = now()->addSeconds(max(1, (int) data_get($payload, 'response.expires_in', 14400)));
        $connection->refresh_token_expires_at = now()->addSeconds(max(1, (int) data_get($payload, 'response.refresh_expires_in', 31536000)));
        $connection->connected_at = $connection->connected_at ?? now();
        $connection->save();

        return redirect()->route('integrations.shopee-api')
            ->with('shopee_flow', ['status' => 'success', 'message' => 'Shopee shop connected.']);
    }

    public function syncOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_size' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('shopee-api.sync.order_page_size_max', 5)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        try {
            $sync = $this->syncFor($request->user()->id);
            $connection = $this->connectionFor($request->user()->id);

            $result = $sync->syncSampleOrders($connection, $validated);

            $this->auditSync($request->user()->id, 'orders', $result);

            return response()->json($result);
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function syncIncome(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('shopee-api.sync.income_page_size_max', 20)],
        ]);

        try {
            $sync = $this->syncFor($request->user()->id);
            $connection = $this->connectionFor($request->user()->id);

            $result = $sync->syncSampleIncome($connection, $validated);

            $this->auditSync($request->user()->id, 'income', $result);

            return response()->json($result);
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function syncEscrow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('shopee-api.sync.escrow_limit_max', 20)],
        ]);

        try {
            $sync = $this->syncFor($request->user()->id);
            $connection = $this->connectionFor($request->user()->id);

            $result = $sync->syncSampleEscrow($connection, $validated);

            $this->auditSync($request->user()->id, 'escrow', $result);

            return response()->json($result);
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
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
                'raw' => ['detail' => $detail, 'escrow' => $escrow],
            ]);
        } catch (ShopeeApiException $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function income(Request $request, ShopeeResponseNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $client = $this->clientFor($request->user()->id);

        $params = [
            'page_size' => $validated['page_size'] ?? 20,
        ];

        if (! empty($validated['status'])) {
            $params['status'] = $validated['status'];
        }

        if (! empty($validated['date_from'])) {
            $params['date_from'] = $validated['date_from'];
        }

        if (! empty($validated['date_to'])) {
            $params['date_to'] = $validated['date_to'];
        }

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
            'error' => $result['error'] ?? null,
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

        if ($connection !== null && $connection->isConfigured()) {
            return ShopeeApiClient::fromConnection($connection);
        }

        return ShopeeApiClient::fromConfig();
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
                'raw' => $envelope,
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

        return response()->json([
            'ok' => false,
            'error' => $exception->getMessage(),
            'rate_limited' => $exception->isRateLimited(),
        ], $status);
    }
}
