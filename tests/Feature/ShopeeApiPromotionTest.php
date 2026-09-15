<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\AccountAuditLog;
use App\Models\MasterProduct;
use App\Models\OrderCostAllocation;
use App\Models\ShopeeApiConnection;
use App\Models\ShopeeProductMapping;
use App\Models\User;
use App\Services\MarketplaceReconciliationService;
use App\Services\MasterProductCatalogService;
use App\Services\ReportLineIdentity;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopeeApiPromotionTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER_ID = 'PROMO_PARTNER_ID_1';

    private const PARTNER_KEY = 'PROMO_PARTNER_KEY_SECRET_1';

    private const SHOP_ID = 'PROMO_SHOP_ID_1';

    private const ACCESS_TOKEN = 'PROMO_ACCESS_TOKEN_SECRET_1';

    private const REFRESH_TOKEN = 'PROMO_REFRESH_TOKEN_SECRET_1';

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

    private function orderStaging(string $orderSn, array $overrides = []): array
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

    private function incomeStaging(string $orderSn, array $overrides = []): array
    {
        return array_merge([
            'order_sn' => $orderSn,
            'description' => 'Total Income',
            'status' => 'Released',
            'payment_method' => 'PAY_PROFILE',
            'currency' => 'IDR',
            'total_income' => 145000,
            'release_time' => 1700001000,
        ], $overrides);
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

    private function catalog(): MasterProductCatalogService
    {
        return app(MasterProductCatalogService::class);
    }

    private function registerProduct(User $user, string $code, string $name, ?int $hppAmount = 10000, string $effectiveFrom = '2023-01-01 00:00:00'): MasterProduct
    {
        $unit = [
            'unit_code' => 'PCS',
            'unit_name' => 'PCS',
            'conversion_to_base' => 1,
        ];

        if ($hppAmount !== null) {
            $unit['hpp_amount'] = $hppAmount;
            $unit['effective_from'] = $effectiveFrom;
        }

        return $this->catalog()->registerTemplateItem($user->id, [
            'template_item_code' => $code,
            'template_name' => $name,
            'units' => [$unit],
        ]);
    }

    private function createMapping(User $user, MasterProduct $product, string $method, ?string $sku, ?string $skuRef, string $productName, ?string $variantName): ShopeeProductMapping
    {
        return ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $product->id,
            'master_unit_id' => $product->baseUnit->id,
            'shopee_product_id' => $sku,
            'shopee_variant_id' => $skuRef,
            'shopee_product_name' => $productName,
            'shopee_variant_name' => $variantName,
            'normalized_shopee_name' => mb_strtolower(trim($productName.' '.($variantName ?? ''))),
            'match_method' => $method,
            'match_confidence' => 1.0,
            'is_active' => true,
            'ambiguous' => false,
        ]);
    }

    private function seedMappedProduct(User $user, int $hppAmount = 10000): MasterProduct
    {
        $product = $this->registerProduct($user, 'PRD-KEMEJA', 'Kemeja', $hppAmount);
        $this->createMapping($user, $product, 'exact', 'SKU-A', 'SKU-A-M', 'Kemeja', 'M');

        return $product;
    }

    // ---------------------------------------------------------------
    // Happy path + reuse of production contracts
    // ---------------------------------------------------------------

    public function test_promote_writes_validated_lines_income_and_allocations(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'staging_income' => [$this->incomeStaging($orderSn)],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));
        DB::table('marketplace_income')->insert($this->excelIncomeRow($user->id, $orderSn));

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'), ['dry_run' => false])
            ->assertOk()
            ->json();

        $this->assertTrue($response['ok']);
        $this->assertFalse($response['dry_run']);
        $this->assertTrue($response['gate']['passed']);
        $this->assertSame(['lines' => 1, 'income' => 1], $response['promoted']);

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('marketplace_income', 1);
        $this->assertDatabaseCount('order_cost_allocations', 1);

        $order = DB::table('marketplace_orders')->where('order_number', $orderSn)->first();
        $this->assertSame('Kemeja', $order->product_name);
        $this->assertSame(45.0, (float) $order->unit_price);
        $this->assertSame(2, (int) $order->quantity);
        $this->assertSame('COMPLETED', $order->order_status);
        $this->assertNotEmpty($order->line_identity);

        $allocation = OrderCostAllocation::query()->where('user_id', $user->id)->first();
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame(20000.0, (float) $allocation->total_hpp);
        $this->assertSame($order->line_identity, $allocation->order_line_identity);

        $income = DB::table('marketplace_income')->where('order_number', $orderSn)->first();
        $this->assertSame(145000.0, (float) $income->total_income);

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.promotion')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('passed', $audit->metadata['gate']);
        $this->assertSame(['lines' => 1, 'income' => 1], $audit->metadata['promoted']);
    }

    public function test_promote_preview_dump_never_persists(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'staging_income' => [$this->incomeStaging($orderSn)],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));
        DB::table('marketplace_income')->insert($this->excelIncomeRow($user->id, $orderSn));

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'), ['dry_run' => true])
            ->assertOk()
            ->json();

        $this->assertTrue($response['ok']);
        $this->assertTrue($response['dry_run']);
        $this->assertSame(['lines' => 1, 'income' => 1], $response['scheduled']);
        $this->assertNull($response['promoted']);

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('marketplace_income', 1);
        $this->assertDatabaseCount('order_cost_allocations', 0);

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.promotion.preview')->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->metadata['dry_run']);
        $this->assertSame('passed', $audit->metadata['gate']);
    }

    public function test_promote_is_idempotent_and_never_duplicates_rows(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'staging_income' => [$this->incomeStaging($orderSn)],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));
        DB::table('marketplace_income')->insert($this->excelIncomeRow($user->id, $orderSn));

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();
        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('marketplace_income', 1);
        $this->assertDatabaseCount('order_cost_allocations', 1);
        $this->assertDatabaseCount('account_audit_logs', 2);
    }

    public function test_removed_line_cleans_stale_allocation_on_reimport(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);
        $this->createMapping($user, $this->registerProduct($user, 'PRD-CELANA', 'Celana'), 'exact', 'SKU-B', 'SKU-B-L', 'Celana', 'L');

        $twoLines = [
            $this->matchedItem($orderSn),
            $this->matchedItem($orderSn, [
                'item_sku' => 'SKU-B', 'item_name' => 'Celana', 'model_sku' => 'SKU-B-L', 'model_name' => 'L',
                'original_price' => 80, 'discounted_price' => 70, 'quantity_purchased' => 1,
            ]),
        ];

        $remainingIdentity = ReportLineIdentity::make(
            $orderSn,
            hash('sha256', strtolower('Kemeja')),
            hash('sha256', 'm'),
            45.0,
            2,
        );

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, $twoLines)],
        ]);

        DB::table('marketplace_orders')->insert([
            $this->excelOrderRow($user->id, $orderSn),
            $this->excelOrderRow($user->id, $orderSn, [
                'parent_sku' => 'SKU-B',
                'product_name' => 'Celana',
                'sku_reference' => 'SKU-B-L',
                'variation_name' => 'L',
                'original_price' => 80,
                'discounted_price' => 70,
                'quantity' => 1,
            ]),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();
        $this->assertDatabaseCount('marketplace_orders', 2);
        $this->assertSame(2, OrderCostAllocation::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('marketplace_orders', ['order_number' => $orderSn, 'line_identity' => $remainingIdentity]);

        $connection->refresh();
        $connection->staging_escrow = [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])];
        $connection->save();

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseHas('marketplace_orders', ['order_number' => $orderSn, 'line_identity' => $remainingIdentity]);

        $allocations = OrderCostAllocation::query()->where('user_id', $user->id)->get();
        $this->assertSame(1, $allocations->count());
        $this->assertSame($remainingIdentity, $allocations->first()->order_line_identity);
    }

    // ---------------------------------------------------------------
    // Gate / rollback / tenancy
    // ---------------------------------------------------------------

    public function test_validation_mismatch_blocks_promotion_with_zero_writes(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn, ['order_status' => 'SHIPPED'])],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertStatus(422)
            ->json();

        $this->assertFalse($response['ok']);
        $this->assertFalse($response['gate']['passed']);
        $this->assertStringContainsString('mismatched', $response['error']);
        $this->assertNull($response['promoted']);

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('marketplace_income', 0);
        $this->assertDatabaseCount('order_cost_allocations', 0);

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.promotion.blocked')->first();
        $this->assertNotNull($audit);
        $this->assertSame('blocked', $audit->metadata['gate']);
    }

    public function test_transaction_rolls_back_all_writes_on_partial_failure(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'staging_income' => [
                $this->incomeStaging($orderSn, ['total_income' => 100]),
                $this->incomeStaging($orderSn, ['total_income' => 100, 'description' => 'Second row']),
            ],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));
        DB::table('marketplace_income')->insert([
            $this->excelIncomeRow($user->id, $orderSn, ['total_income' => 100, 'row_type' => 'Total Income']),
            $this->excelIncomeRow($user->id, $orderSn, ['total_income' => 100, 'row_type' => 'Second row']),
        ]);

        $response = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertStatus(422)
            ->json();

        $this->assertFalse($response['ok']);
        $this->assertStringContainsString('Promotion failed', $response['error']);

        $this->assertDatabaseCount('marketplace_orders', 1);
        $this->assertDatabaseCount('marketplace_income', 2);
        $this->assertDatabaseCount('order_cost_allocations', 0);

        $audit = AccountAuditLog::query()->where('action', 'shopee_api.promotion.failed')->first();
        $this->assertNotNull($audit);
    }

    public function test_promotion_is_tenant_scoped(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $userA = $this->activeUser();
        $userB = $this->activeUser();
        $orderA = '220404NF3CFFNY';
        $orderB = '220404AB12CDEF';

        $this->connectedConnection($userA, [
            'staging_orders' => [$this->orderStaging($orderA)],
            'staging_escrow' => [$this->escrowEntry($orderA, [$this->matchedItem($orderA)])],
        ]);
        $this->connectedConnection($userB, [
            'partner_id' => 'PROMO_PARTNER_ID_2',
            'partner_key' => 'PROMO_PARTNER_KEY_SECRET_2',
            'shop_id' => 'PROMO_SHOP_ID_2',
            'access_token' => 'PROMO_ACCESS_TOKEN_SECRET_2',
            'staging_orders' => [$this->orderStaging($orderB)],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($userB->id, $orderB));

        $this->actingAs($userA)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $this->assertDatabaseHas('marketplace_orders', ['order_number' => $orderA, 'user_id' => $userA->id]);
        $this->assertDatabaseMissing('marketplace_orders', ['order_number' => $orderB, 'user_id' => $userA->id]);
        $this->assertDatabaseHas('marketplace_orders', ['order_number' => $orderB, 'user_id' => $userB->id]);

        $audit = AccountAuditLog::query()->where('user_id', $userA->id)->get();
        $this->assertTrue($audit->every(fn (AccountAuditLog $log): bool => $log->user_id === $userA->id));
    }

    // ---------------------------------------------------------------
    // HPP / returns / refunds / secrets
    // ---------------------------------------------------------------

    public function test_missing_hpp_is_distinct_from_zero_hpp(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $missingSn = '220404NF3CFFNY';
        $zeroSn = '220404AB12CDEF';

        $missingProduct = $this->registerProduct($user, 'PRD-NOHPP', 'Tanpa HPP', null);
        $this->createMapping($user, $missingProduct, 'exact', 'SKU-NOHPP', 'SKU-NOHPP-V', 'Tanpa HPP', 'M');
        $this->seedMappedProduct($user, 0);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($missingSn), $this->orderStaging($zeroSn)],
            'staging_escrow' => [
                $this->escrowEntry($missingSn, [$this->matchedItem($missingSn, ['item_sku' => 'SKU-NOHPP', 'item_name' => 'Tanpa HPP', 'model_sku' => 'SKU-NOHPP-V'])]),
                $this->escrowEntry($zeroSn, [$this->matchedItem($zeroSn)]),
            ],
        ]);

        DB::table('marketplace_orders')->insert([
            $this->excelOrderRow($user->id, $missingSn, ['parent_sku' => 'SKU-NOHPP', 'product_name' => 'Tanpa HPP', 'sku_reference' => 'SKU-NOHPP-V']),
            $this->excelOrderRow($user->id, $zeroSn),
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $missingIdentity = ReportLineIdentity::make($missingSn, hash('sha256', strtolower('Tanpa HPP')), hash('sha256', 'm'), 45.0, 2);
        $zeroIdentity = ReportLineIdentity::make($zeroSn, hash('sha256', strtolower('Kemeja')), hash('sha256', 'm'), 45.0, 2);

        $missingAllocation = OrderCostAllocation::query()->where('user_id', $user->id)
            ->where('order_line_identity', $missingIdentity)
            ->first();
        $this->assertNotNull($missingAllocation);
        $this->assertSame('hpp_missing', $missingAllocation->cost_status);
        $this->assertNull($missingAllocation->effective_hpp_record_id);

        $zeroAllocation = OrderCostAllocation::query()->where('user_id', $user->id)
            ->where('order_line_identity', $zeroIdentity)
            ->first();
        $this->assertNotNull($zeroAllocation);
        $this->assertSame('ok', $zeroAllocation->cost_status);
        $this->assertSame(0.0, (float) $zeroAllocation->total_hpp);
        $this->assertNotNull($zeroAllocation->effective_hpp_record_id);
    }

    public function test_returned_quantity_and_refund_income_are_preserved(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user, 10000);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [
                $this->matchedItem($orderSn, ['quantity_purchased' => 5, 'quantity_returned' => 2]),
            ])],
            'staging_income' => [$this->incomeStaging($orderSn, ['total_income' => -35000, 'description' => 'Refund'])],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn, ['quantity' => 5]));
        DB::table('marketplace_income')->insert($this->excelIncomeRow($user->id, $orderSn, ['total_income' => -35000, 'row_type' => 'Refund']));

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $order = DB::table('marketplace_orders')->where('order_number', $orderSn)->first();
        $this->assertSame(5, (int) $order->quantity);
        $this->assertSame(2, (int) $order->returned_quantity);

        $allocation = OrderCostAllocation::query()->where('user_id', $user->id)->first();
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame(3.0, (float) $allocation->quantity_base_unit);
        $this->assertSame(30000.0, (float) $allocation->total_hpp);

        $income = DB::table('marketplace_income')->where('order_number', $orderSn)->first();
        $this->assertSame('Refund', $income->row_type);
        $this->assertEquals(-35000.0, (float) $income->total_income);
    }

    public function test_promotion_is_offline_and_uses_staged_snapshot_without_api_calls(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $connection = $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));

        Http::fake();

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        Http::assertNothingSent();
        $connection->refresh();
        $this->assertCount(1, $connection->staging_orders);
        $this->assertCount(1, $connection->staging_escrow);
    }

    public function test_promotion_responses_and_audit_never_leak_credentials(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn)],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'staging_income' => [$this->incomeStaging($orderSn)],
        ]);

        DB::table('marketplace_orders')->insert($this->excelOrderRow($user->id, $orderSn));
        DB::table('marketplace_income')->insert($this->excelIncomeRow($user->id, $orderSn));

        $content = $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertOk()
            ->getContent();

        foreach ([self::PARTNER_KEY, self::ACCESS_TOKEN, self::REFRESH_TOKEN] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }

        $auditMetadata = AccountAuditLog::query()->where('action', 'shopee_api.promotion')->first()->metadata;
        $serialized = json_encode($auditMetadata);

        foreach ([self::PARTNER_KEY, self::ACCESS_TOKEN, self::REFRESH_TOKEN] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $serialized);
        }

        $rows = DB::table('marketplace_orders')->where('user_id', $user->id)->get();
        foreach ($rows as $row) {
            $this->assertStringNotContainsString(self::PARTNER_KEY, $row->raw_data);
            $this->assertStringNotContainsString(self::ACCESS_TOKEN, $row->raw_data);
        }
    }

    public function test_promote_requires_connection_and_staged_data(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->connectedConnection($user);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseCount('marketplace_orders', 0);
        $this->assertDatabaseCount('account_audit_logs', 0);
    }

    // ---------------------------------------------------------------
    // P1 remediation: tracking_number + income identity
    // ---------------------------------------------------------------

    public function test_promote_persists_tracking_number_from_package_list(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn, [
                'package_list' => [
                    ['shipping_carrier' => 'J&T', 'tracking_number' => ' TRACK-A1 '],
                ],
            ])],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $order = DB::table('marketplace_orders')->where('order_number', $orderSn)->first();
        $this->assertSame('TRACK-A1', $order->tracking_number);
        $this->assertSame('J&T', $order->shipping_option);
    }

    public function test_promote_classifies_api_income_as_orphan_and_settles_against_linked_income(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = $this->activeUser();
        $orderSn = '220404NF3CFFNY';
        $this->seedMappedProduct($user);

        $this->connectedConnection($user, [
            'staging_orders' => [$this->orderStaging($orderSn, [
                'package_list' => [
                    ['shipping_carrier' => 'J&T', 'tracking_number' => 'TRACKING-B2'],
                ],
            ])],
            'staging_escrow' => [$this->escrowEntry($orderSn, [$this->matchedItem($orderSn)])],
            'staging_income' => [$this->incomeStaging($orderSn)],
        ]);

        $this->actingAs($user)->postJson(route('integrations.shopee-api.promote'))->assertOk();

        $order = DB::table('marketplace_orders')->where('order_number', $orderSn)->first();
        $this->assertSame('TRACKING-B2', $order->tracking_number);
        $this->assertNotEmpty($order->line_identity);

        $apiIncome = DB::table('marketplace_income')
            ->where('order_number', $orderSn)
            ->whereNull('product_key')
            ->first();
        $this->assertNotNull($apiIncome);
        $this->assertNull($apiIncome->item_index);
        $this->assertNull($apiIncome->unit_price);
        $this->assertNull($apiIncome->quantity);
        $this->assertNotNull($apiIncome->line_identity);
        $this->assertNotSame($order->line_identity, $apiIncome->line_identity);

        DB::table('marketplace_income')->insert([
            'user_id' => $user->id,
            'order_number' => $orderSn,
            'item_index' => $order->item_index,
            'line_identity' => $order->line_identity,
            'row_type' => 'Total Revenue',
            'product_key' => $order->product_key,
            'variation_key' => $order->variation_key,
            'product_name' => $order->product_name,
            'unit_price' => $order->unit_price,
            'product_price' => $order->discounted_price,
            'quantity' => $order->quantity,
            'total_income' => 145000.0,
            'raw_data' => json_encode(['source' => 'linked_income'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recon = new MarketplaceReconciliationService($user->id);
        $row = $recon->joinedQuery($user->id)->where('orders.order_number', $orderSn)->first();

        $this->assertNotNull($row);
        $this->assertSame('Settled', $row->business_status);
        $this->assertSame('Exact', $row->match_method);
        $this->assertSame('Settled', $row->settlement_status);
    }
}
