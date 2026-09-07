<?php

namespace Tests\Feature;

use App\Http\Requests\UploadReportsRequest;
use App\Models\User;
use App\Services\IncomeReportImporter;
use App\Services\MarketplaceReconciliationService;
use App\Services\OrderReportImporter;
use App\Services\ReportImportService;
use App\Services\UploadReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionMethod;
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

    public function test_order_260819_rtg8y8ns_excludes_untracked_rawon_from_tracked_query(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('h', 64);

        foreach ([
            ['variation' => 'Rawon', 'tracking' => null],
            ['variation' => 'Rendang', 'tracking' => 'TRACKING'],
            ['variation' => 'Gulai', 'tracking' => 'TRACKING'],
        ] as $line) {
            DB::table('marketplace_orders')->insert($this->order($user->id, [
                'order_number' => '260819RTG8Y8NS',
                'product_key' => $productKey,
                'item_index' => 106,
                'discounted_price' => 7750,
                'unit_price' => 7750,
                'quantity' => 1,
                'variation_name' => $line['variation'],
                'variation_key' => hash('sha256', $line['variation']),
                'tracking_number' => $line['tracking'],
            ]));
        }

        foreach ([6304, 6305] as $totalIncome) {
            DB::table('marketplace_income')->insert($this->income($user->id, [
                'order_number' => '260819RTG8Y8NS',
                'product_key' => $productKey,
                'item_index' => 106,
                'product_price' => 7750,
                'quantity' => 1,
                'total_income' => $totalIncome,
            ]));
        }

        $rows = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id)
            ->where('orders.order_number', '260819RTG8Y8NS')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['Rendang', 'Gulai'], $rows->pluck('variation_name')->all());
        $this->assertSame(['Grouped Match'], $rows->pluck('settlement_status')->unique()->values()->all());
    }

    public function test_reconciliation_contract_ignores_untracked_orders_and_zero_income(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('i', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'CONTRACT-ORDER',
                'product_key' => $productKey,
                'item_index' => 107,
                'discounted_price' => 100,
                'unit_price' => 100,
                'quantity' => 1,
                'tracking_number' => null,
            ]),
            $this->order($user->id, [
                'order_number' => 'CONTRACT-ORDER',
                'product_key' => $productKey,
                'item_index' => 108,
                'discounted_price' => 200,
                'unit_price' => 200,
                'quantity' => 1,
                'tracking_number' => 'TRACKING',
            ]),
        ]);

        DB::table('marketplace_income')->insert([
            $this->income($user->id, [
                'order_number' => 'CONTRACT-ORDER',
                'product_key' => $productKey,
                'item_index' => 107,
                'product_price' => 100,
                'total_income' => 50,
            ]),
            $this->income($user->id, [
                'order_number' => 'CONTRACT-ORDER',
                'product_key' => $productKey,
                'item_index' => 108,
                'product_price' => 200,
                'total_income' => 0,
            ]),
        ]);

        $rows = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id)
            ->where('orders.order_number', 'CONTRACT-ORDER')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(108, $rows->first()->item_index);
        $this->assertNull($rows->first()->total_income);
        $this->assertSame('Estimated', $rows->first()->settlement_status);
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

    public function test_financial_columns_follow_reconciliation_fee_contract(): void
    {
        $row = (object) [
            'quantity' => 2,
            'returned_quantity' => 0,
            'discounted_price' => 500,
            'order_subtotal' => 1000,
            'platform_fee' => 100,
            'free_shipping_xtra_fee' => 50,
            'promo_xtra_service_fee' => 25,
            'order_processing_fee' => 10,
            'pph22' => 5,
        ];

        $result = app(MarketplaceReconciliationService::class)->calculateFinancials($row);

        $this->assertSame(175.0, $result->fee_subtotal);
        $this->assertSame(185.0, $result->total_fee);
        $this->assertSame(1190.0, $result->penghasilan);
        $this->assertSame(0.0, $result->hpp);
        $this->assertSame(1190.0, $result->laba);
    }

    public function test_report_import_number_parser_keeps_decimal_values_intact(): void
    {
        $service = app(ReportImportService::class);
        $method = new ReflectionMethod($service, 'number');
        $method->setAccessible(true);

        $this->assertSame(100.5, $method->invoke($service, '100.50'));
        $this->assertSame(123456.0, $method->invoke($service, '123.456'));
        $this->assertSame(1234.56, $method->invoke($service, '1.234,56'));
        $this->assertSame(1234.56, $method->invoke($service, '1,234.56'));
        $this->assertSame(1500.0, $method->invoke($service, '1.500'));
        $this->assertSame(7750.0, $method->invoke($service, '7.750'));
        $this->assertSame(7750.0, $method->invoke($service, '7,750'));
        $this->assertSame(10.5, $method->invoke($service, '10,5'));
    }

    public function test_importers_preserve_decimal_values_from_excel_numeric_cells(): void
    {
        $user = User::factory()->create();

        $orderPath = tempnam(sys_get_temp_dir(), 'order-import-').'.xlsx';
        $orderSheet = new Spreadsheet;
        $orderWorksheet = $orderSheet->getActiveSheet();
        $orderWorksheet->setTitle('orders');
        $orderWorksheet->fromArray([
            ['No. Pesanan', 'Nama Produk', 'Nama Variasi', 'Jumlah', 'Harga Satuan', 'Harga Setelah Diskon', 'Status Pesanan', 'No. Resi'],
            ['ORDER-DECIMAL', 'Baju', 'Merah', 2, 100.5, 100.5, 'Selesai', 'TRACK-1'],
        ], null, 'A1');
        (new Xlsx($orderSheet))->save($orderPath);

        $incomePath = tempnam(sys_get_temp_dir(), 'income-import-').'.xlsx';
        $incomeSheet = new Spreadsheet;
        $incomeWorksheet = $incomeSheet->getActiveSheet();
        $incomeWorksheet->setTitle('Penghasilan');
        $incomeWorksheet->fromArray([
            ['Laporan Penghasilan'],
            [],
            ['No. Pesanan', 'Nama Produk', 'Nama Variasi', 'Jumlah', 'Harga Satuan', 'Total Pendapatan', 'Lihat berdasarkan', 'No. Pengajuan', 'Waktu Pesanan Dibuat'],
            ['ORDER-DECIMAL', 'Baju', 'Merah', 2, 100.5, 201.0, 'Sku', 'APP-1', '2026-09-07 10:00:00'],
        ], null, 'A1');
        (new Xlsx($incomeSheet))->save($incomePath);

        app(OrderReportImporter::class)->import($orderPath, $user->id);
        app(IncomeReportImporter::class)->import($incomePath, $user->id);

        $this->assertDatabaseHas('marketplace_orders', [
            'user_id' => $user->id,
            'order_number' => 'ORDER-DECIMAL',
            'product_key' => hash('sha256', 'baju'),
            'variation_key' => hash('sha256', 'merah'),
            'quantity' => 2,
            'unit_price' => 100.5,
        ]);
        $this->assertDatabaseHas('marketplace_income', [
            'user_id' => $user->id,
            'order_number' => 'ORDER-DECIMAL',
            'product_key' => hash('sha256', 'baju'),
            'variation_key' => hash('sha256', 'merah'),
            'quantity' => 2,
            'unit_price' => 100.5,
            'total_income' => 201.0,
        ]);

        unlink($orderPath);
        unlink($incomePath);
    }

    public function test_upload_reports_service_imports_excel_rows_through_the_full_pipeline(): void
    {
        $user = User::factory()->create();

        $orderPath = tempnam(sys_get_temp_dir(), 'full-order-').'.xlsx';
        $orderSheet = new Spreadsheet;
        $orderWorksheet = $orderSheet->getActiveSheet();
        $orderWorksheet->setTitle('orders');
        $orderWorksheet->fromArray([
            ['No. Pesanan', 'Nama Produk', 'Nama Variasi', 'Jumlah', 'Harga Satuan', 'Harga Setelah Diskon', 'Status Pesanan', 'No. Resi'],
            ['ORDER-END-TO-END', 'Produk', 'Variation', 1, 250.0, 250.0, 'Selesai', 'TRACK-ORDER'],
        ], null, 'A1');
        (new Xlsx($orderSheet))->save($orderPath);

        $incomePath = tempnam(sys_get_temp_dir(), 'full-income-').'.xlsx';
        $incomeSheet = new Spreadsheet;
        $incomeWorksheet = $incomeSheet->getActiveSheet();
        $incomeWorksheet->setTitle('Penghasilan');
        $incomeWorksheet->fromArray([
            ['Laporan Penghasilan'],
            [],
            ['No. Pesanan', 'Nama Produk', 'Nama Variasi', 'Jumlah', 'Harga Satuan', 'Total Pendapatan', 'Lihat berdasarkan', 'No. Pengajuan', 'Waktu Pesanan Dibuat'],
            ['ORDER-END-TO-END', 'Produk', 'Variation', 1, 250.0, 250.0, 'Sku', 'APP-1', '2026-09-07 10:00:00'],
        ], null, 'A1');
        (new Xlsx($incomeSheet))->save($incomePath);

        $request = Request::create('/imports/upload', 'POST', [], [], [
            'order_report' => new UploadedFile($orderPath, 'order.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            'income_report' => new UploadedFile($incomePath, 'income.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);

        $result = app(UploadReportsService::class)->storeAndImport($request, $user);

        $this->assertSame(['orders' => 1, 'income' => 1], $result);

        $rows = app(MarketplaceReconciliationService::class)->reconciliationRows($user->id);
        $this->assertCount(1, $rows);
        $this->assertSame(250.0, (float) $rows[0]->total_income);
        $this->assertSame('Settled', $rows[0]->settlement_status);

        unlink($orderPath);
        unlink($incomePath);
    }

    public function test_import_allows_missing_variation_name_when_core_reconciliation_columns_exist(): void
    {
        $user = User::factory()->create();

        $path = tempnam(sys_get_temp_dir(), 'order-report-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('orders');
        $sheet->fromArray([
            ['No. Pesanan', 'Nama Produk', 'Jumlah', 'Harga Satuan', 'Harga Setelah Diskon'],
            ['ORDER-1', 'Produk Sample', 2, 250000, 500000],
        ], null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $file = File::createWithContent('order-report.xlsx', file_get_contents($path));
        $request = UploadReportsRequest::create('/imports/upload', 'POST', [], [], ['order_report' => $file], [], [], []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        try {
            $request->validateResolved();
            $this->assertTrue(true);
        } catch (ValidationException $exception) {
            $this->fail('Order reports without a variation name should still be accepted when the core reconciliation columns are present.');
        }

        unlink($path);
    }

    public function test_unsettled_orders_reuse_settled_sku_fee_percentages_and_constant_processing_fee(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('j', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'UNSETTLED-REUSE',
                'product_key' => $productKey,
                'item_index' => 110,
                'discounted_price' => 200,
                'unit_price' => 200,
                'quantity' => 2,
                'tracking_number' => 'TRACKING-A',
            ]),
            $this->order($user->id, [
                'order_number' => 'SETTLED-REUSE',
                'product_key' => $productKey,
                'item_index' => 111,
                'discounted_price' => 200,
                'unit_price' => 200,
                'quantity' => 2,
                'tracking_number' => 'TRACKING-B',
            ]),
        ]);

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'SETTLED-REUSE',
            'product_key' => $productKey,
            'item_index' => 111,
            'product_price' => 200,
            'quantity' => 2,
            'total_income' => 500,
            'platform_fee' => 150,
            'free_shipping_xtra_fee' => 30,
            'promo_xtra_service_fee' => 20,
            'order_processing_fee' => 1000,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'UNSETTLED-REUSE')
            ->first();

        $this->assertSame(400.0, (float) ($row->order_subtotal ?? 0));
        $this->assertSame(150.0, (float) $row->platform_fee);
        $this->assertSame(30.0, (float) $row->free_shipping_xtra_fee);
        $this->assertSame(20.0, (float) $row->promo_xtra_service_fee);
        $this->assertSame(1250.0, (float) $row->order_processing_fee);
        $this->assertSame('Estimated', $row->settlement_status);
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
