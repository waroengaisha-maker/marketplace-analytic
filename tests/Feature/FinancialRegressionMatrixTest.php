<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CanonicalFinancialProjectionService;
use App\Services\MarketplaceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinancialRegressionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public static function canonicalFinancialCases(): array
    {
        return [
            'normal order' => [
                ['refund_amount' => 0.0],
                ['cost_status' => 'ok', 'total_hpp' => 40.0],
                'confirmed',
            ],
            'refund with zero returned quantity' => [
                ['refund_amount' => -20.0, 'returned_quantity' => 0],
                ['cost_status' => 'ok', 'total_hpp' => 40.0],
                'confirmed',
            ],
            'physical return without monetary refund' => [
                ['returned_quantity' => 1, 'refund_amount' => 0.0],
                ['cost_status' => 'ok', 'total_hpp' => 40.0],
                'confirmed',
            ],
            'refund plus physical return' => [
                ['returned_quantity' => 1, 'refund_amount' => -20.0],
                ['cost_status' => 'ok', 'total_hpp' => 40.0],
                'confirmed',
            ],
            'pending hpp' => [
                [],
                ['cost_status' => 'mapping_pending', 'total_hpp' => 40.0],
                'provisional',
            ],
            'missing fee input' => [
                ['platform_fee' => null],
                ['cost_status' => 'ok', 'total_hpp' => 40.0],
                'unavailable',
            ],
            'missing tax input' => [
                ['pph22' => null],
                ['cost_status' => 'ok', 'total_hpp' => 40.0],
                'unavailable',
            ],
        ];
    }

    #[DataProvider('canonicalFinancialCases')]
    public function test_canonical_financial_matrix_preserves_semantic_status(
        array $sourceOverrides,
        array $allocation,
        string $expectedStatus,
    ): void {
        $source = array_merge([
            'quantity' => 2,
            'discounted_price' => 100.0,
            'order_subtotal' => 200.0,
            'platform_fee' => 0.0,
            'free_shipping_xtra_fee' => 0.0,
            'promo_xtra_service_fee' => 0.0,
            'order_processing_fee' => 0.0,
            'pph22' => 0.0,
            'refund_amount' => 0.0,
            'returned_quantity' => 0,
        ], $sourceOverrides);

        $projection = app(CanonicalFinancialProjectionService::class)->projectLine($source, $allocation);

        $this->assertSame($expectedStatus, $projection->status);

        if ($expectedStatus === 'unavailable') {
            $this->assertNull($projection->penghasilan);
            $this->assertNull($projection->laba);
        }

        if ($expectedStatus === 'provisional') {
            $this->assertNull($projection->hpp);
            $this->assertNull($projection->laba);
        }
    }

    public function test_negative_fees_and_nonzero_tax_preserve_source_signs_in_projection(): void
    {
        $projection = app(CanonicalFinancialProjectionService::class)->projectLine([
            'quantity' => 1,
            'discounted_price' => 1000.0,
            'platform_fee' => -100.0,
            'free_shipping_xtra_fee' => -25.0,
            'promo_xtra_service_fee' => -50.0,
            'order_processing_fee' => -10.0,
            'pph22' => 15.0,
            'refund_amount' => 0.0,
        ], ['cost_status' => 'ok', 'total_hpp' => 400.0]);

        $this->assertSame('confirmed', $projection->status);
        $this->assertSame(-175.0, $projection->feeSubtotal);
        $this->assertSame(-185.0, $projection->totalFee);
        $this->assertSame(15.0, $projection->tax);
        $this->assertSame(830.0, $projection->penghasilan);
        $this->assertSame(430.0, $projection->laba);
        $this->assertSame('source_income', $projection->provenance['fees']);
        $this->assertSame('source_income', $projection->provenance['tax']);
    }

    public function test_business_status_matrix_distinguishes_refund_return_and_cancellation(): void
    {
        $user = User::factory()->create();
        $service = app(MarketplaceReconciliationService::class);
        $productKey = str_repeat('m', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'MATRIX-RETURN', 'item_index' => 1, 'returned_quantity' => 1]),
            $this->order($user->id, ['order_number' => 'MATRIX-CANCEL', 'item_index' => 2, 'order_status' => 'Dibatalkan', 'cancellation_reason' => 'Buyer cancelled', 'cancelled_quantity' => 1, 'quantity' => 2]),
            $this->order($user->id, ['order_number' => 'MATRIX-REFUND', 'item_index' => 3]),
            $this->order($user->id, ['order_number' => 'MATRIX-REFUND-RETURN', 'item_index' => 4, 'returned_quantity' => 1]),
        ]);

        DB::table('marketplace_income')->insert([
            $this->income($user->id, ['order_number' => 'MATRIX-REFUND', 'refund_to_buyer' => -100.0]),
            $this->income($user->id, ['order_number' => 'MATRIX-REFUND-RETURN', 'refund_to_buyer' => -50.0]),
        ]);

        $rows = collect($service->reconciliationRows($user->id))->keyBy('order_number');

        $this->assertSame('Returned', $rows['MATRIX-RETURN']->business_status);
        $this->assertSame(0.0, (float) $rows['MATRIX-RETURN']->refund_amount);
        $this->assertSame('Cancelled', $rows['MATRIX-CANCEL']->business_status);
        $this->assertSame(1, $rows['MATRIX-CANCEL']->cancelled_quantity);
        $this->assertSame(2, $rows['MATRIX-CANCEL']->quantity);
        $this->assertSame('Refunded', $rows['MATRIX-REFUND']->business_status);
        $this->assertSame('Partially Refunded', $rows['MATRIX-REFUND-RETURN']->business_status);
        $this->assertSame(1, $rows['MATRIX-REFUND-RETURN']->returned_quantity);
        $this->assertSame(-50.0, (float) $rows['MATRIX-REFUND-RETURN']->refund_amount);
    }

    public function test_missing_financial_inputs_never_become_confirmed_zero_in_page_or_export(): void
    {
        $user = User::factory()->create();
        $service = app(MarketplaceReconciliationService::class);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'MATRIX-MISSING',
            'item_index' => 1,
        ]));
        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'MATRIX-MISSING',
            'platform_fee' => null,
            'free_shipping_xtra_fee' => 0.0,
            'promo_xtra_service_fee' => 0.0,
            'order_processing_fee' => 0.0,
            'pph22' => 0.0,
        ]));

        $line = $service->reconciliationRows($user->id)[0];
        $export = $service->orderExportLines($user->id, null, null, [])[0];

        $this->assertSame('unavailable', $line->canonical_status);
        $this->assertNull($line->total_fee);
        $this->assertNull($line->penghasilan);
        $this->assertNull($line->laba);
        $this->assertSame('unavailable', $export['canonical_status']);
        $this->assertNull($export['total_fee']);
        $this->assertNull($export['penghasilan']);
        $this->assertNull($export['laba']);
    }

    public function test_order_and_customer_pages_and_exports_match_canonical_projection(): void
    {
        $user = User::factory()->create();
        $service = app(MarketplaceReconciliationService::class);
        $params = ['per_page' => 25, 'sort_field' => 'order_number', 'sort_order' => 'asc'];

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'MATRIX-CONSISTENT',
            'item_index' => 1,
            'buyer_username' => 'matrix.buyer',
            'discounted_price' => 500.0,
            'unit_price' => 500.0,
            'quantity' => 2,
        ]));
        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'MATRIX-CONSISTENT',
            'product_price' => 1000.0,
            'quantity' => 2,
            'total_income' => 800.0,
            'platform_fee' => -100.0,
            'free_shipping_xtra_fee' => -20.0,
            'promo_xtra_service_fee' => -30.0,
            'order_processing_fee' => -10.0,
            'pph22' => 5.0,
        ]));
        DB::table('order_cost_allocations')->insert([
            'user_id' => $user->id,
            'order_line_identity' => DB::table('marketplace_orders')->where('user_id', $user->id)->value('line_identity'),
            'hpp_per_base_unit' => 100.0,
            'quantity_base_unit' => 2,
            'total_hpp' => 200.0,
            'cost_status' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $service->reconciliationRows($user->id)[0];
        $expected = [
            'subtotal' => $row->order_subtotal,
            'total_fee' => $row->total_fee,
            'tax' => $row->tax,
            'penghasilan' => $row->penghasilan,
            'hpp' => $row->hpp,
            'laba' => $row->laba,
        ];

        $orderPage = $service->orderSummariesPage($user->id, null, null, $params)->items()[0];
        $orderExport = $service->orderSummariesAll($user->id, null, null, $params)[0];
        $lineExport = $service->orderExportLines($user->id, null, null, [])[0];
        $customerPage = $service->customerSummariesPage($user->id, null, null, $params)->items()[0];
        $customerExport = $service->customerSummariesAll($user->id, null, null, [])[0];
        $dashboard = $service->dashboardStats($user->id);

        foreach ($expected as $field => $value) {
            $this->assertSame($value, (float) ($orderPage[$field] ?? $orderPage->{$field} ?? null));
            $this->assertSame($value, (float) ($orderExport[$field] ?? null));
            $this->assertSame($value, (float) ($customerPage[$field] ?? null));
            $this->assertSame($value, (float) ($customerExport[$field] ?? null));
        }

        $this->assertSame($expected['penghasilan'], $dashboard['canonical_penghasilan']);
        $this->assertSame($expected['laba'], $dashboard['net_profit']);
        $this->assertSame($expected['total_fee'], $lineExport['total_fee']);
        $this->assertSame($expected['penghasilan'], $lineExport['penghasilan']);
        $this->assertSame($expected['hpp'], $lineExport['hpp']);
        $this->assertSame($expected['laba'], $lineExport['laba']);
        $this->assertSame('confirmed', $lineExport['canonical_status']);
    }

    public function test_multiple_refund_applications_remain_independent_events(): void
    {
        $user = User::factory()->create();
        $service = app(MarketplaceReconciliationService::class);
        $productKey = str_repeat('r', 64);
        $lineIdentity = hash('sha256', 'MATRIX-REFUND-EVENT|'.$productKey.'||100|1');

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'MATRIX-REFUND-EVENT',
            'product_key' => $productKey,
            'item_index' => 1,
            'line_identity' => $lineIdentity,
        ]));

        DB::table('marketplace_income')->insert([
            $this->income($user->id, [
                'order_number' => 'MATRIX-REFUND-EVENT',
                'product_key' => $productKey,
                'item_index' => 1,
                'line_identity' => $lineIdentity,
                'refund_event_identity' => hash('sha256', 'MATRIX-REFUND-EVENT|APP-001'),
                'application_number' => 'APP-001',
                'refund_to_buyer' => -30.0,
            ]),
            $this->income($user->id, [
                'order_number' => 'MATRIX-REFUND-EVENT',
                'product_key' => $productKey,
                'item_index' => 1,
                'line_identity' => $lineIdentity,
                'refund_event_identity' => hash('sha256', 'MATRIX-REFUND-EVENT|APP-002'),
                'application_number' => 'APP-002',
                'refund_to_buyer' => -20.0,
            ]),
        ]);

        $row = $service->reconciliationRows($user->id)[0];

        $this->assertSame(-50.0, (float) $row->refund_amount);
        $this->assertSame('Partially Refunded', $row->business_status);
        $this->assertSame('Partial', $row->refund_type);
    }

    public function test_order_and_customer_exports_preserve_unavailable_aggregate_values(): void
    {
        $user = User::factory()->create();
        $service = app(MarketplaceReconciliationService::class);
        $productKey = str_repeat('u', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'MATRIX-MIXED', 'item_index' => 1, 'buyer_username' => 'mixed.buyer']),
            $this->order($user->id, ['order_number' => 'MATRIX-MIXED', 'item_index' => 2, 'buyer_username' => 'mixed.buyer', 'product_key' => $productKey, 'variation_key' => str_repeat('v', 64)]),
        ]);
        DB::table('marketplace_income')->insert([
            $this->income($user->id, ['order_number' => 'MATRIX-MIXED', 'item_index' => 1, 'platform_fee' => 0.0, 'free_shipping_xtra_fee' => 0.0, 'promo_xtra_service_fee' => 0.0, 'order_processing_fee' => 0.0, 'pph22' => 0.0]),
            $this->income($user->id, ['order_number' => 'MATRIX-MIXED', 'item_index' => 2, 'product_key' => $productKey, 'variation_key' => str_repeat('v', 64), 'platform_fee' => null, 'free_shipping_xtra_fee' => 0.0, 'promo_xtra_service_fee' => 0.0, 'order_processing_fee' => 0.0, 'pph22' => 0.0]),
        ]);

        $page = $service->orderSummariesPage($user->id, null, null, ['per_page' => 25])->items()[0];
        $orderExport = $service->orderSummariesAll($user->id, null, null, [])[0];
        $customerExport = $service->customerSummariesAll($user->id, null, null, [])[0];

        $this->assertNull($page['total_fee']);
        $this->assertNull($page['penghasilan']);
        $this->assertNull($page['laba']);
        $this->assertNull($orderExport['total_fee']);
        $this->assertNull($orderExport['penghasilan']);
        $this->assertNull($orderExport['laba']);
        $this->assertNull($customerExport['total_fee']);
        $this->assertNull($customerExport['penghasilan']);
        $this->assertNull($customerExport['laba']);
    }

    private function order(int $userId, array $overrides = []): array
    {
        $orderNumber = $overrides['order_number'] ?? 'MATRIX-ORDER';
        $productKey = $overrides['product_key'] ?? str_repeat('p', 64);
        $variationKey = $overrides['variation_key'] ?? null;
        $price = $overrides['unit_price'] ?? $overrides['discounted_price'] ?? 100.0;
        $quantity = $overrides['quantity'] ?? 1;

        return array_merge([
            'user_id' => $userId,
            'order_number' => 'MATRIX-ORDER',
            'order_status' => 'Selesai',
            'tracking_number' => 'TRACKING',
            'cancellation_reason' => null,
            'buyer_username' => 'matrix.buyer',
            'product_name' => 'Matrix Product',
            'product_key' => str_repeat('p', 64),
            'variation_key' => null,
            'variation_name' => null,
            'discounted_price' => 100.0,
            'unit_price' => 100.0,
            'quantity' => 1,
            'returned_quantity' => 0,
            'fulfilled_quantity' => null,
            'cancelled_quantity' => null,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides, [
            'line_identity' => hash('sha256', $orderNumber.'|'.$productKey.'|'.($variationKey ?? '').'|'.$price.'|'.$quantity),
        ]);
    }

    private function income(int $userId, array $overrides = []): array
    {
        $orderNumber = $overrides['order_number'] ?? 'MATRIX-ORDER';
        $productKey = $overrides['product_key'] ?? str_repeat('p', 64);
        $variationKey = $overrides['variation_key'] ?? null;
        $price = $overrides['unit_price'] ?? $overrides['product_price'] ?? 100.0;
        $quantity = $overrides['quantity'] ?? 1;

        return array_merge([
            'user_id' => $userId,
            'order_number' => 'MATRIX-ORDER',
            'item_index' => 1,
            'product_name' => 'Matrix Product',
            'product_key' => str_repeat('p', 64),
            'variation_key' => null,
            'product_price' => 100.0,
            'unit_price' => 100.0,
            'quantity' => 1,
            'total_income' => 100.0,
            'refund_to_buyer' => 0.0,
            'platform_fee' => 0.0,
            'free_shipping_xtra_fee' => 0.0,
            'promo_xtra_service_fee' => 0.0,
            'order_processing_fee' => 0.0,
            'pph22' => 0.0,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides, [
            'line_identity' => hash('sha256', $orderNumber.'|'.$productKey.'|'.($variationKey ?? '').'|'.$price.'|'.$quantity),
        ]);
    }
}
