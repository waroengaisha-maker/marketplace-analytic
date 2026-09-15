<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\ShopeeApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeApiPocTest extends TestCase
{
    use RefreshDatabase;

    private const API_CONFIG = [
        'environment' => 'production',
        'region' => 'global',
        'host' => '',
        'timeout' => 30,
        'partner_id' => 'SENTINEL_PARTNER_ID',
        'partner_key' => 'SENTINEL_PARTNER_KEY_VALUE',
        'shop_id' => 'SENTINEL_SHOP_ID',
        'access_token' => 'SENTINEL_ACCESS_TOKEN_VALUE',
    ];

    private function activeUser(): User
    {
        return User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
    }

    private function configureApi(): void
    {
        config(['shopee-api' => self::API_CONFIG]);
    }

    public function test_lab_routes_require_authentication(): void
    {
        foreach ([
            'integrations.shopee-api.status',
            'integrations.shopee-api.test',
            'integrations.shopee-api.orders',
            'integrations.shopee-api.income',
        ] as $name) {
            $this->get(route($name))->assertRedirect('/login');
        }

        $this->post(route('integrations.shopee-api.clear'))->assertRedirect('/login');

        $this->get(route('integrations.shopee-api.order-detail', '220404NF3CFFNY'))->assertRedirect('/login');
    }

    public function test_configured_status_response_never_exposes_credentials(): void
    {
        $this->configureApi();
        $user = $this->activeUser();

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.status'));

        $response->assertOk()->assertJson([
            'ok' => true,
            'config' => [
                'configured' => true,
                'missing' => [],
                'environment' => 'production',
                'region' => 'global',
                'host' => 'https://partner.shopeemobile.com',
            ],
        ]);
        $response->assertJsonMissingPath('config.partner_id');
        $response->assertJsonMissingPath('config.partner_key');
        $response->assertJsonMissingPath('config.shop_id');
        $response->assertJsonMissingPath('config.access_token');

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::API_CONFIG['partner_id'], $body);
        $this->assertStringNotContainsString(self::API_CONFIG['partner_key'], $body);
        $this->assertStringNotContainsString(self::API_CONFIG['shop_id'], $body);
        $this->assertStringNotContainsString(self::API_CONFIG['access_token'], $body);
    }

    public function test_lab_endpoint_responses_never_contain_credential_values(): void
    {
        $this->configureApi();
        $this->fakeApiEnvelopes();
        $user = $this->activeUser();

        $bodies = [];

        foreach (['status', 'test', 'orders', 'income'] as $path) {
            $bodies[] = $this->actingAs($user)->getJson(route('integrations.shopee-api.'.$path))->getContent();
        }

        $bodies[] = $this->actingAs($user)->postJson(route('integrations.shopee-api.clear'))->getContent();

        $bodies[] = $this->actingAs($user)
            ->getJson(route('integrations.shopee-api.order-detail', '220404NF3CFFNY'))
            ->getContent();

        foreach ($bodies as $body) {
            $this->assertStringNotContainsString(self::API_CONFIG['partner_id'], $body);
            $this->assertStringNotContainsString(self::API_CONFIG['partner_key'], $body);
            $this->assertStringNotContainsString(self::API_CONFIG['shop_id'], $body);
            $this->assertStringNotContainsString(self::API_CONFIG['access_token'], $body);
        }
    }

    public function test_unconfigured_status_reports_missing_names_without_values(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->getJson(route('integrations.shopee-api.status'));

        $response->assertOk()->assertJson([
            'ok' => true,
            'config' => [
                'configured' => false,
                'missing' => ['partner_id', 'partner_key', 'shop_id', 'access_token'],
            ],
        ]);
        $this->assertStringNotContainsString('env(', $response->getContent());
    }

    public function test_client_builds_signed_request_and_returns_envelope(): void
    {
        $this->fakeApiEnvelopes();

        $client = new ShopeeApiClient(app(HttpFactory::class), self::API_CONFIG);
        $envelope = $client->getOrderList(['page_size' => 5]);

        $this->assertSame('-', $envelope['error']);
        $this->assertCount(2, data_get($envelope, 'response.order_list'));

        Http::assertSent(function (ClientRequest $request): bool {
            $parsedUrl = parse_url($request->url());
            $query = [];
            parse_str((string) ($parsedUrl['query'] ?? ''), $query);

            $timestamp = (int) ($query['timestamp'] ?? 0);

            $baseString = self::API_CONFIG['partner_id']
                .ShopeeApiClient::PATH_ORDER_LIST
                .$timestamp
                .self::API_CONFIG['access_token']
                .self::API_CONFIG['shop_id'];
            $expectedSign = hash_hmac('sha256', $baseString, self::API_CONFIG['partner_key']);

            return $query['partner_id'] === self::API_CONFIG['partner_id']
                && $query['access_token'] === self::API_CONFIG['access_token']
                && $query['shop_id'] === self::API_CONFIG['shop_id']
                && $query['sign'] === $expectedSign
                && $query['page_size'] === '5';
        });
    }

    public function test_orders_endpoint_normalizes_order_headers(): void
    {
        $this->configureApi();
        $this->fakeApiEnvelopes();

        $response = $this->actingAs($this->activeUser())
            ->getJson(route('integrations.shopee-api.orders', ['page_size' => 10]));

        $response->assertOk()->assertJson([
            'ok' => true,
            'order_count' => 2,
            'more' => true,
        ]);

        $normalized = $response->json('normalized')[0];
        $this->assertSame('220404NF3CFFNY', $normalized['order_number']);
        $this->assertSame('COMPLETED', $normalized['order_status']);
        $this->assertSame('PAY_PROFILE', $normalized['payment_method']);
        $this->assertSame('J&T', $normalized['shipping_option']);
        $this->assertSame('buyer_one', $normalized['buyer_username']);
        $this->assertSame(Carbon::createFromTimestamp(1700000000)->toDateTimeString(), $normalized['order_created_at']);
        $this->assertSame(Carbon::createFromTimestamp(1700000100)->toDateTimeString(), $normalized['payment_at']);
        $this->assertSame(Carbon::createFromTimestamp(1700000200)->toDateTimeString(), $normalized['shipped_at']);
        $this->assertNull(data_get($response->json('normalized'), '1.shipping_option'));
    }

    public function test_order_detail_endpoint_normalizes_headers_escrow_and_lines(): void
    {
        $this->configureApi();
        $this->fakeApiEnvelopes();

        $response = $this->actingAs($this->activeUser())
            ->getJson(route('integrations.shopee-api.order-detail', '220404NF3CFFNY'));

        $response->assertOk()->assertJson([
            'ok' => true,
            'order_number' => '220404NF3CFFNY',
            'escrow' => [
                'order_number' => '220404NF3CFFNY',
                'buyer_amount' => 112,
                'escrow_amount' => 100,
                'currency' => 'IDR',
                'coins' => 5,
            ],
            'lines' => [
                [
                    'order_number' => '220404NF3CFFNY',
                    'parent_sku' => 'SKU-A',
                    'product_name' => 'Kemeja',
                    'sku_reference' => 'SKU-A-M',
                    'variation_name' => 'M',
                    'original_price' => 50,
                    'discounted_price' => 45,
                    'quantity' => 2,
                ],
            ],
        ]);

        $this->assertSame('COMPLETED', $response->json('headers.0.order_status'));
    }

    public function test_income_endpoint_normalizes_income_rows(): void
    {
        $this->configureApi();
        $this->fakeApiEnvelopes();

        $response = $this->actingAs($this->activeUser())
            ->getJson(route('integrations.shopee-api.income'));

        $response->assertOk()->assertJson([
            'ok' => true,
            'normalized' => [
                [
                    'order_number' => '220404NF3CFFNY',
                    'row_type' => 'Total Income',
                    'status' => 'Released',
                    'payment_method' => 'PAY_PROFILE',
                    'currency' => 'IDR',
                    'total_income' => 145000,
                    'income_released_at' => Carbon::createFromTimestamp(1700001000)->toDateTimeString(),
                ],
            ],
        ]);
    }

    public function test_api_rate_limit_returns_429_rate_limited_flag(): void
    {
        $this->configureApi();
        Http::fake([
            '*' => Http::response('', 429),
        ]);

        $response = $this->actingAs($this->activeUser())
            ->getJson(route('integrations.shopee-api.orders'));

        $response->assertStatus(429)->assertJson([
            'ok' => false,
            'error' => 'Shopee API HTTP 429 (rate limited).',
            'rate_limited' => true,
        ]);
    }

    public function test_envelope_api_error_is_surfaced_without_credentials(): void
    {
        $this->configureApi();
        Http::fake([
            '*' => Http::response([
                'request_id' => 'req-err',
                'error' => 'error_parameter',
                'message' => 'Invalid parameter value.',
            ], 200),
        ]);

        $response = $this->actingAs($this->activeUser())
            ->getJson(route('integrations.shopee-api.orders'));

        $response->assertStatus(502)->assertJson([
            'ok' => false,
            'error' => 'Invalid parameter value.',
            'rate_limited' => false,
        ]);
        $this->assertStringNotContainsString(self::API_CONFIG['partner_key'], $response->getContent());
        $this->assertStringNotContainsString(self::API_CONFIG['access_token'], $response->getContent());
    }

    public function test_identical_lab_responses_across_tenants_with_no_cross_user_data(): void
    {
        $this->configureApi();
        $this->fakeApiEnvelopes();

        $first = $this->activeUser();
        $second = $this->activeUser();

        $firstOrders = $this->actingAs($first)->getJson(route('integrations.shopee-api.orders'));
        $secondOrders = $this->actingAs($second)->getJson(route('integrations.shopee-api.orders'));

        $this->assertSame($firstOrders->getContent(), $secondOrders->getContent());
        $this->assertSame('220404NF3CFFNY', $firstOrders->json('normalized.0.order_number'));
        $firstOrders->assertJsonMissingPath('normalized.0.user_id');
        $secondOrders->assertJsonMissingPath('normalized.0.user_id');

        $this->assertDatabaseMissing('marketplace_orders', ['user_id' => $first->id]);
        $this->assertDatabaseMissing('marketplace_orders', ['user_id' => $second->id]);
    }

    public function test_lab_actions_never_write_to_production_tables(): void
    {
        $this->configureApi();
        $this->fakeApiEnvelopes();
        $user = $this->activeUser();

        $this->actingAs($user)->getJson(route('integrations.shopee-api.status'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.test'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.orders'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.order-detail', '220404NF3CFFNY'))->assertOk();
        $this->actingAs($user)->getJson(route('integrations.shopee-api.income'))->assertOk();
        $this->actingAs($user)->postJson(route('integrations.shopee-api.clear'))->assertOk();

        $this->assertDatabaseCount('marketplace_orders', 0);
        $this->assertDatabaseCount('marketplace_income', 0);
        $this->assertDatabaseCount('order_cost_allocations', 0);
    }

    private function fakeApiEnvelopes(): void
    {
        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => [
                    'order_list' => [
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
                        [
                            'order_sn' => '221215UNDEYD5D',
                            'order_status' => 'SHIPPED',
                            'payment_method' => 'COD',
                            'package_list' => [],
                            'buyer_username' => null,
                            'create_time' => 1700000300,
                            'pay_time' => null,
                            'pickup_done_time' => 1700000400,
                        ],
                    ],
                    'next_cursor' => 'cursor-2',
                    'more' => true,
                ],
            ])),
            '*/api/v2/order/get_order_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => [
                    'order_list' => [
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
                    ],
                ],
            ])),
            '*/api/v2/payment/get_escrow_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => [
                    'order_income' => [
                        'order_sn' => '220404NF3CFFNY',
                        'escrow_amount' => 100,
                        'buyer_total_amount' => 112,
                        'currency' => 'IDR',
                        'coins' => 5,
                        'voucher_from_seller' => 2,
                        'voucher_from_shopee' => 3,
                        'credit_card_promotion' => 1,
                        'items' => [
                            [
                                'item_id' => 1001,
                                'item_name' => 'Kemeja',
                                'item_sku' => 'SKU-A',
                                'model_id' => 2001,
                                'model_name' => 'M',
                                'model_sku' => 'SKU-A-M',
                                'original_price' => 50,
                                'discounted_price' => 45,
                                'quantity_purchased' => 2,
                            ],
                        ],
                    ],
                ],
            ])),
            '*/api/v2/payment/get_income_detail*' => Http::response(array_merge($this->baseEnvelope(), [
                'response' => [
                    'income_detail_list_item' => [
                        [
                            'order_sn' => '220404NF3CFFNY',
                            'description' => 'Total Income',
                            'status' => 'Released',
                            'payment_method' => 'PAY_PROFILE',
                            'currency' => 'IDR',
                            'total_income' => 145000,
                            'release_time' => 1700001000,
                        ],
                    ],
                ],
            ])),
        ]);
    }

    private function baseEnvelope(): array
    {
        return [
            'request_id' => 'req-poc',
            'error' => '-',
            'message' => '-',
        ];
    }
}
