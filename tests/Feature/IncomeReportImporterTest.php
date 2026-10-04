<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncomeReportImporter;
use App\Services\RefundEventIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class IncomeReportImporterTest extends TestCase
{
    use RefreshDatabase;

    protected IncomeReportImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(IncomeReportImporter::class);
    }

    protected function writeIncomeReport(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'income-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Penghasilan');
        $sheet->fromArray([
            [null],
            [null],
            [
                'No. Pesanan',
                'Nama Produk',
                'Nama Variasi',
                'Harga Produk',
                'Jumlah',
                'Total Penghasilan',
                'Total Pendapatan',
                'Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori E)',
                'Biaya Gratis Ongkir XTRA - Ukuran Khusus (Kategori E)',
                'Lihat berdasarkan',
            ],
            ...$lines,
        ], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    protected function incomeLine(
        string $orderNumber,
        string $product,
        string $variant,
        float $price = 100.0,
        int $quantity = 1,
        ?string $total = null,
        ?string $revenue = null,
    ): array {
        return [
            $orderNumber,
            $product,
            $variant,
            $price,
            $quantity,
            $total ?? (string) ($price * $quantity),
            $revenue,
            0,
            0,
            'Sku',
        ];
    }

    public function test_income_import_sums_special_category_e_free_shipping_fee_once(): void
    {
        $user = User::factory()->create();
        $line = $this->incomeLine('INC-FEE-E', 'Teh Botol', 'Original');
        $line[7] = 12.5;
        $line[8] = 37.5;
        $path = $this->writeIncomeReport([$line]);

        try {
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $this->assertSame(50.0, (float) DB::table('marketplace_income')
            ->where('user_id', $user->id)
            ->where('order_number', 'INC-FEE-E')
            ->value('free_shipping_xtra_fee'));
    }

    public function test_income_import_deduplicates_identical_lines_within_file(): void
    {
        $user = User::factory()->create();

        $path = $this->writeIncomeReport([
            $this->incomeLine('INC-DUP', 'Teh Botol', 'Original', 100.0, 1),
            $this->incomeLine('INC-DUP', 'Teh Botol', 'Original', 100.0, 1),
        ]);

        try {
            $rows = $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, $rows);
        $this->assertSame(1, DB::table('marketplace_income')->where('user_id', $user->id)->count());
    }

    public function test_income_import_keeps_distinct_lines_with_same_order_number(): void
    {
        $user = User::factory()->create();

        $path = $this->writeIncomeReport([
            $this->incomeLine('INC-MIX', 'Teh Botol', 'Original', 100.0, 1),
            $this->incomeLine('INC-MIX', 'Teh Botol', 'Melati', 120.0, 2, '240'),
        ]);

        try {
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $this->assertSame(2, DB::table('marketplace_income')->where('user_id', $user->id)->count());
    }

    public function test_income_reimport_replaces_same_order_number_without_duplicates(): void
    {
        $user = User::factory()->create();

        $path = $this->writeIncomeReport([
            $this->incomeLine('INC-RE', 'Teh Botol', 'Original', 100.0, 1),
        ]);

        try {
            $this->importer->import($path, $user->id);
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, DB::table('marketplace_income')->where('user_id', $user->id)->count());
    }

    public function test_income_import_preserves_total_income_and_total_revenue_as_distinct_sources(): void
    {
        $user = User::factory()->create();

        $path = $this->writeIncomeReport([
            $this->incomeLine(
                'INC-SOURCE-SEPARATION',
                'Teh Botol',
                'Original',
                100.0,
                1,
                '123.45',
                '678.90',
            ),
        ]);

        try {
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $row = DB::table('marketplace_income')
            ->where('user_id', $user->id)
            ->where('order_number', 'INC-SOURCE-SEPARATION')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(123.45, (float) $row->total_income);
        $this->assertSame(678.90, (float) $row->total_revenue);

        $rawData = json_decode((string) $row->raw_data, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('123.45', $rawData['Total Penghasilan']);
        $this->assertEqualsWithDelta(678.90, (float) $rawData['Total Pendapatan'], 0.000001);
    }

    public function test_income_import_does_not_fallback_to_total_revenue_when_total_income_is_missing(): void
    {
        $user = User::factory()->create();

        $line = $this->incomeLine(
            'INC-NO-INCOME-FALLBACK',
            'Teh Botol',
            'Original',
            100.0,
            1,
            null,
            '678.90',
        );
        $line[5] = null;

        $path = $this->writeIncomeReport([$line]);

        try {
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $row = DB::table('marketplace_income')
            ->where('user_id', $user->id)
            ->where('order_number', 'INC-NO-INCOME-FALLBACK')
            ->first();

        $this->assertNotNull($row);
        $this->assertNull($row->total_income);
        $this->assertSame(678.90, (float) $row->total_revenue);
    }

    public function test_income_replacement_rolls_back_when_later_batch_insert_fails(): void
    {
        $user = User::factory()->create();
        $path = $this->writeIncomeReport([$this->incomeLine('INC-ATOMIC', 'Teh Botol', 'Original')]);
        try {
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $original = DB::table('marketplace_income')->where('user_id', $user->id)->where('order_number', 'INC-ATOMIC')->first();
        $this->assertNotNull($original);

        $rows = [];
        for ($index = 0; $index < 500; $index++) {
            $row = (array) $original;
            unset($row['id']);
            $row['order_number'] = 'INC-BATCH-'.$index;
            $row['line_identity'] = hash('sha256', 'atomic-income-'.$index);
            $rows[] = $row;
        }
        $conflict = (array) $original;
        unset($conflict['id']);
        $conflict['order_number'] = 'INC-CONFLICT';
        $conflict['line_identity'] = str_repeat('x', 65);
        $rows[] = $conflict;

        $this->expectException(QueryException::class);
        try {
            $this->importer->persist($rows, $user->id);
        } finally {
            $this->assertSame(1, DB::table('marketplace_income')->where('user_id', $user->id)->where('order_number', 'INC-ATOMIC')->count());
            $this->assertSame(0, DB::table('marketplace_income')->where('user_id', $user->id)->where('order_number', 'INC-BATCH-0')->count());
        }
    }

    public function test_income_persist_deduplicates_duplicate_representations_of_one_refund_event(): void
    {
        $user = User::factory()->create();
        $lineIdentity = str_repeat('l', 64);
        $eventIdentity = RefundEventIdentity::make('ORDER-REFUND-DUP', 'APP-001');

        $base = [
            'user_id' => $user->id,
            'order_number' => 'ORDER-REFUND-DUP',
            'line_identity' => $lineIdentity,
            'refund_event_identity' => $eventIdentity,
            'application_number' => 'APP-001',
            'product_name' => 'Product',
            'product_key' => str_repeat('p', 64),
            'product_price' => 38000,
            'quantity' => 1,
            'total_income' => 0,
            'refund_to_buyer' => -12000,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $rows = $this->importer->persist([
            $base + ['row_type' => 'Sku', 'source_row' => 10],
            $base + ['row_type' => 'Detail', 'source_row' => 11],
        ], $user->id);

        $this->assertSame(1, $rows);

        $stored = DB::table('marketplace_income')
            ->where('user_id', $user->id)
            ->where('order_number', 'ORDER-REFUND-DUP')
            ->get();

        $this->assertCount(1, $stored);
        $this->assertSame('APP-001', $stored[0]->application_number);
        $this->assertSame($eventIdentity, $stored[0]->refund_event_identity);
        $this->assertSame(-12000.0, (float) $stored[0]->refund_to_buyer);
    }

    public function test_income_event_identity_uses_application_number_and_stays_null_without_one(): void
    {
        $user = User::factory()->create();
        $service = app(IncomeReportImporter::class);
        $rows = [
            ['user_id' => $user->id, 'order_number' => 'ORDER-EVENT-IDENTITY', 'line_identity' => str_repeat('l', 64), 'refund_event_identity' => RefundEventIdentity::make('ORDER-EVENT-IDENTITY', 'APP-1')],
            ['user_id' => $user->id, 'order_number' => 'ORDER-EVENT-IDENTITY', 'line_identity' => str_repeat('l', 64), 'refund_event_identity' => RefundEventIdentity::make('ORDER-EVENT-IDENTITY', 'APP-2')],
            ['user_id' => $user->id, 'order_number' => 'ORDER-EVENT-IDENTITY', 'line_identity' => str_repeat('l', 64), 'refund_event_identity' => null],
        ];
        $method = (new \ReflectionClass($service))->getMethod('uniqueRows');
        $method->setAccessible(true);
        $unique = $method->invoke($service, $rows);
        $this->assertCount(3, $unique);
        $this->assertNotSame($unique[0]['refund_event_identity'], $unique[1]['refund_event_identity']);
        $this->assertNull($unique[2]['refund_event_identity']);
    }
}
