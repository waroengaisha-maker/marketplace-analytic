<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MarketplaceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketplaceReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_match_uses_variation_price_and_quantity(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('a', 64);
        $variationKey = str_repeat('b', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-EXACT',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 101,
            'unit_price' => 100,
            'discounted_price' => 80,
            'quantity' => 2,
            'returned_quantity' => 0,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-EXACT',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 999,
            'product_price' => 160,
            'quantity' => 2,
            'total_income' => 180,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-EXACT')
            ->first();

        $this->assertSame('180.00', $row->total_income);
        $this->assertSame('Settled', $row->settlement_status);
    }

    public function test_zero_income_is_not_a_settlement(): void
    {
        $user = User::factory()->create();

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-ZERO',
            'product_key' => str_repeat('c', 64),
            'item_index' => 102,
            'unit_price' => 100,
            'quantity' => 1,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-ZERO',
            'product_key' => str_repeat('c', 64),
            'item_index' => 102,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 0,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-ZERO')
            ->first();

        $this->assertNull($row->total_income);
        $this->assertSame('Belum Settlement', $row->settlement_status);
    }

    public function test_ambiguous_item_index_fallback_is_not_settled(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('d', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-AMBIGUOUS',
            'product_key' => $productKey,
            'item_index' => 103,
            'unit_price' => 100,
            'quantity' => 1,
        ]));

        foreach ([90, 91] as $totalIncome) {
            DB::table('marketplace_income')->insert($this->income($user->id, [
                'order_number' => 'ORDER-AMBIGUOUS',
                'product_key' => $productKey,
                'item_index' => 103,
                'product_price' => 999,
                'quantity' => 1,
                'total_income' => $totalIncome,
            ]));
        }

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-AMBIGUOUS')
            ->first();

        $this->assertNull($row->total_income);
        $this->assertSame('Ambiguous', $row->settlement_status);
    }

    public function test_identical_item_index_lines_can_settle_as_a_group(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('g', 64);

        foreach (['A', 'B'] as $variation) {
            DB::table('marketplace_orders')->insert($this->order($user->id, [
                'order_number' => 'ORDER-GROUP',
                'product_key' => $productKey,
                'item_index' => 105,
                'discounted_price' => 100,
                'unit_price' => 100,
                'quantity' => 1,
                'variation_name' => $variation,
                'variation_key' => hash('sha256', $variation),
            ]));
        }

        foreach ([90, 91] as $totalIncome) {
            DB::table('marketplace_income')->insert($this->income($user->id, [
                'order_number' => 'ORDER-GROUP',
                'product_key' => $productKey,
                'item_index' => 105,
                'product_price' => 100,
                'quantity' => 1,
                'total_income' => $totalIncome,
            ]));
        }

        $rows = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-GROUP')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame(['Grouped Match'], $rows->pluck('settlement_status')->unique()->values()->all());
        $this->assertEquals(181.0, (float) $rows->sum('total_income'));
    }

    public function test_dashboard_excludes_orders_without_tracking_from_valid_totals(): void
    {
        $user = User::factory()->create();

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-NO-TRACKING',
            'product_key' => str_repeat('e', 64),
            'item_index' => 104,
            'unit_price' => 250,
            'discounted_price' => 250,
            'quantity' => 2,
            'tracking_number' => '   ',
            'order_created_at' => '2026-08-12 10:00:00',
        ]));

        $stats = app(MarketplaceReconciliationService::class)
            ->dashboardStats($user->id, '2026-08-12', '2026-08-12');

        $this->assertSame(500.0, $stats['gross_sales']);
        $this->assertSame(0.0, $stats['net_sales']);
        $this->assertSame(1, $stats['valid_without_tracking']);
        $this->assertSame(500.0, $stats['valid_without_tracking_sales']);
    }

    private function order(int $userId, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $userId,
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
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function income(int $userId, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $userId,
            'order_number' => 'ORDER',
            'product_name' => 'Product',
            'product_key' => str_repeat('f', 64),
            'variation_key' => null,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 100,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }
}
