<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\OrderCostAllocation;
use App\Models\User;
use App\Services\MarketplaceReconciliationService;
use App\Services\MasterProductCatalogService;
use App\Services\OrderCostAllocationService;
use App\Services\ReportLineIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitMarginReportTest extends TestCase
{
    use RefreshDatabase;

    protected MasterProductCatalogService $catalog;

    protected OrderCostAllocationService $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = app(MasterProductCatalogService::class);
        $this->allocation = app(OrderCostAllocationService::class);
    }

    public function test_normal_mapped_order_reports_revenue_hpp_profit_and_margin(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0001', 'Produk Normal', 10000);

        $lineIdentity = ReportLineIdentity::make('ORDER-NORMAL', str_repeat('n', 64), null, 500.0, 2);
        $orderNumber = 'ORDER-NORMAL';
        $this->insertOrder($user, [
            'order_number' => $orderNumber,
            'product_key' => str_repeat('n', 64),
            'discounted_price' => 500,
            'unit_price' => 500,
            'quantity' => 2,
            'line_identity' => $lineIdentity,
        ]);
        $this->allocation->allocate($user->id, $lineIdentity, $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 2);

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame(1000.0, $row->order_subtotal);
        $this->assertSame(1000.0, $row->penghasilan);
        $this->assertSame(20000.0, $row->hpp);
        $this->assertSame(-19000.0, $row->laba);
        $this->assertSame('ok', $row->hpp_status);

        $stats = app(MarketplaceReconciliationService::class)->dashboardStats($user->id);

        $this->assertSame(1000.0, $stats['net_sales']);
        $this->assertSame(20000.0, $stats['total_hpp']);
        $this->assertSame(1000.0, $stats['gross_profit']);
        $this->assertSame(-19000.0, $stats['net_profit']);
        $this->assertSame(-1900.0, $stats['net_margin']);
    }

    public function test_valid_zero_hpp_is_reported_as_ok_not_as_data_quality_issue(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0002', 'Produk Gratis', 0);

        $lineIdentity = ReportLineIdentity::make('ORDER-ZERO-HPP', str_repeat('z', 64), null, 100.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-ZERO-HPP',
            'product_key' => str_repeat('z', 64),
            'quantity' => 1,
            'line_identity' => $lineIdentity,
        ]);
        $this->allocation->allocate($user->id, $lineIdentity, $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 1);

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame(0.0, $row->hpp);
        $this->assertSame('ok', $row->hpp_status);
        $this->assertSame(0.0, app(MarketplaceReconciliationService::class)->dashboardStats($user->id)['total_hpp']);
    }

    public function test_mapping_missing_allocation_is_not_reported_as_zero_cost(): void
    {
        $user = User::factory()->create();

        $lineIdentity = ReportLineIdentity::make('ORDER-MAPPING-MISSING', str_repeat('m', 64), null, 100.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-MAPPING-MISSING',
            'product_key' => str_repeat('m', 64),
            'quantity' => 1,
            'line_identity' => $lineIdentity,
        ]);
        $this->insertMissingAllocation($user, $lineIdentity, 'mapping_missing');

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame('mapping_missing', $row->hpp_status);
        $this->assertSame(0.0, $row->hpp);
        $this->assertSame(100.0, $row->penghasilan);
        $this->assertSame('mapping_missing', $row->cost_status);
        $this->assertSame(0.0, app(MarketplaceReconciliationService::class)->dashboardStats($user->id)['total_hpp']);
    }

    public function test_mapping_ambiguous_allocation_is_not_reported_as_zero_cost(): void
    {
        $user = User::factory()->create();

        $lineIdentity = ReportLineIdentity::make('ORDER-MAPPING-AMBIGUOUS', str_repeat('a', 64), null, 100.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-MAPPING-AMBIGUOUS',
            'product_key' => str_repeat('a', 64),
            'quantity' => 1,
            'line_identity' => $lineIdentity,
        ]);
        $this->insertMissingAllocation($user, $lineIdentity, 'mapping_ambiguous');

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame('mapping_ambiguous', $row->hpp_status);
        $this->assertSame(0.0, $row->hpp);
        $this->assertSame('mapping_ambiguous', $row->cost_status);
        $this->assertSame(0.0, app(MarketplaceReconciliationService::class)->dashboardStats($user->id)['total_hpp']);
    }

    public function test_hpp_missing_allocation_is_not_reported_as_zero_cost(): void
    {
        $user = User::factory()->create();

        $lineIdentity = ReportLineIdentity::make('ORDER-HPP-MISSING', str_repeat('h', 64), null, 100.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-HPP-MISSING',
            'product_key' => str_repeat('h', 64),
            'quantity' => 1,
            'line_identity' => $lineIdentity,
        ]);
        $this->insertMissingAllocation($user, $lineIdentity, 'hpp_missing');

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame('hpp_missing', $row->hpp_status);
        $this->assertSame(0.0, $row->hpp);
        $this->assertSame('hpp_missing', $row->cost_status);
        $this->assertSame(0.0, app(MarketplaceReconciliationService::class)->dashboardStats($user->id)['total_hpp']);
    }

    public function test_row_without_allocation_is_flagged_as_no_allocation(): void
    {
        $user = User::factory()->create();

        $lineIdentity = ReportLineIdentity::make('ORDER-NO-ALLOC', str_repeat('x', 64), null, 100.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-NO-ALLOC',
            'product_key' => str_repeat('x', 64),
            'quantity' => 1,
            'line_identity' => $lineIdentity,
        ]);

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame('no_allocation', $row->hpp_status);
        $this->assertSame(0.0, $row->hpp);
    }

    public function test_report_uses_historical_hpp_from_allocation_not_latest_version(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0003', 'Produk Historis', 10000, '2026-08-01 00:00:00');
        $unitId = $product->baseUnit->id;
        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 20000,
            'effective_from' => '2026-08-15 00:00:00',
            'source_type' => 'template',
        ]);
        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 30000,
            'effective_from' => '2026-09-01 00:00:00',
            'source_type' => 'template',
        ]);

        $oldRecord = $this->catalog->resolveEffectiveHpp($user->id, $product->id, $unitId, CarbonImmutable::parse('2026-08-05 12:00:00'));
        $this->assertSame('10000.00', (string) $oldRecord->hpp_amount);

        $lineIdentity = ReportLineIdentity::make('ORDER-HISTORICAL', str_repeat('t', 64), null, 100.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-HISTORICAL',
            'product_key' => str_repeat('t', 64),
            'quantity' => 1,
            'order_created_at' => '2026-08-05 10:00:00',
            'line_identity' => $lineIdentity,
        ]);
        $this->allocation->allocate($user->id, $lineIdentity, $product->id, $unitId, $oldRecord->id, 1);

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame((int) $oldRecord->id, (int) $row->effective_hpp_record_id);
        $this->assertSame(10000.0, $row->hpp);
        $this->assertSame('ok', $row->hpp_status);
        $this->assertNotSame(30000.0, $row->hpp);
    }

    public function test_multiple_order_lines_do_not_double_count_hpp(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0004', 'Produk Multi Lin', 1500);

        foreach (['A', 'B'] as $index => $variation) {
            $lineIdentity = ReportLineIdentity::make('ORDER-MULTI-LINES', str_repeat('l', 64), hash('sha256', $variation), 100.0, 1);
            $this->insertOrder($user, [
                'order_number' => 'ORDER-MULTI-LINES',
                'product_key' => str_repeat('l', 64),
                'variation_key' => hash('sha256', $variation),
                'variation_name' => $variation,
                'item_index' => 100 + $index,
                'quantity' => 1,
                'line_identity' => $lineIdentity,
            ]);
            $this->allocation->allocate($user->id, $lineIdentity, $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 1);
        }

        $rows = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id);

        $this->assertCount(2, $rows);
        $this->assertSame(3000.0, array_sum(array_map(fn ($row) => $row->hpp, $rows)));
        $this->assertSame(1, app(MarketplaceReconciliationService::class)->dashboardStats($user->id)['gross_order_count']);
    }

    public function test_multiple_orders_aggregate_hpp_without_double_counting(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0005', 'Produk Aggregasi', 1000);

        foreach (['ORD-A', 'ORD-B'] as $index => $orderNumber) {
            $variationKey = hash('sha256', $orderNumber);
            $lineIdentity = ReportLineIdentity::make($orderNumber, str_repeat('g', 64), $variationKey, 100.0, 2);
            $this->insertOrder($user, [
                'order_number' => $orderNumber,
                'product_key' => str_repeat('g', 64),
                'variation_key' => $variationKey,
                'item_index' => 200 + $index,
                'quantity' => 2,
                'line_identity' => $lineIdentity,
            ]);
            $this->allocation->allocate($user->id, $lineIdentity, $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 2);
        }

        $rows = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id);
        $stats = app(MarketplaceReconciliationService::class)->dashboardStats($user->id);

        $this->assertCount(2, $rows);
        $this->assertSame(4000.0, $stats['total_hpp']);
        $this->assertSame(2, $stats['gross_order_count']);
    }

    public function test_returned_quantity_hpp_uses_net_quantity_and_status_is_unchanged(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0006', 'Produk Retur', 10000);

        $lineIdentity = ReportLineIdentity::make('ORDER-RETURN-HPP', str_repeat('r', 64), null, 100.0, 2);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-RETURN-HPP',
            'product_key' => str_repeat('r', 64),
            'quantity' => 2,
            'returned_quantity' => 1,
            'line_identity' => $lineIdentity,
        ]);
        $this->allocation->allocate($user->id, $lineIdentity, $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 2, 1);

        $row = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id)[0];

        $this->assertSame('Returned', $row->business_status);
        $this->assertSame(1.0, $row->net_quantity);
        $this->assertSame(10000.0, $row->hpp);
        $this->assertSame('ok', $row->hpp_status);
    }

    public function test_tenant_isolation_keeps_hpp_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productA = $this->registerProduct($userA, 'IT0007', 'Produk A', 5000);
        $this->registerProduct($userB, 'IT0007', 'Produk B', 9000);

        $lineA = ReportLineIdentity::make('ORDER-TENANT', str_repeat('q', 64), null, 100.0, 1);
        $lineB = ReportLineIdentity::make('ORDER-TENANT', str_repeat('q', 64), null, 100.0, 1);

        $this->insertOrder($userA, ['order_number' => 'ORDER-TENANT', 'product_key' => str_repeat('q', 64), 'quantity' => 1, 'line_identity' => $lineA]);
        $this->insertOrder($userB, ['order_number' => 'ORDER-TENANT', 'product_key' => str_repeat('q', 64), 'quantity' => 1, 'line_identity' => $lineB]);

        $this->allocation->allocate($userA->id, $lineA, $productA->id, $productA->baseUnit->id, $productA->hppRecords()->first()->id, 1);

        $rowA = app(MarketplaceReconciliationService::class)->reconciliationRows($userA->id)[0];
        $rowB = app(MarketplaceReconciliationService::class)->reconciliationRows($userB->id)[0];

        $this->assertSame(5000.0, $rowA->hpp);
        $this->assertSame('ok', $rowA->hpp_status);
        $this->assertSame(0.0, $rowB->hpp);
        $this->assertSame('no_allocation', $rowB->hpp_status);

        $this->assertSame(5000.0, app(MarketplaceReconciliationService::class)->dashboardStats($userA->id)['total_hpp']);
        $this->assertSame(0.0, app(MarketplaceReconciliationService::class)->dashboardStats($userB->id)['total_hpp']);
    }

    public function test_revenue_zero_margin_is_safe_without_nan_or_infinity(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT0008', 'Produk Margin Aman', 5000);

        $lineIdentity = ReportLineIdentity::make('ORDER-ZERO-REVENUE', str_repeat('e', 64), null, 0.0, 1);
        $this->insertOrder($user, [
            'order_number' => 'ORDER-ZERO-REVENUE',
            'product_key' => str_repeat('e', 64),
            'quantity' => 1,
            'discounted_price' => 0,
            'unit_price' => 0,
            'line_identity' => $lineIdentity,
        ]);
        $this->allocation->allocate($user->id, $lineIdentity, $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 1);

        $stats = app(MarketplaceReconciliationService::class)->dashboardStats($user->id);

        $this->assertSame(0.0, $stats['net_sales']);
        $this->assertSame(0.0, $stats['net_margin']);
        $this->assertIsFloat($stats['net_margin']);
        $this->assertTrue(is_finite($stats['net_margin']));
        $this->assertSame(-5000.0, $stats['net_profit']);
    }

    public function test_dashboard_stats_reports_hpp_status_counts_per_data_quality(): void
    {
        $user = User::factory()->create();
        $this->seedHppStatusFixture($user);

        $stats = app(MarketplaceReconciliationService::class)->dashboardStats($user->id);

        $this->assertSame(1, $stats['hpp_ok_count']);
        $this->assertSame(1, $stats['hpp_mapping_missing_count']);
        $this->assertSame(1, $stats['hpp_mapping_ambiguous_count']);
        $this->assertSame(1, $stats['hpp_hpp_missing_count']);
        $this->assertSame(1, $stats['hpp_no_allocation_count']);
        $this->assertSame(5000.0, $stats['total_hpp']);
        $this->assertSame(5, $stats['net_order_count']);
        $this->assertSame(
            5,
            $stats['hpp_ok_count']
            + $stats['hpp_mapping_missing_count']
            + $stats['hpp_mapping_ambiguous_count']
            + $stats['hpp_hpp_missing_count']
            + $stats['hpp_no_allocation_count'],
        );
    }

    public function test_hpp_status_filter_selects_matching_rows(): void
    {
        $user = User::factory()->create();
        $this->seedHppStatusFixture($user);

        $page = app(MarketplaceReconciliationService::class)->reconciliationPage($user->id, null, null, [
            'hpp_statuses' => ['hpp_missing', 'ok'],
            'per_page' => 25,
        ]);

        $this->assertSame(3, $page->total());
        $orderNumbers = collect($page->items())->pluck('order_number')->sort()->values()->all();
        $this->assertSame(['ORDER-CANCELLED', 'ORDER-HPP-MISSING', 'ORDER-OK'], $orderNumbers);
        $this->assertSame(['hpp_missing', 'ok', 'ok'], collect($page->items())->pluck('hpp_status')->sortByDesc(fn ($status) => $status === 'hpp_missing' ? 1 : 0)->values()->all());
    }

    public function test_no_allocation_filter_matches_rows_without_cost_allocation(): void
    {
        $user = User::factory()->create();
        $this->seedHppStatusFixture($user);

        $page = app(MarketplaceReconciliationService::class)->reconciliationPage($user->id, null, null, [
            'hpp_statuses' => ['no_allocation'],
            'per_page' => 25,
        ]);

        $this->assertSame(1, $page->total());
        $this->assertSame('ORDER-NO-ALLOC', $page->items()[0]->order_number);
        $this->assertSame('no_allocation', $page->items()[0]->hpp_status);
    }

    public function test_rows_expose_hpp_status_per_line_for_each_data_quality_issue(): void
    {
        $user = User::factory()->create();
        $this->seedHppStatusFixture($user);

        $rows = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id);
        $statusByOrder = collect($rows)->mapWithKeys(static fn ($row): array => [$row->order_number => $row->hpp_status])->all();

        $this->assertSame('ok', $statusByOrder['ORDER-OK']);
        $this->assertSame('mapping_missing', $statusByOrder['ORDER-MAPPING-MISSING']);
        $this->assertSame('mapping_ambiguous', $statusByOrder['ORDER-MAPPING-AMBIGUOUS']);
        $this->assertSame('hpp_missing', $statusByOrder['ORDER-HPP-MISSING']);
        $this->assertSame('no_allocation', $statusByOrder['ORDER-NO-ALLOC']);
        $this->assertSame('ok', $statusByOrder['ORDER-CANCELLED']);
    }

    public function test_reconciliation_endpoint_accepts_hpp_status_filter_and_validates_values(): void
    {
        $user = User::factory()->create(['account_status' => AccountStatus::Active]);

        $this->actingAs($user)
            ->get(route('finance.reconciliation', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'hpp_statuses' => ['ok', 'hpp_missing', 'no_allocation'],
            ]))
            ->assertOk()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('finance.reconciliation', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'hpp_statuses' => ['not-a-status'],
            ]))
            ->assertSessionHasErrors('hpp_statuses.0');
    }

    private function seedHppStatusFixture(User $user): void
    {
        $product = $this->registerProduct($user, 'IT0009', 'Produk Fixture HPP', 5000);

        $lines = [
            'ORDER-OK' => ReportLineIdentity::make('ORDER-OK', str_repeat('1', 64), null, 100.0, 1),
            'ORDER-MAPPING-MISSING' => ReportLineIdentity::make('ORDER-MAPPING-MISSING', str_repeat('2', 64), null, 100.0, 1),
            'ORDER-MAPPING-AMBIGUOUS' => ReportLineIdentity::make('ORDER-MAPPING-AMBIGUOUS', str_repeat('3', 64), null, 100.0, 1),
            'ORDER-HPP-MISSING' => ReportLineIdentity::make('ORDER-HPP-MISSING', str_repeat('4', 64), null, 100.0, 1),
            'ORDER-NO-ALLOC' => ReportLineIdentity::make('ORDER-NO-ALLOC', str_repeat('5', 64), null, 100.0, 1),
            'ORDER-CANCELLED' => ReportLineIdentity::make('ORDER-CANCELLED', str_repeat('6', 64), null, 100.0, 1),
        ];

        $fixtures = [
            'ORDER-OK' => ['product_key' => str_repeat('1', 64), 'allocation' => 'ok'],
            'ORDER-MAPPING-MISSING' => ['product_key' => str_repeat('2', 64), 'allocation' => 'mapping_missing'],
            'ORDER-MAPPING-AMBIGUOUS' => ['product_key' => str_repeat('3', 64), 'allocation' => 'mapping_ambiguous'],
            'ORDER-HPP-MISSING' => ['product_key' => str_repeat('4', 64), 'allocation' => 'hpp_missing'],
            'ORDER-NO-ALLOC' => ['product_key' => str_repeat('5', 64), 'allocation' => null],
            'ORDER-CANCELLED' => ['product_key' => str_repeat('6', 64), 'order_status' => 'Batal', 'allocation' => 'ok'],
        ];

        foreach ($fixtures as $orderNumber => $fixture) {
            $this->insertOrder($user, [
                'order_number' => $orderNumber,
                'product_key' => $fixture['product_key'],
                'order_status' => $fixture['order_status'] ?? 'Selesai',
                'quantity' => 1,
                'line_identity' => $lines[$orderNumber],
            ]);

            if ($fixture['allocation'] === 'ok') {
                $this->allocation->allocate($user->id, $lines[$orderNumber], $product->id, $product->baseUnit->id, $product->hppRecords()->first()->id, 1);
            } elseif ($fixture['allocation'] !== null) {
                $this->insertMissingAllocation($user, $lines[$orderNumber], $fixture['allocation']);
            }
        }
    }

    private function registerProduct(User $user, string $code, string $name, int $hpp = 10000, string $effectiveFrom = '2026-08-01 00:00:00')
    {
        return $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => $code,
            'template_name' => $name,
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => $hpp,
                'effective_from' => $effectiveFrom,
            ]],
        ]);
    }

    private function insertOrder(User $user, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $user->id,
            'order_number' => 'ORDER',
            'order_status' => 'Selesai',
            'tracking_number' => 'TRACKING',
            'product_name' => 'Product',
            'product_key' => str_repeat('f', 64),
            'variation_key' => null,
            'discounted_price' => 100,
            'unit_price' => 100,
            'quantity' => 1,
            'returned_quantity' => 0,
            'line_identity' => ReportLineIdentity::make(
                $overrides['order_number'] ?? 'ORDER',
                $overrides['product_key'] ?? str_repeat('f', 64),
                $overrides['variation_key'] ?? null,
                (float) ($overrides['unit_price'] ?? $overrides['discounted_price'] ?? 100),
                (int) ($overrides['quantity'] ?? 1),
            ),
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('marketplace_orders')->insert(array_merge($defaults, $overrides));
    }

    private function insertMissingAllocation(User $user, string $lineIdentity, string $costStatus): void
    {
        OrderCostAllocation::query()->create([
            'user_id' => $user->id,
            'order_line_identity' => $lineIdentity,
            'master_product_id' => null,
            'master_unit_id' => null,
            'effective_hpp_record_id' => null,
            'hpp_per_base_unit' => 0,
            'quantity_base_unit' => 0,
            'total_hpp' => 0,
            'cost_status' => $costStatus,
        ]);
    }
}
