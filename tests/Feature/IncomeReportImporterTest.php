<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IncomeReportImporter;
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
                'Lihat berdasarkan',
            ],
            ...$lines,
        ], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    protected function incomeLine(string $orderNumber, string $product, string $variant, float $price = 100.0, int $quantity = 1, ?string $total = null): array
    {
        return [
            $orderNumber,
            $product,
            $variant,
            $price,
            $quantity,
            $total ?? (string) ($price * $quantity),
            'Sku',
        ];
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
}
