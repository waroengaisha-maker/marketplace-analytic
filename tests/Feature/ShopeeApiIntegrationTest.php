<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\ShopeeApiConnection;
use App\Models\User;
use App\Services\ShopeeApiClient;
use App\Services\ShopeeOAuthService;
use App\Services\ShopeeResponseNormalizer;
use App\Services\ShopeeSyncService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER_ID = 'TENANT_PARTNER_ID_1';

    private const PARTNER_KEY = 'TENANT_PARTNER_KEY_SECRET_1';

    private const SHOP_ID = 'TENANT_SHOP_ID_1';

    private const ACCESS_TOKEN = 'TENANT_ACCESS_TOKEN_SECRET_1';

    private const REFRESH_TOKEN = 'TENANT_REFRESH_TOKEN_SECRET_1';

    private function activeUser(): User
    {
        return User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
    }

    private function connectedConnection(User $user): ShopeeApiConnection
    {
        return ShopeeApiConnection::create([
            'user_id' => $user->id,
            'environment' => 'production',
            'region' => 'global',
            'partner_id' => self::PARTNER_ID,
            'partner_key' => self::PARTNER_KEY,
            'shop_id' => self::SHOP_ID,
            'access_token' => self::ACCESS_TOKEN,
            'refresh_token' => self::REFRESH_TOKEN,
            'access_token_expires_at' => now()->addHour(),
            'refresh_token_expires_at' => now()->addYear(),
            'shop_name' => 'Test Shop',
            'connected_at' => now(),
        ]);
    }

    private function baseEnvelope(): array
    {
        return [
            'request_id' => 'req-4i',
            'error' => '-',
            'message' => '-',
        ];
    }

    private function orderEnvelope(array $orders, ?string $nextCursor = null, bool $more = false): array
    {
        $response = ['order_list' => $orders];

        if ($nextCursor !== null) {
            $response['next_cursor'] = $nextCursor;
            $response['more'] = $more;
        }

        return array_merge($this->baseEnvelope(), ['response' => $response]);
    }

    private function fakeOrderListResponse(array $orders, ?string $nextCursor = null, bool $more = false): void
    {
        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope($orders, $nextCursor, $more)),
        ]);
    }

    private function fakeIncomeResponse(array $items): void
    {
        Http::fake([
            '*/api/v2/payment/get_income_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => ['income_detail_list_item' => $items],
            ])),
        ]);
    }

    // ---------------------------------------------------------------
    // Authorization / token flow
    // ---------------------------------------------------------------

    public function test_authorization_url_is_built_with_signed_params(): void
    {
        $connection = $this->connectedConnection($this->activeUser());

        $service = new ShopeeOAuthService(app(HttpFactory::class));
        $url = $service->authorizationUrl($connection, 1700000000);

        $parsed = parse_url($url);
        $query = [];
        parse_str((string) ($parsed['query'] ?? ''), $query);

        $this->assertSame('partner.shopeemobile.com', $parsed['host']);
        $this->assertSame('/api/v2/shop/auth_partner', $parsed['path']);
        $this->assertSame(self::PARTNER_ID, $query['partner_id']);
        $this->assertSame('1700000000', $query['token']);

        $expectedSign = hash_hmac(
            'sha256',
            self::PARTNER_ID.'https://partner.shopeemobile.com/api/v2/shop/auth_partner'.'1700000000',
            self::PARTNER_KEY,
        );
        $this->assertSame($expectedSign, $query['sign']);
    }

    public function test_authorize_route_returns_oauth_url_upon_authorization(): void
    {
        $user = $this->activeUser();
        $this->connectedConnection($user);

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.authorize'));

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertStringStartsWith('https://partner.shopeemobile.com/api/v2/shop/auth_partner?', $response->json('url'));
        $this->assertStringNotContainsString(self::PARTNER_KEY, $response->getContent());
    }

    public function test_authorize_requires_configured_partner_credentials(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.authorize'));

        $response->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_code_exchange_stores_tokens_encrypted_and_marks_connected(): void
    {
        $user = $this->activeUser();
        $connection = $this->connectedConnection($user);
        $connection->update(['access_token' => null, 'refresh_token' => null, 'connected_at' => null]);

        Http::fake([
            '*/api/v2/auth/token/get*' => Http::response([
                'request_id' => 'req-auth',
                'error' => '-',
                'response' => [
                    'access_token' => 'EXCHANGED_ACCESS_TOKEN',
                    'refresh_token' => 'EXCHANGED_REFRESH_TOKEN',
                    'expires_in' => 14400,
                    'shop_id' => 123456,
                    'shop_name' => 'Connected Shop',
                ],
            ]),
        ]);

        $state = $this->startAuthorizationFlow($user);

        $response = $this->actingAs($user)->get(route('integrations.shopee-api.shopee-auth', ['code' => 'AUTH_CODE_1', 'state' => $state]));

        $response->assertRedirect(route('integrations.shopee-api'));

        $connection->refresh();
        $this->assertSame('EXCHANGED_ACCESS_TOKEN', $connection->access_token);
        $this->assertSame('EXCHANGED_REFRESH_TOKEN', $connection->refresh_token);
        $this->assertSame('Connected Shop', $connection->shop_name);
        $this->assertSame('123456', (string) $connection->shop_id);
        $this->assertNotNull($connection->connected_at);
        $this->assertNotNull($connection->access_token_expires_at);

        $raw = DB::table('shopee_api_connections')->where('user_id', $user->id)->first();
        $this->assertStringNotContainsString('EXCHANGED_ACCESS_TOKEN', (string) $raw->access_token);
        $this->assertStringNotContainsString('EXCHANGED_REFRESH_TOKEN', (string) $raw->refresh_token);

        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/api/v2/auth/token/get'));
    }

    public function test_oauth_callback_rejects_missing_or_mismatched_state_without_exchange(): void
    {
        $user = $this->activeUser();
        $this->connectedConnection($user)->update(['access_token' => null]);

        Http::fake([
            '*/api/v2/auth/token/get*' => Http::response([
                'error' => '-',
                'response' => ['access_token' => 'SHOULD_NOT_BE_EXCHANGED'],
            ]),
        ]);

        $state = $this->startAuthorizationFlow($user);

        $this->actingAs($user)->get(route('integrations.shopee-api.shopee-auth', ['code' => 'CODE_X', 'state' => $state.'-tampered']))
            ->assertRedirect(route('integrations.shopee-api'));

        $this->assertNull(
            ShopeeApiConnection::forUser($user->id)->first()->access_token,
            'A tampered state must not trigger a token exchange.'
        );

        $this->actingAs($user)->get(route('integrations.shopee-api.shopee-auth', ['code' => 'CODE_Y', 'state' => $state]))
            ->assertRedirect(route('integrations.shopee-api'));

        $this->assertNull(
            ShopeeApiConnection::forUser($user->id)->first()->access_token,
            'A state token is single-use and must not be accepted twice.'
        );

        Http::assertNothingSent();
    }

    /**
     * Starts the OAuth flow by hitting the authorize route, which stores a
     * per-session state and embeds it in the redirect URL. Returns the state.
     */
    private function startAuthorizationFlow(User $user): string
    {
        $authResponse = $this->actingAs($user)->getJson(route('integrations.shopee-api.authorize'));

        $authResponse->assertOk()->assertJson(['ok' => true]);

        $url = (string) $authResponse->json('url');
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $redirectQuery = [];
        parse_str((string) parse_url((string) ($query['redirect'] ?? ''), PHP_URL_QUERY), $redirectQuery);

        $state = (string) ($redirectQuery['state'] ?? '');

        $this->assertSame(self::PARTNER_ID, (string) ($query['partner_id'] ?? ''), 'Authorize flow must use the tenant connection.');
        $this->assertNotEmpty($state, 'Authorize flow must embed a state token in the redirect URL.');
        $this->assertStringNotContainsString(self::PARTNER_KEY, (string) $authResponse->getContent());

        return $state;
    }

    public function test_refresh_access_token_updates_token_and_expiry(): void
    {
        $connection = $this->connectedConnection($this->activeUser());
        $connection->update(['access_token_expires_at' => now()->subMinute()]);

        Http::fake([
            '*/api/v2/auth/access_token/get*' => Http::response([
                'request_id' => 'req-refresh',
                'error' => '-',
                'response' => ['access_token' => 'REFRESHED_ACCESS_TOKEN', 'expires_in' => 14400],
            ]),
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                ['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000],
            ])),
        ]);

        $sync = new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            new ShopeeOAuthService(app(HttpFactory::class)),
            app(ShopeeResponseNormalizer::class),
        );

        $result = $sync->syncSampleOrders($connection);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['order_count']);

        $connection->refresh();
        $this->assertSame('REFRESHED_ACCESS_TOKEN', $connection->access_token);
        $this->assertSame(self::REFRESH_TOKEN, $connection->refresh_token);

        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/api/v2/auth/access_token/get'));
    }

    // ---------------------------------------------------------------
    // Secure credential storage / non-exposure
    // ---------------------------------------------------------------

    public function test_all_credential_values_are_stored_encrypted_in_db(): void
    {
        $user = $this->activeUser();

        ShopeeApiConnection::create([
            'user_id' => $user->id,
            'partner_id' => self::PARTNER_ID,
            'partner_key' => self::PARTNER_KEY,
            'shop_id' => self::SHOP_ID,
            'access_token' => self::ACCESS_TOKEN,
            'refresh_token' => self::REFRESH_TOKEN,
        ]);

        $raw = DB::table('shopee_api_connections')->where('user_id', $user->id)->first();

        $stored = [
            'partner_id' => $raw->partner_id,
            'partner_key' => $raw->partner_key,
            'shop_id' => $raw->shop_id,
            'access_token' => $raw->access_token,
            'refresh_token' => $raw->refresh_token,
        ];

        foreach (['partner_id', 'partner_key', 'shop_id', 'access_token', 'refresh_token'] as $field) {
            $this->assertNotEmpty($stored[$field]);
            $this->assertNotSame($this->secretFor($field), $stored[$field]);
            $this->assertStringStartsWith('eyJ', (string) $stored[$field]);
        }
    }

    private function secretFor(string $field): string
    {
        return match ($field) {
            'partner_id' => self::PARTNER_ID,
            'partner_key' => self::PARTNER_KEY,
            'shop_id' => self::SHOP_ID,
            'access_token' => self::ACCESS_TOKEN,
            'refresh_token' => self::REFRESH_TOKEN,
        };
    }

    public function test_safe_state_and_status_response_never_expose_credentials(): void
    {
        $user = $this->activeUser();
        $this->connectedConnection($user);

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.status'));

        $response->assertOk();
        $body = $response->getContent();

        foreach ([self::PARTNER_ID, self::PARTNER_KEY, self::SHOP_ID, self::ACCESS_TOKEN, self::REFRESH_TOKEN] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        $response->assertJsonMissingPath('connection.access_token');
        $response->assertJsonMissingPath('connection.refresh_token');
        $response->assertJsonMissingPath('connection.partner_key');
        $response->assertJsonMissingPath('connection.partner_id');
        $response->assertJsonMissingPath('connection.shop_id');
        $response->assertJsonMissingPath('config.partner_id');
        $response->assertJsonPath('connection.shop_name', 'Test Shop');
    }

    public function test_exception_messages_and_logs_do_not_leak_secrets(): void
    {
        $user = $this->activeUser();
        $this->connectedConnection($user);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response('', 429),
        ]);

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.orders'));

        $response->assertStatus(429);
        foreach ([self::ACCESS_TOKEN, self::PARTNER_KEY, self::REFRESH_TOKEN] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    // ---------------------------------------------------------------
    // Signature / tenant isolation
    // ---------------------------------------------------------------

    public function test_order_request_uses_tenant_connection_credentials(): void
    {
        $user = $this->activeUser();
        $this->connectedConnection($user);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                ['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000],
            ])),
        ]);

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.orders'));

        $response->assertOk()->assertJson(['ok' => true, 'order_count' => 1]);

        Http::assertSent(function (ClientRequest $request): bool {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $this->assertSame(self::PARTNER_ID, $query['partner_id']);
            $this->assertSame(self::ACCESS_TOKEN, $query['access_token']);
            $this->assertSame(self::SHOP_ID, $query['shop_id']);

            $expectedSign = hash_hmac(
                'sha256',
                self::PARTNER_ID.'/api/v2/order/get_order_list'.$query['timestamp'].self::ACCESS_TOKEN.self::SHOP_ID,
                self::PARTNER_KEY,
            );

            return $query['sign'] === $expectedSign;
        });
    }

    public function test_sync_never_crosses_tenant_boundaries(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $first = $this->activeUser();
        $second = $this->activeUser();
        $this->connectedConnection($first);
        $this->connectedConnection($second);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                ['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000],
            ])),
        ]);

        $this->actingAs($first)->postJson(route('integrations.shopee-api.sync-orders'), ['page_size' => 5])
            ->assertOk()->assertJson(['ok' => true, 'order_count' => 1]);

        $firstConnection = ShopeeApiConnection::forUser($first->id)->first();
        $secondConnection = ShopeeApiConnection::forUser($second->id)->first();

        $this->assertNotEmpty($firstConnection->staging_orders);
        $this->assertEmpty($secondConnection->staging_orders);

        $rawPartnerKey = DB::table('shopee_api_connections')->where('user_id', $second->id)->value('partner_key');
        $this->assertNotSame(self::PARTNER_KEY, $rawPartnerKey, 'Partner key must be stored encrypted');
        $this->assertTrue(str_starts_with($rawPartnerKey, 'ey'), 'Encrypted column must be ciphertext');
        $this->assertSame(self::PARTNER_KEY, $secondConnection->partner_key);
    }

    // ---------------------------------------------------------------
    // Pagination + partial failure
    // ---------------------------------------------------------------

    public function test_cursor_pagination_walks_all_pages_and_persists_cursor(): void
    {
        $connection = $this->connectedConnection($this->activeUser());

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::sequence()
                ->push($this->orderEnvelope(
                    [['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000]],
                    'cursor-2',
                    true,
                ))
                ->push($this->orderEnvelope(
                    [['order_sn' => '221215UNDEYD5D', 'order_status' => 'SHIPPED', 'create_time' => 1700000300]],
                    'cursor-3',
                    true,
                ))
                ->push($this->orderEnvelope(
                    [['order_sn' => '221215UNDEYD5E', 'order_status' => 'COMPLETED', 'create_time' => 1700000600]],
                )),
        ]);

        $sync = new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            new ShopeeOAuthService(app(HttpFactory::class)),
            app(ShopeeResponseNormalizer::class),
        );

        $result = $sync->syncSampleOrders($connection);

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['order_count']);
        $this->assertSame(3, $result['pages']);
        $this->assertFalse($result['more']);
        $this->assertSame('', $result['cursor']);

        $connection->refresh();
        $this->assertCount(3, $connection->staging_orders);
        $this->assertSame('', $connection->last_sync_order_cursor);
        $this->assertSame('success', $connection->last_sync_status);
    }

    public function test_429_backoff_retries_then_succeeds(): void
    {
        $connection = $this->connectedConnection($this->activeUser());
        $sleeps = [];

        Http::fake([
            '*/api/v2/payment/get_income_detail*' => Http::sequence()
                ->push('', 429)
                ->push(array_merge($this->baseEnvelope(), [
                    'response' => ['income_detail_list_item' => [
                        ['order_sn' => '220404NF3CFFNY', 'description' => 'Income', 'status' => 'Released', 'total_income' => 100],
                    ]],
                ])),
        ]);

        $sync = new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            new ShopeeOAuthService(app(HttpFactory::class)),
            app(ShopeeResponseNormalizer::class),
            function (int $seconds) use (&$sleeps): int {
                $sleeps[] = $seconds;

                return 0;
            },
        );

        $result = $sync->syncSampleIncome($connection);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['row_count']);
        $this->assertSame([2], $sleeps);
        $this->assertSame('success', $connection->refresh()->last_sync_status);
    }

    public function test_429_exhausted_backoff_marks_rate_limited_and_preserves_staging(): void
    {
        $connection = $this->connectedConnection($this->activeUser());

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::sequence()
                ->push($this->orderEnvelope(
                    [['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000]],
                    'cursor-2',
                    true,
                ))
                ->push('', 429)
                ->push('', 429)
                ->push('', 429),
        ]);

        $sync = new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            new ShopeeOAuthService(app(HttpFactory::class)),
            app(ShopeeResponseNormalizer::class),
            fn (int $seconds): int => $seconds,
        );

        $result = $sync->syncSampleOrders($connection);

        $this->assertFalse($result['ok']);
        $this->assertSame(1, $result['order_count']);
        $this->assertTrue($result['rate_limited']);

        $connection->refresh();
        $this->assertCount(1, $connection->staging_orders);
        $this->assertSame('cursor-2', $connection->last_sync_order_cursor);
        $this->assertSame('rate_limited', $connection->last_sync_status);
    }

    public function test_envelope_error_on_recommend_stops_partial_and_stores_error(): void
    {
        $connection = $this->connectedConnection($this->activeUser());

        Http::fake([
            '*/api/v2/payment/get_income_detail*' => Http::response([
                'request_id' => 'req-err',
                'error' => 'error_auth',
                'message' => 'shop session expired: reconnect',
            ], 200),
        ]);

        $sync = new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            new ShopeeOAuthService(app(HttpFactory::class)),
            app(ShopeeResponseNormalizer::class),
        );

        $result = $sync->syncSampleIncome($connection);

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['row_count']);
        $this->assertSame('shop session expired: reconnect', $result['error']);

        $connection->refresh();
        $this->assertSame('error', $connection->last_sync_status);
        $this->assertSame('shop session expired: reconnect', $connection->last_sync_error);
    }

    // ---------------------------------------------------------------
    // Normalization + staged persistence (via real sync)
    // ---------------------------------------------------------------

    public function test_order_sync_normalizes_and_stages_rows(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $connection = $this->connectedConnection($user);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                [
                    'order_sn' => '220404NF3CFFNY',
                    'order_status' => 'COMPLETED',
                    'payment_method' => 'PAY_PROFILE',
                    'package_list' => [['shipping_carrier' => 'J&T']],
                    'buyer_username' => 'buyer_one',
                    'create_time' => 1700000000,
                    'pay_time' => 1700000100,
                    'pickup_done_time' => 1700000200,
                ],
            ])),
        ]);

        $result = $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-orders'), ['page_size' => 5])
            ->assertOk()
            ->json();

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['order_count']);
        $this->assertSame('220404NF3CFFNY', $result['normalized'][0]['order_number']);
        $this->assertSame('COMPLETED', $result['normalized'][0]['order_status']);
        $this->assertSame('J&T', $result['normalized'][0]['shipping_option']);
        $this->assertSame(Carbon::createFromTimestamp(1700000000)->toDateTimeString(), $result['normalized'][0]['order_created_at']);

        $connection->refresh();
        $this->assertCount(1, $connection->staging_orders);
        $this->assertSame('success', $connection->last_sync_status);
        $this->assertDatabaseCount('marketplace_orders', 0);
    }

    public function test_income_sync_normalizes_and_stages_rows(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $connection = $this->connectedConnection($user);

        $this->fakeIncomeResponse([
            [
                'order_sn' => '220404NF3CFFNY',
                'description' => 'Total Income',
                'status' => 'Released',
                'payment_method' => 'PAY_PROFILE',
                'currency' => 'IDR',
                'total_income' => 145000,
                'release_time' => 1700001000,
            ],
        ]);

        $result = $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-income'))
            ->assertOk()
            ->json();

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['row_count']);
        $this->assertSame('220404NF3CFFNY', $result['normalized'][0]['order_number']);
        $this->assertSame('Total Income', $result['normalized'][0]['row_type']);
        $this->assertSame(145000, $result['normalized'][0]['total_income']);

        $connection->refresh();
        $this->assertCount(1, $connection->staging_income);
        $this->assertDatabaseCount('marketplace_income', 0);
    }

    // ---------------------------------------------------------------
    // Zero production-table writes
    // ---------------------------------------------------------------

    public function test_full_connect_and_sync_cycle_writes_zero_production_rows(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $this->connectedConnection($user);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                ['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000],
            ])),
            '*/api/v2/order/get_order_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => ['order_list' => [
                    ['order_sn' => '220404NF3CFFNY', 'order_status' => 'COMPLETED', 'create_time' => 1700000000],
                ]],
            ])),
            '*/api/v2/payment/get_escrow_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => ['order_income' => [
                    'order_sn' => '220404NF3CFFNY', 'escrow_amount' => 100, 'buyer_total_amount' => 112,
                    'items' => [['item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'original_price' => 50, 'quantity_purchased' => 2]],
                ]],
            ])),
            '*/api/v2/payment/get_income_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => ['income_detail_list_item' => [
                    ['order_sn' => '220404NF3CFFNY', 'description' => 'Income', 'status' => 'Released', 'total_income' => 100],
                ]],
            ])),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-orders'))->assertOk();
        $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-income'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.status'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.test'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.orders'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.order-detail', '220404NF3CFFNY'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.income'))->assertOk();

        $this->assertDatabaseCount('marketplace_orders', 0);
        $this->assertDatabaseCount('marketplace_income', 0);
        $this->assertDatabaseCount('order_cost_allocations', 0);
        $this->assertDatabaseCount('shopee_api_connections', 1);
    }

    public function test_configure_and_status_routes_are_tenant_scoped(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $first = $this->activeUser();
        $second = $this->activeUser();
        $this->connectedConnection($first);
        $this->connectedConnection($second);

        $firstId = ShopeeApiConnection::forUser($first->id)->value('id');
        $secondId = ShopeeApiConnection::forUser($second->id)->value('id');
        $this->assertNotSame($firstId, $secondId);

        $this->actingAs($first)->postJson(route('integrations.shopee-api.configure'), [
            'environment' => 'production',
            'region' => 'global',
            'partner_id' => 'CHANGED_PARTNER_ID',
            'partner_key' => 'CHANGED_KEY',
        ])->assertOk();

        $this->assertSame(
            'CHANGED_PARTNER_ID',
            ShopeeApiConnection::forUser($first->id)->first()->partner_id
        );
        $this->assertSame(
            self::PARTNER_ID,
            ShopeeApiConnection::forUser($second->id)->first()->partner_id
        );
    }
}
