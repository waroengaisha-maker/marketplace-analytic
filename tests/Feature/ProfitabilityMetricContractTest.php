<?php

namespace Tests\Feature;

use App\Models\OrderCostAllocation;
use App\Models\User;
use App\Services\MarketplaceReconciliationService;
use App\Services\ReportLineIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitabilityMetricContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_profitability_metrics_use_canonical_values_and_margin_contract(): void
    {
        $user = User::factory()->create();
        $lineIdentity = ReportLineIdentity::make('ORDER-CONTRACT', str_repeat('c', 64), null, 100.0, 2);

        $this->insertOrder($user, $lineIdentity, [
            'order_number' => 'ORDER-CONTRACT',
            'product_key' => str_repeat('c', 64),
            'quantity' => 2,
            'discounted_price' => 100,
        ]);
        $this->insertAllocation($user, $lineIdentity, 150);

        DB::table('marketplace_income')->insert([
            'user_id' => $user->id,
            'order_number' => 'ORDER-CONTRACT',
            'item_index' => null,
            'product_name' => 'Product',
            'product_key' => str_repeat('c', 64),
            'variation_key' => null,
            'line_identity' => $lineIdentity,
            'product_price' => 200,
            'quantity' => 2,
            'total_income' => 170,
            'refund_to_buyer' => -10,
            'platform_fee' => -15,
            'free_shipping_xtra_fee' => -5,
            'promo_xtra_service_fee' => 0,
            'order_processing_fee' => -5,
            'pph22' => -5,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(MarketplaceReconciliationService::class);
        $row = $service->reconciliationRows($user->id)[0];

        $this->assertSame(200.0, $row->order_subtotal);
        $this->assertSame(170.0, $row->penghasilan);
        $this->assertSame(150.0, $row->hpp);
        $this->assertSame(20.0, $row->laba);

        $stats = $service->dashboardStats($user->id);
        $this->assertSame(170.0, $stats['canonical_penghasilan']);
        $this->assertSame(20.0, $stats['total_profit']);
        $this->assertSame(20.0 / 170.0 * 100, $stats['net_margin']);
    }

    public function test_unavailable_hpp_does_not_become_zero_or_fake_profit(): void
    {
        $user = User::factory()->create();
        $lineIdentity = ReportLineIdentity::make('ORDER-CONTRACT-MISSING', str_repeat('m', 64), null, 100.0, 1);

        $this->insertOrder($user, $lineIdentity, [
            'order_number' => 'ORDER-CONTRACT-MISSING',
            'product_key' => str_repeat('m', 64),
            'quantity' => 1,
        ]);
        OrderCostAllocation::query()->create([
            'user_id' => $user->id,
            'order_line_identity' => $lineIdentity,
            'master_product_id' => null,
            'master_unit_id' => null,
            'effective_hpp_record_id' => null,
            'hpp_per_base_unit' => 0,
            'quantity_base_unit' => 0,
            'total_hpp' => 0,
            'cost_status' => 'hpp_missing',
        ]);

        DB::table('marketplace_income')->insert([
            'user_id' => $user->id,
            'order_number' => 'ORDER-CONTRACT-MISSING',
            'item_index' => null,
            'product_name' => 'Product',
            'product_key' => str_repeat('m', 64),
            'variation_key' => null,
            'line_identity' => $lineIdentity,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 100,
            'refund_to_buyer' => 0,
            'platform_fee' => 0,
            'free_shipping_xtra_fee' => 0,
            'promo_xtra_service_fee' => 0,
            'order_processing_fee' => 0,
            'pph22' => 0,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(MarketplaceReconciliationService::class)->dashboardStats($user->id);

        $this->assertNull($stats['total_hpp']);
        $this->assertNull($stats['total_profit']);
        $this->assertNull($stats['net_margin']);
    }

    private function insertOrder(User $user, string $lineIdentity, array $overrides = []): void
    {
        DB::table('marketplace_orders')->insert(array_merge([
            'user_id' => $user->id,
            'order_number' => 'ORDER',
            'item_index' => 1,
            'order_status' => 'Selesai',
            'tracking_number' => 'TRACKING',
            'product_name' => 'Product',
            'product_key' => str_repeat('f', 64),
            'variation_key' => null,
            'unit_price' => 100,
            'discounted_price' => 100,
            'quantity' => 1,
            'returned_quantity' => 0,
            'raw_data' => '{}',
            'line_identity' => $lineIdentity,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function insertAllocation(User $user, string $lineIdentity, float $totalHpp): void
    {
        OrderCostAllocation::query()->create([
            'user_id' => $user->id,
            'order_line_identity' => $lineIdentity,
            'master_product_id' => null,
            'master_unit_id' => null,
            'effective_hpp_record_id' => null,
            'hpp_per_base_unit' => $totalHpp,
            'quantity_base_unit' => 1,
            'total_hpp' => $totalHpp,
            'cost_status' => 'ok',
        ]);
    }
}
