<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\AccountAuditLog;
use App\Models\MasterProduct;
use App\Models\ShopeeApiConnection;
use App\Models\ShopeeProductMapping;
use App\Models\User;
use App\Services\MasterProductCatalogService;
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

class ShopeeApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER_ID = 'HARDEN_PARTNER_ID_1';

    private const PARTNER_KEY = 'HARDEN_PARTNER_KEY_SECRET_1';

    private const SHOP_ID = 'HARDEN_SHOP_ID_1';

    private const ACCESS_TOKEN = 'HARDEN_ACCESS_TOKEN_SECRET_1';

    private const REFRESH_TOKEN = 'HARDEN_REFRESH_TOKEN_SECRET_1';

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
        return ['request_id' => 'req-4l', 'error' => '-', 'message' => '-'];
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

    private function orderRow(string $orderSn, array $overrides = []): array
    {
        return array_merge([
            'order_sn' => $orderSn,
            'order_status' => 'COMPLETED',
            'payment_method' => 'PAY_PROFILE',
            'package_list' => [['shipping_carrier' => 'J&T']],
            'buyer_username' => 'buyer_one',
            'create_time' => 1700000000,
            'pay_time' => 1700000100,
            'pickup_done_time' => 1700000200,
        ], $overrides);
    }

    private function escrowEntry(string $orderSn, array $items): array
    {
        return [
            'order_sn' => $orderSn,
            'response' => [
                'order_income' => [
                    'order_sn' => $orderSn,
                    'escrow_amount' => 100,
                    'buyer_total_amount' => 112,
                    'items' => $items,
                ],
            ],
        ];
    }

    private function matchedItem(string $orderSn, array $overrides = []): array
    {
        return array_merge([
            'item_sku' => 'SKU-A',
            'item_name' => 'Kemeja',
            'model_sku' => 'SKU-A-M',
            'model_name' => 'M',
            'original_price' => 50,
            'discounted_price' => 45,
            'quantity_purchased' => 2,
        ], $overrides);
    }

    private function syncService(ShopeeApiConnection $connection, ?callable $sleeper = null): ShopeeSyncService
    {
        return new ShopeeSyncService(
            ShopeeApiClient::fromConnection($connection),
            app(ShopeeOAuthService::class),
            app(ShopeeResponseNormalizer::class),
            $sleeper,
        );
    }

    private function excelOrderRow(int $userId, string $orderSn, array $overrides = []): array
    {
        $productName = strtolower(trim((string) ($overrides['product_name'] ?? 'Kemeja')));
        $variationName = $overrides['variation_name'] ?? 'M';
        $productKey = hash('sha256', $productName);
        $variationKey = $variationName === null ? null : hash('sha256', strtolower(trim($variationName)));
        $discountedPrice = (float) ($overrides['discounted_price'] ?? 45);
        $quantity = (int) ($overrides['quantity'] ?? 2);

        return array_merge([
            'user_id' => $userId,
            'order_number' => $orderSn,
            'line_identity' => ReportLineIdentity::make($orderSn, $productKey, $variationKey, $discountedPrice, $quantity),
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

    private function catalog(): MasterProductCatalogService
    {
        return app(MasterProductCatalogService::class);
    }

    private function seedMappedProduct(User $user): MasterProduct
    {
        $product = $this->catalog()->registerTemplateItem($user->id, [
            'template_item_code' => 'PRD-KEMEJA',
            'template_name' => 'Kemeja',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 10000,
                'effective_from' => '2023-01-01 00:00:00',
            ]],
        ]);

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $product->id,
            'master_unit_id' => $product->baseUnit->id,
            'shopee_product_id' => 'SKU-A',
            'shopee_variant_id' => 'SKU-A-M',
            'shopee_product_name' => 'Kemeja',
            'shopee_variant_name' => 'M',
            'normalized_shopee_name' => mb_strtolower(trim('Kemeja'.' '.'M')),
            'match_method' => 'exact',
            'match_confidence' => 1.0,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        return $product;
    }

    // ---------------------------------------------------------------
    // Incremental sync merges into existing staging (no data loss)
    // ---------------------------------------------------------------

    public function test_incremental_order_sync_merges_into_existing_staging_without_duplicates(): void
    {
        $user = $this->activeUser();
        $existingSn = '220404NF3CFFNY';
        $newSn = '220404AB12CDEF';

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow($existingSn)],
            'last_sync_order_cursor' => 'cursor-resume',
        ]);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                $this->orderRow($existingSn),
                $this->orderRow($newSn),
            ])),
        ]);

        $result = $this->syncService($connection)->syncSampleOrders($connection);

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['order_count']);
        $this->assertSame(2, $result['staged_total']);

        $connection->refresh();
        $serialNumbers = collect($connection->staging_orders)->pluck('order_sn')->all();
        sort($serialNumbers);
        $this->assertSame(['220404AB12CDEF', '220404NF3CFFNY'], $serialNumbers);
        $this->assertNotNull($connection->last_staged_at);
    }

    public function test_partial_order_sync_failure_keeps_previously_staged_rows(): void
    {
        $user = $this->activeUser();
        $existingSn = '220404NF3CFFNY';
        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow($existingSn)],
            'last_sync_status' => 'error',
        ]);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response('', 429),
        ]);

        $result = $this->syncService($connection, fn (int $seconds): int => $seconds)->syncSampleOrders($connection);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['rate_limited']);

        $connection->refresh();
        $this->assertCount(1, $connection->staging_orders);
        $this->assertSame($existingSn, $connection->staging_orders[0]['order_sn']);
        $this->assertSame('rate_limited', $connection->last_sync_status);
        $this->assertTrue($connection->isStagingStale());
    }

    // ---------------------------------------------------------------
    // Duplicate promotion prevention + idempotency guard
    // ---------------------------------------------------------------

    public function test_re_promoting_unchanged_staging_is_skipped_without_duplicate_rows(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        $first = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertOk()
            ->json();

        $this->assertTrue($first['ok']);
        $this->assertSame(['lines' => 1, 'income' => 0], $first['promoted']);
        $this->assertSame($connection->refresh()->stagingFingerprint(), $connection->promoted_fingerprint);
        $this->assertTrue($connection->isPromotionCurrent());

        $second = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertOk()
            ->json();

        $this->assertTrue($second['ok']);
        $this->assertTrue($second['skipped'] ?? false);
        $this->assertNull($second['promoted']);

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('order_cost_allocations', 1);

        $skippedAudit = AccountAuditLog::query()->where('action', 'shopee_api.promotion.skipped')->first();
        $this->assertNotNull($skippedAudit);
        $this->assertSame($user->id, $skippedAudit->user_id);
    }

    public function test_changed_staging_after_promotion_re_promotes_and_cleans_removed_line(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $connection->refresh();
        $this->assertCount(1, $connection->staging_escrow);
        $this->assertNotNull($connection->promoted_fingerprint);

        // Change staging to add a second line.
        $connection->staging_escrow = [$this->escrowEntry($orderSn, [
            $this->matchedItem($orderSn),
            $this->matchedItem($orderSn, [
                'item_sku' => 'SKU-B', 'item_name' => 'Celana', 'model_sku' => 'SKU-B-L', 'model_name' => 'L',
                'original_price' => 80, 'discounted_price' => 70, 'quantity_purchased' => 1,
            ]),
        ])];
        $connection->save();

        $this->assertFalse($connection->refresh()->isPromotionCurrent());

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $this->assertDatabaseCount('marketplace_orders', 2);
        $this->assertDatabaseHas('marketplace_orders', ['order_number' => $orderSn]);
    }

    // ---------------------------------------------------------------
    // Stale staging blocks promotion until a fresh sync
    // ---------------------------------------------------------------

    public function test_stale_staging_blocks_promotion_with_zero_writes(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'last_staged_at' => now()->subMinutes(10),
            'last_sync_at' => now()->subMinutes(10),
            'last_sync_status' => 'rate_limited',
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertStatus(422)
            ->json();

        $this->assertFalse($response['ok']);
        $this->assertStringContainsString('stale', $response['error']);
        $this->assertNull($response['promoted']);

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('order_cost_allocations', 0);

        $blockedAudit = AccountAuditLog::query()->where('action', 'shopee_api.promotion.blocked')->first();
        $this->assertNotNull($blockedAudit);
        $this->assertStringContainsString('stale', $blockedAudit->metadata['reason']);
    }

    public function test_fresh_successful_sync_clears_stale_flag_and_unblocks_promotion(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'last_staged_at' => now()->subDays(2),
            'last_promoted_at' => now()->subDays(3),
            'last_sync_at' => now()->subDays(2),
            'last_sync_status' => 'success',
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        $this->assertTrue($connection->refresh()->isStagingStale());

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                $this->orderRow($orderSn),
            ])),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-orders'), ['page_size' => 5])->assertOk();

        $connection->refresh();
        $this->assertFalse($connection->isStagingStale());

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertOk()
            ->json();

        $this->assertTrue($response['ok']);
        $this->assertSame(['lines' => 1, 'income' => 0], $response['promoted']);
    }

    // ---------------------------------------------------------------
    // Audit trail for every sync outcome
    // ---------------------------------------------------------------

    public function test_every_sync_operation_is_audited_without_secrets(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $this->connectedConnection($user);

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->orderEnvelope([
                $this->orderRow('220404NF3CFFNY'),
            ])),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-orders'), ['page_size' => 5])->assertOk();

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.sync.orders')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertTrue($audit->metadata['ok']);
        $this->assertSame(1, $audit->metadata['order_count']);

        $serialized = json_encode($audit->metadata);
        foreach ([self::PARTNER_KEY, self::ACCESS_TOKEN, self::REFRESH_TOKEN] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $serialized);
        }
    }

    public function test_failed_sync_is_audited_as_failure(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $this->connectedConnection($user);

        Http::fake([
            '*/api/v2/payment/get_income_detail*' => Http::sequence()
                ->push('', 429)
                ->push('', 429)
                ->push('', 429),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.sync-income'))
            ->assertOk()
            ->assertJson(['ok' => false, 'rate_limited' => true]);

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.sync.income')->first();
        $this->assertNotNull($audit);
        $this->assertFalse($audit->metadata['ok']);
        $this->assertTrue($audit->metadata['rate_limited']);
        $this->assertNotNull($audit->metadata['error']);
    }

    // ---------------------------------------------------------------
    // Clear endpoint wipes staging safely + tenant scoped
    // ---------------------------------------------------------------

    public function test_clear_removes_staging_and_resets_sync_tracking(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow('220404NF3CFFNY')],
            'last_sync_order_cursor' => 'cursor-x',
            'last_sync_status' => 'success',
            'last_staged_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.clear'))
            ->assertOk()
            ->json();

        $this->assertTrue($response['cleared']);
        $this->assertTrue($response['staged']);

        $connection->refresh();
        $this->assertNull($connection->staging_orders);
        $this->assertNull($connection->staging_income);
        $this->assertNull($connection->staging_escrow);
        $this->assertNull($connection->last_staged_at);
        $this->assertNull($connection->last_sync_order_cursor);
        $this->assertNull($connection->last_sync_status);
        $this->assertFalse($connection->hasStagedData());

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.sync.clear')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);

        $this->assertDatabaseCount('marketplace_orders', 0);
    }

    public function test_clear_is_tenant_scoped(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $other = $this->activeUser();
        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderRow('220404NF3CFFNY')],
        ]);
        $otherConnection = $this->connectedConnection($other, [
            'staging_orders' => [$this->orderRow('220404AB12CDEF')],
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.clear'))->assertOk();

        $userConnection = ShopeeApiConnection::forUser($user->id)->first();
        $this->assertNull($userConnection->staging_orders);

        $otherConnection->refresh();
        $this->assertCount(1, $otherConnection->staging_orders);
        $this->assertSame('220404AB12CDEF', $otherConnection->staging_orders[0]['order_sn']);
    }
}
