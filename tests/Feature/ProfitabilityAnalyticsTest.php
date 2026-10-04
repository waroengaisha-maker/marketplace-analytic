<?php

namespace Tests\Feature;

use App\Models\OrderCostAllocation;
use App\Models\User;
use App\Services\MarketplaceReconciliationService;
use App\Services\ReportLineIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitabilityAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profitability_analytics_groups_canonical_metrics_by_supported_dimensions(): void
    {
        $user = User::factory()->create();

        $this->insertLine($user, 'ORDER-1', 'product-a', 'SKU-A', 'variation-a', '2026-10-01 10:00:00', 100, 40);
        $this->insertLine($user, 'ORDER-2', 'product-a', 'SKU-A', 'variation-a', '2026-10-01 12:00:00', 200, 80);
        $this->insertLine($user, 'ORDER-3', 'product-b', 'SKU-B', 'variation-b', '2026-10-02 10:00:00', 300, 100);

        $service = app(MarketplaceReconciliationService::class);

        $product = $service->profitabilityAnalytics($user->id, 'product');
        $this->assertCount(2, $product);
        $productAKey = hash('sha256', 'product-a');
        $productA = collect($product)->firstWhere('dimension_key', $productAKey);
        $this->assertSame(300.0, $productA['penghasilan']);
        $this->assertSame(120.0, $productA['hpp']);
        $this->assertSame(180.0, $productA['laba']);
        $this->assertSame(60.0, $productA['profit_margin']);

        $sku = $service->profitabilityAnalytics($user->id, 'sku');
        $this->assertSame(300.0, collect($sku)->firstWhere('dimension_key', 'SKU-A')['penghasilan']);

        $variation = $service->profitabilityAnalytics($user->id, 'variation');
        $this->assertSame(300.0, collect($variation)->firstWhere('dimension_key', 'variation-a')['penghasilan']);

        $day = $service->profitabilityAnalytics($user->id, 'day');
        $this->assertSame(300.0, collect($day)->firstWhere('dimension_key', '2026-10-01')['penghasilan']);

        $month = $service->profitabilityAnalytics($user->id, 'month');
        $this->assertSame(600.0, collect($month)->firstWhere('dimension_key', '2026-10')['penghasilan']);
    }

    public function test_profitability_analytics_preserves_unavailable_hpp_state(): void
    {
        $user = User::factory()->create();

        $this->insertLine($user, 'ORDER-COMPLETE', 'product-a', 'SKU-A', 'variation-a', '2026-10-01 10:00:00', 100, 40);
        $this->insertLine($user, 'ORDER-MISSING', 'product-a', 'SKU-A', 'variation-a', '2026-10-01 11:00:00', 100, null);

        $rows = app(MarketplaceReconciliationService::class)->profitabilityAnalytics($user->id, 'sku');
        $row = $rows[0];

        $this->assertSame(2, $row['line_count']);
        $this->assertSame(1, $row['hpp_available_line_count']);
        $this->assertSame(1, $row['hpp_unavailable_line_count']);
        $this->assertNull($row['hpp']);
        $this->assertNull($row['laba']);
        $this->assertNull($row['profit_margin']);
        $this->assertSame('partial', $row['financial_status']);
    }

    private function insertLine(User $user, string $orderNumber, string $productKey, string $sku, string $variationKey, string $createdAt, float $income, ?float $hpp): void
    {
        $lineIdentity = ReportLineIdentity::make($orderNumber, hash('sha256', $productKey), $variationKey, $income, 1);

        DB::table('marketplace_orders')->insert([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'item_index' => 1,
            'order_status' => 'Selesai',
            'tracking_number' => 'TRACK-'.$orderNumber,
            'product_name' => $productKey,
            'product_key' => hash('sha256', $productKey),
            'sku_reference' => $sku,
            'variation_name' => $variationKey,
            'variation_key' => $variationKey,
            'unit_price' => $income,
            'discounted_price' => $income,
            'quantity' => 1,
            'returned_quantity' => 0,
            'fulfilled_quantity' => 1,
            'cancelled_quantity' => 0,
            'raw_data' => '{}',
            'line_identity' => $lineIdentity,
            'order_created_at' => $createdAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_income')->insert([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'item_index' => 1,
            'product_name' => $productKey,
            'product_key' => hash('sha256', $productKey),
            'variation_key' => $variationKey,
            'line_identity' => $lineIdentity,
            'product_price' => $income,
            'quantity' => 1,
            'total_income' => $income,
            'platform_fee' => 0,
            'free_shipping_xtra_fee' => 0,
            'promo_xtra_service_fee' => 0,
            'order_processing_fee' => 0,
            'pph22' => 0,
            'refund_to_buyer' => 0,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($hpp !== null) {
            OrderCostAllocation::query()->create([
                'user_id' => $user->id,
                'order_line_identity' => $lineIdentity,
                'effective_hpp_record_id' => null,
                'master_product_id' => null,
                'master_unit_id' => null,
                'hpp_per_base_unit' => $hpp,
                'quantity_base_unit' => 1,
                'total_hpp' => $hpp,
                'cost_status' => 'ok',
            ]);
        }
    }
}
