<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\ShopeeApiConnection;
use App\Models\User;
use App\Services\ReportLineIdentity;
use App\Services\ShopeeApiClient;
use App\Services\ShopeeOAuthService;
use App\Services\ShopeeResponseNormalizer;
use App\Services\ShopeeSyncService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeShadowValidationTest extends TestCase
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

    private function connectedConnection(User $user, array $overrides = []): ShopeeApiConnection
    {
        return ShopeeApiConnection::create(array_merge([
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
        ], $overrides));
    }

    private function baseEnvelope(): array
    {
        return [
            'request_id' => 'req-4j',
            'error' => '-',
            'message' => '-',
        ];
    }

    private function escrowEnvelope(string $orderSn, array $items): array
    {
        return array_merge($this->baseEnvelope(), ['response' => [
            'order_income' => [
                'order_sn' => $orderSn,
                'escrow_amount' => 100,
                'buyer_total_amount' => 112,
                'items' => $items,
            ],
        ]]);
    }

    private function orderStaging(string $orderSn): array
    {
        return [
            'order_sn' => $orderSn,
            'order_status' => 'COMPLETED',
            'payment_method' => 'PAY_PROFILE',
            'package_list' => [['shipping_carrier' => 'J&T']],
            'buyer_username' => 'buyer_one',
            'create_time' => 1700000000,
            'pay_time' => 1700000100,
            'pickup_done_time' => 1700000200,
        ];
    }

    private function excelOrderRow(int $userId, string $orderSn, array $overrides = []): array
    {
        $productName = strtolower(trim((string) ($overrides['product_name'] ?? 'Kemeja')));
        $variationName = $overrides['variation_name'] ?? 'M';
        $productKey = hash('sha256', $productName);
        $variationKey = $variationName === null ? null : hash('sha256', strtolower(trim($variationName)));
        $discountedPrice = (float) ($overrides['discounted_price'] ?? 45);
        $quantity = (int) ($overrides['quantity'] ?? 2);
        $lineIdentity = ReportLineIdentity::make($orderSn, $productKey, $variationKey, $discountedPrice, $quantity);

        return array_merge([
            'user_id' => $userId,
            'order_number' => $orderSn,
            'line_identity' => $lineIdentity,
            'order_status' => 'COMPLETED',
            'payment_method' => 'PAY_PROFILE',
            'buyer_username' => 'buyer_one',
            'shipping_option' => 'J&T',
            'order_created_at' => Carbon::createFromTimestamp(1700000000)->toDateTimeString(),
            'payment_at' => Carbon::createFromTimestamp(1700000100)->toDateTimeString(),
            'shipped_at' => Carbon::createFromTimestamp(1700000200)->toDateTimeString(),
            'parent_sku' => 'SKU-A',
            'product_name' => 'Kemeja',
            'sku_reference' => 'SKU-A-M',
            'variation_name' => 'M',
            'original_price' => 50,
            'discounted_price' => 45,
            'quantity' => 2,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function excelIncomeRow(int $userId, string $orderSn, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $userId,
            'order_number' => $orderSn,
            'row_type' => 'Total Income',
            'total_income' => 145000,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // sync-escrow
    // ---------------------------------------------------------------

    public function test_sync_escrow_stages_raw_payloads_and_normalized_lines(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $connection = $this->connectedConnection($user, ['staging_orders' => [
            $this->orderStaging('220404NF3CFFNY'),
            $this->orderStaging('220404AB12CDEF'),
        ]]);

        Http::fake([
            '*/api/v2/payment/get_escrow_detail*' => Http::sequence()
                ->push($this->escrowEnvelope('220404NF3CFFNY', [[
                    'item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'model_name' => 'M',
                    'original_price' => 50, 'discounted_price' => 45, 'quantity_purchased' => 2,
                ]]))
                ->push($this->escrowEnvelope('220404AB12CDEF', [[
                    'item_sku' => 'SKU-B', 'item_name' => 'Celana', 'model_sku' => 'SKU-B-L', 'model_name' => 'L',
                    'original_price' => 80, 'discounted_price' => 70, 'quantity_purchased' => 1,
                ]])),
        ]);

        $result = $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-escrow'), ['limit' => 5])
            ->assertOk()
            ->json();

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['escrow_count']);
        $this->assertSame(2, $result['total_orders']);
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['normalized']);
        $this->assertSame('Kemeja', $result['normalized'][0]['product_name']);
        $this->assertSame(2, $result['normalized'][0]['quantity']);

        $connection->refresh();
        $staged = $connection->staging_escrow;
        $this->assertCount(2, $staged);
        $this->assertSame('220404NF3CFFNY', $staged[0]['order_sn']);
        $this->assertSame('220404AB12CDEF', $staged[1]['order_sn']);
        $this->assertSame(100, data_get($staged[0], 'response.order_income.escrow_amount'));
        $this->assertSame('success', $connection->last_sync_status);
        $this->assertDatabaseCount('marketplace_orders', 0);
        $this->assertDatabaseCount('marketplace_income', 0);
    }

    public function test_sync_escrow_marks_partial_rate_limited_failure(): void
    {
        $user = $this->activeUser();
        $connection = $this->connectedConnection($user, ['staging_orders' => [
            $this->orderStaging('220404NF3CFFNY'),
            $this->orderStaging('220404AB12CDEF'),
        ]]);

        Http::fake([
            '*/api/v2/payment/get_escrow_detail*' => Http::sequence()
                ->push($this->escrowEnvelope('220404NF3CFFNY', [[
                    'item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'model_name' => 'M',
                    'original_price' => 50, 'discounted_price' => 45, 'quantity_purchased' => 2,
                ]]))
                ->push('', 429)
                ->push('', 429)
                ->push('', 429),
        ]);

        $service = new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            app(ShopeeOAuthService::class),
            app(ShopeeResponseNormalizer::class),
            fn (int $seconds): null => null,
        );

        $result = $service->syncSampleEscrow($connection, ['limit' => 5]);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['rate_limited']);
        $this->assertSame(1, $result['escrow_count']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('220404AB12CDEF', $result['errors'][0]['order_sn']);
        $this->assertTrue($result['errors'][0]['rate_limited']);

        $connection->refresh();
        $this->assertCount(1, $connection->staging_escrow);
        $this->assertSame('220404NF3CFFNY', $connection->staging_escrow[0]['order_sn']);
        $this->assertSame('rate_limited', $connection->last_sync_status);
    }

    public function test_sync_escrow_is_tenant_scoped(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $first = $this->connectedConnection($user, ['staging_orders' => [
            $this->orderStaging('220404NF3CFFNY'),
        ]]);

        Http::fake([
            '*/api/v2/payment/get_escrow_detail*' => Http::response($this->escrowEnvelope('220404AB12CDEF', [[
                'item_sku' => 'SKU-B', 'item_name' => 'Celana', 'model_sku' => 'SKU-B-L', 'model_name' => 'L',
                'original_price' => 80, 'discounted_price' => 70, 'quantity_purchased' => 1,
            ]])),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-escrow'), ['limit' => 5])->assertOk();

        $other = $this->connectedConnection($this->activeUser(), [
            'partner_id' => 'TENANT_PARTNER_ID_2',
            'partner_key' => 'TENANT_PARTNER_KEY_SECRET_2',
            'shop_id' => 'TENANT_SHOP_ID_2',
            'access_token' => 'TENANT_ACCESS_TOKEN_SECRET_2',
            'staging_orders' => [
                $this->orderStaging('220404AB12CDEF'),
            ],
        ]);

        $result = $this->actingAs($other->user)->postJson(route('integrations.shopee-api.sync-escrow'), ['limit' => 5])
            ->assertOk()
            ->json();

        $this->assertSame(1, $result['escrow_count']);
        $this->assertSame('220404AB12CDEF', $result['normalized'][0]['order_number']);

        $first->refresh();
        $this->assertCount(1, $first->staging_escrow);
        $this->assertSame('220404NF3CFFNY', $first->staging_escrow[0]['order_sn']);
    }

    // ---------------------------------------------------------------
    // validate
    // ---------------------------------------------------------------

    public function test_validate_reports_matched_orders_lines_and_income(): void
    {
        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [[
                'order_sn' => $orderSn,
                'response' => $this->escrowEnvelope($orderSn, [[
                    'item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'model_name' => 'M',
                    'original_price' => 50, 'discounted_price' => 45, 'quantity_purchased' => 2,
                ]])['response'],
            ]],
            'staging_income' => [[
                'order_sn' => $orderSn, 'description' => 'Total Income', 'status' => 'Released',
                'total_income' => 145000, 'release_time' => 1700001000,
            ]],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));
        DB::table('marketplace_income')->insert($this->excelIncomeRow($user->id, $orderSn));

        $report = $this->actingAs($user)->getJson(route('integrations.shopee-api.validate'))
            ->assertOk()
            ->json();

        $this->assertTrue($report['ok']);
        $this->assertSame(['matched' => 1, 'mismatched' => 0, 'missing_in_excel' => 0, 'missing_in_api' => 0], $report['summary']['orders']);
        $this->assertSame(1, $report['summary']['lines']['matched']);
        $this->assertSame(0, $report['summary']['lines']['mismatched']);
        $this->assertSame(0, $report['summary']['lines']['missing_in_excel']);
        $this->assertSame(0, $report['summary']['lines']['missing_in_api']);
        $this->assertSame(0, $report['summary']['lines']['line_identity_changed']);
        $this->assertSame(['matched' => 1, 'mismatched' => 0, 'missing_in_excel' => 0, 'missing_in_api' => 0], $report['summary']['income']);

        $order = $report['orders'][0];
        $this->assertSame('matched', $order['status']);
        $this->assertSame([], $order['differences']);

        $line = $report['lines'][0];
        $this->assertSame('matched', $line['status']);
        $this->assertFalse($line['line_identity_changed']);
    }

    public function test_validate_detects_mismatches_and_missing_rows(): void
    {
        $user = $this->activeUser();

        $matchedSn = '220404NF3CFFNY';
        $mismatchSn = '220404AB12CDEF';
        $apiOnlySn = '220404ONLYAPI';
        $excelOnlySn = '220404ONLYXLS';

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [
                $this->orderStaging($matchedSn),
                array_replace($this->orderStaging($mismatchSn), ['order_status' => 'SHIPPED']),
                $this->orderStaging($apiOnlySn),
            ],
            'staging_escrow' => [
                [
                    'order_sn' => $matchedSn,
                    'response' => $this->escrowEnvelope($matchedSn, [[
                        'item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'model_name' => 'M',
                        'original_price' => 50, 'discounted_price' => 45, 'quantity_purchased' => 2,
                    ]])['response'],
                ],
                [
                    'order_sn' => $mismatchSn,
                    'response' => $this->escrowEnvelope($mismatchSn, [[
                        'item_sku' => 'SKU-B', 'item_name' => 'Celana', 'model_sku' => 'SKU-B-L', 'model_name' => 'L',
                        'original_price' => 80, 'discounted_price' => 70, 'quantity_purchased' => 1,
                    ]])['response'],
                ],
            ],
        ]);

        DB::table('marketplace_orders')->insert([
            $this->excelOrderRow($user->id, $matchedSn),
            $this->excelOrderRow($user->id, $mismatchSn, [
                'parent_sku' => 'SKU-B',
                'product_name' => 'Celana',
                'sku_reference' => 'SKU-B-L',
                'variation_name' => 'L',
                'original_price' => 80,
                'discounted_price' => 75,
                'quantity' => 1,
            ]),
            $this->excelOrderRow($user->id, $excelOnlySn, [
                'buyer_username' => 'buyer_excel',
            ]),
        ]);

        $report = $this->actingAs($user)->getJson(route('integrations.shopee-api.validate'))
            ->assertOk()
            ->json();

        $this->assertSame(['matched' => 1, 'mismatched' => 1, 'missing_in_excel' => 1, 'missing_in_api' => 1], $report['summary']['orders']);

        $mismatch = collect($report['orders'])->firstWhere('key', $mismatchSn);
        $this->assertSame('mismatched', $mismatch['status']);
        $this->assertNotEmpty($mismatch['differences']);
        $this->assertSame('order_status', $mismatch['differences'][0]['field']);
    }

    public function test_validate_detects_line_identity_drift(): void
    {
        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [[
                'order_sn' => $orderSn,
                'response' => $this->escrowEnvelope($orderSn, [[
                    'item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'model_name' => 'M',
                    'original_price' => 50, 'discounted_price' => 45, 'quantity_purchased' => 3,
                ]])['response'],
            ]],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn, ['quantity' => 2]));

        $report = $this->actingAs($user)->getJson(route('integrations.shopee-api.validate'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $report['summary']['lines']['missing_in_excel']);
        $this->assertSame(1, $report['summary']['lines']['missing_in_api']);
        $this->assertSame(2, $report['summary']['lines']['line_identity_changed']);

        $apiLine = collect($report['lines'])->firstWhere('status', 'missing_in_excel');
        $this->assertTrue($apiLine['line_identity_changed']);
        $this->assertNotEmpty($apiLine['changed_from_identity']);
    }

    public function test_validate_is_read_only(): void
    {
        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [[
                'order_sn' => $orderSn,
                'response' => $this->escrowEnvelope($orderSn, [[
                    'item_sku' => 'SKU-A', 'item_name' => 'Kemeja', 'model_sku' => 'SKU-A-M', 'model_name' => 'M',
                    'original_price' => 50, 'discounted_price' => 45, 'quantity_purchased' => 2,
                ]])['response'],
            ]],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        $this->actingAs($user)->getJson(route('integrations.shopee-api.validate'))->assertOk();

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('marketplace_income', 0);
        $this->assertDatabaseCount('order_cost_allocations', 0);
        $this->assertDatabaseCount('shopee_api_connections', 1);
    }

    public function test_validate_returns_422_without_connection(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();

        $this->actingAs($user)->getJson(route('integrations.shopee-api.validate'))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_validate_sources_are_api_side_only_for_authenticated_user(): void
    {
        $userA = $this->activeUser();
        $userB = $this->activeUser();
        $orderSnA = '220404NF3CFFNY';
        $orderSnB = '220404AB12CDEF';

        $this->connectedConnection($userA, [
            'staging_orders' => [$this->orderStaging($orderSnA)],
        ]);
        $this->connectedConnection($userB, [
            'staging_orders' => [$this->orderStaging($orderSnB)],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($userB->id, $orderSnB));

        $this->actingAs($userB)->getJson(route('integrations.shopee-api.validate'))
            ->assertOk()
            ->assertJsonPath('sources.api.orders', 1)
            ->assertJsonPath('sources.excel.orders', 1)
            ->assertJsonPath('summary.orders.matched', 1);
    }
}
