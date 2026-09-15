<?php

namespace Tests\Feature;

use App\Models\MasterProduct;
use App\Models\OrderCostAllocation;
use App\Models\ShopeeProductMapping;
use App\Models\User;
use App\Services\MasterProductCatalogService;
use App\Services\OrderReportImporter;
use App\Services\ShopeeProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OrderReportHppAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected MasterProductCatalogService $catalog;

    protected ShopeeProductMappingService $mapping;

    protected OrderReportImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = app(MasterProductCatalogService::class);
        $this->mapping = app(ShopeeProductMappingService::class);
        $this->importer = app(OrderReportImporter::class);
    }

    protected function registerProduct(User $user, string $code, string $name, ?int $hppAmount = 10000, ?string $effectiveFrom = '2026-08-01 00:00:00'): MasterProduct
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

        return $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => $code,
            'template_name' => $name,
            'units' => [$unit],
        ]);
    }

    protected function createMapping(User $user, MasterProduct $product, string $method, ?string $sku, ?string $skuRef, string $productName, ?string $variantName): ShopeeProductMapping
    {
        return ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $product->id,
            'master_unit_id' => $product->baseUnit->id,
            'shopee_product_id' => $sku,
            'shopee_variant_id' => $skuRef,
            'shopee_product_name' => $productName,
            'shopee_variant_name' => $variantName,
            'normalized_shopee_name' => $this->normalizedIdentity($productName, $variantName),
            'match_method' => $method,
            'match_confidence' => $method === 'normalized' ? 0.90 : 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);
    }

    protected function writeOrderReport(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hpp-order-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('orders');
        $sheet->fromArray([[
            'No. Pesanan',
            'Nama Produk',
            'Nama Variasi',
            'Jumlah',
            'Harga Satuan',
            'Harga Setelah Diskon',
            'Status Pesanan',
            'No. Resi',
            'SKU Induk',
            'Nomor Referensi SKU',
            'Returned quantity',
            'Waktu Pesanan Dibuat',
        ], ...$lines], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    protected function orderLine(string $orderNumber, string $product, string $variant, int $quantity = 2, float $price = 100.0, string $orderDate = '2026-08-10 10:00:00', int $returned = 0, ?string $sku = null, ?string $skuRef = null): array
    {
        $defaultSku = 'SP-'.preg_replace('/[^A-Za-z0-9]/', '', $product);

        return [
            $orderNumber,
            $product,
            $variant,
            $quantity,
            $price,
            $price,
            'Selesai',
            'TRACK-'.$orderNumber,
            $sku ?? $defaultSku,
            $skuRef ?? 'SV-'.$variant,
            $returned,
            $orderDate,
        ];
    }

    protected function normalizedIdentity(?string $productName, ?string $variantName): string
    {
        $productKey = strtolower(trim((string) $productName));
        $variantKey = strtolower(trim((string) $variantName));

        return $variantKey === '' ? $productKey : $productKey.'|'.$variantKey;
    }

    protected function import(string $path, int $userId): void
    {
        try {
            $this->importer->import($path, $userId);
        } finally {
            unlink($path);
        }
    }

    public function test_order_import_allocates_ok_for_exact_mapping(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-EXACT', 'Teh Botol Aqua');
        $this->createMapping($user, $product, 'exact', 'SP-EXACT-1', 'SV-EXACT-1', 'Teh Botol Aqua', 'Original');

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-EXACT', 'Teh Botol Aqua', 'Original', 2, 100.0, '2026-08-10 10:00:00', 0, 'SP-EXACT-1', 'SV-EXACT-1'),
        ]), $user->id);

        $lineIdentity = DB::table('marketplace_orders')->where('order_number', 'ORD-EXACT')->value('line_identity');
        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame($lineIdentity, $allocation->order_line_identity);
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame($product->baseUnit->id, $allocation->master_unit_id);
        $this->assertSame('20000.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_allocates_ok_for_normalized_mapping(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-NORM', 'Teh Botol Norm');
        $this->createMapping($user, $product, 'normalized', null, null, 'Teh Botol Norm', 'Original');

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-NORM', 'Teh Botol Norm', 'Original', 3, 100.0, '2026-08-10 10:00:00', 0, 'SP-UNKNOWN', 'SV-UNKNOWN'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame('30000.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_allocates_ok_for_manual_mapping(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-MANUAL', 'Teh Botol Manual');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-MANUAL-1',
            'shopee_variant_id' => 'SV-MANUAL-1',
            'shopee_product_name' => 'Teh Botol Manual',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-MANUAL', 'Teh Botol Manual', 'Original', 1, 100.0, '2026-08-10 10:00:00', 0, 'SP-MANUAL-1', 'SV-MANUAL-1'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame('10000.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_persists_mapping_missing_allocation(): void
    {
        $user = User::factory()->create();

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-NOMAP', 'Teh Botol Unmapped', 'Original'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('mapping_missing', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_persists_mapping_ambiguous_allocation(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-AMB-A', 'Teh Botol Amb');
        $productB = $this->registerProduct($user, 'IT-AMB-B', 'Teh Botol Amb Besar');

        $this->createMapping($user, $productA, 'manual', null, null, 'Teh Botol Amb', 'Original');
        $this->createMapping($user, $productB, 'manual', null, null, 'Teh Botol Amb', 'Original');

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-AMB', 'Teh Botol Amb', 'Original', 2, 100.0, '2026-08-10 10:00:00', 0, 'SP-AMB-1', 'SV-AMB-1'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('mapping_ambiguous', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_persists_hpp_missing_allocation(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-NOHPP', 'Teh Botol NoHpp', null);

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-NOHPP-1',
            'shopee_variant_id' => 'SV-NOHPP-1',
            'shopee_product_name' => 'Teh Botol NoHpp',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-NOHPP', 'Teh Botol NoHpp', 'Original', 2, 100.0, '2026-08-10 10:00:00', 0, 'SP-NOHPP-1', 'SV-NOHPP-1'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('hpp_missing', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_with_zero_hpp_is_ok_with_zero_cost(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-ZEROHPP', 'Teh Botol Gratis', 0);

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-ZEROHPP-1',
            'shopee_variant_id' => 'SV-ZEROHPP-1',
            'shopee_product_name' => 'Teh Botol Gratis',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-ZEROHPP', 'Teh Botol Gratis', 'Original', 5, 100.0, '2026-08-10 10:00:00', 0, 'SP-ZEROHPP-1', 'SV-ZEROHPP-1'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_order_import_creates_exactly_one_allocation_per_line(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-MULTI', 'Teh Botol Multi');

        foreach (['Original', 'Melati'] as $variant) {
            $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
                'shopee_product_id' => 'SP-MULTI',
                'shopee_variant_id' => 'SV-'.$variant,
                'shopee_product_name' => 'Teh Botol Multi',
                'shopee_variant_name' => $variant,
                'manual_override_by' => $user->id,
            ]);
        }

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-MULTI', 'Teh Botol Multi', 'Original', 2, 100.0),
            $this->orderLine('ORD-MULTI', 'Teh Botol Multi', 'Melati', 1, 100.0),
        ]), $user->id);

        $allocations = OrderCostAllocation::query()->forUser($user->id)->get();
        $identities = DB::table('marketplace_orders')->where('order_number', 'ORD-MULTI')->pluck('line_identity');

        $this->assertCount(2, $allocations);
        $this->assertSame(
            $identities->sort()->values()->all(),
            $allocations->pluck('order_line_identity')->sort()->values()->all(),
        );
        $this->assertTrue($allocations->every(fn ($allocation) => $allocation->cost_status === 'ok'));
    }

    public function test_repeated_import_is_idempotent_without_duplicate_allocations(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-IDEM', 'Teh Botol Idem');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-IDEM-1',
            'shopee_variant_id' => 'SV-IDEM-1',
            'shopee_product_name' => 'Teh Botol Idem',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $path = $this->writeOrderReport([$this->orderLine('ORD-IDEM', 'Teh Botol Idem', 'Original', 2, 100.0)]);

        try {
            $this->importer->import($path, $user->id);
            $this->importer->import($path, $user->id);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame('20000.00', (string) $allocation->total_hpp);
    }

    public function test_reimport_with_changed_line_identity_removes_stale_allocation(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-CHG', 'Teh Botol Chg');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-CHG-1',
            'shopee_variant_id' => 'SV-CHG-1',
            'shopee_product_name' => 'Teh Botol Chg',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-CHG', 'Teh Botol Chg', 'Original', 2, 100.0),
        ]), $user->id);

        $staleIdentity = DB::table('marketplace_orders')->where('order_number', 'ORD-CHG')->value('line_identity');
        $this->assertDatabaseHas('order_cost_allocations', ['user_id' => $user->id, 'order_line_identity' => $staleIdentity]);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-CHG', 'Teh Botol Chg', 'Original', 3, 100.0),
        ]), $user->id);

        $freshIdentity = DB::table('marketplace_orders')->where('order_number', 'ORD-CHG')->value('line_identity');

        $this->assertNotSame($staleIdentity, $freshIdentity);
        $this->assertDatabaseMissing('order_cost_allocations', ['user_id' => $user->id, 'order_line_identity' => $staleIdentity]);
        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();
        $this->assertSame($freshIdentity, $allocation->order_line_identity);
        $this->assertSame('30000.00', (string) $allocation->total_hpp);
    }

    public function test_reimport_with_removed_line_removes_stale_allocation(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-REM', 'Teh Botol Rem');

        foreach (['Original', 'Melati'] as $variant) {
            $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
                'shopee_product_id' => 'SP-REM',
                'shopee_variant_id' => 'SV-'.$variant,
                'shopee_product_name' => 'Teh Botol Rem',
                'shopee_variant_name' => $variant,
                'manual_override_by' => $user->id,
            ]);
        }

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-REM', 'Teh Botol Rem', 'Original', 1, 100.0),
            $this->orderLine('ORD-REM', 'Teh Botol Rem', 'Melati', 1, 100.0),
        ]), $user->id);

        $melatiIdentity = DB::table('marketplace_orders')->where('order_number', 'ORD-REM')->where('variation_name', 'Melati')->value('line_identity');
        $this->assertSame(2, OrderCostAllocation::query()->forUser($user->id)->count());

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-REM', 'Teh Botol Rem', 'Original', 1, 100.0),
        ]), $user->id);

        $this->assertDatabaseMissing('order_cost_allocations', ['user_id' => $user->id, 'order_line_identity' => $melatiIdentity]);
        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame(1, DB::table('marketplace_orders')->where('order_number', 'ORD-REM')->count());
    }

    public function test_order_import_allocations_are_tenant_isolated(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productA = $this->registerProduct($userA, 'IT-TEN-A', 'Teh Botol Tenant');
        $productB = $this->registerProduct($userB, 'IT-TEN-B', 'Teh Botol Tenant');

        foreach ([[$userA, $productA], [$userB, $productB]] as [$tenant, $product]) {
            $this->mapping->createManualMapping($tenant->id, $product, $product->baseUnit->id, [
                'shopee_product_id' => 'SP-TEN-1',
                'shopee_variant_id' => 'SV-TEN-1',
                'shopee_product_name' => 'Teh Botol Tenant',
                'shopee_variant_name' => 'Original',
                'manual_override_by' => $tenant->id,
            ]);
        }

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-TEN', 'Teh Botol Tenant', 'Original', 1, 100.0, '2026-08-10 10:00:00', 0, 'SP-TEN-1', 'SV-TEN-1'),
        ]), $userA->id);

        $this->assertSame(1, OrderCostAllocation::query()->forUser($userA->id)->count());
        $this->assertSame(0, OrderCostAllocation::query()->forUser($userB->id)->count());
        $this->assertSame($productA->id, OrderCostAllocation::query()->forUser($userA->id)->first()->master_product_id);
        $this->assertSame(1, DB::table('marketplace_orders')->where('user_id', $userA->id)->count());
        $this->assertSame(0, DB::table('marketplace_orders')->where('user_id', $userB->id)->count());
    }

    public function test_order_import_rejects_mapping_with_unit_of_another_product(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-XP-A', 'Teh Botol Cross');
        $productB = $this->registerProduct($user, 'IT-XP-B', 'Teh Botol Cross Besar');

        $this->createMapping($user, $productA, 'manual', 'SP-XP-1', 'SV-XP-1', 'Teh Botol Cross', 'Original');
        ShopeeProductMapping::query()
            ->where('user_id', $user->id)
            ->where('shopee_product_id', 'SP-XP-1')
            ->update(['master_unit_id' => $productB->baseUnit->id]);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-XP', 'Teh Botol Cross', 'Original', 2, 100.0, '2026-08-10 10:00:00', 0, 'SP-XP-1', 'SV-XP-1'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();

        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
        $this->assertSame('mapping_missing', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_variant_less_manual_override_does_not_hijack_existing_variant_mapping(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-VL-A', 'Teh Botol Variantless');
        $productB = $this->registerProduct($user, 'IT-VL-B', 'Teh Botol Variantless Jumbo');

        $this->mapping->createManualMapping($user->id, $productA, $productA->baseUnit->id, [
            'shopee_product_name' => 'Teh Botol Variantless',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, [
            'shopee_product_name' => 'Teh Botol Variantless',
            'manual_override_by' => $user->id,
        ]);

        $mappings = ShopeeProductMapping::query()->forUser($user->id)->get();

        $this->assertCount(2, $mappings);
        $variantRow = $mappings->firstWhere('normalized_shopee_name', 'teh botol variantless|original');
        $productRow = $mappings->firstWhere('normalized_shopee_name', 'teh botol variantless');

        $this->assertNotNull($variantRow);
        $this->assertNotNull($productRow);
        $this->assertSame($productA->id, $variantRow->master_product_id);
        $this->assertSame($productB->id, $productRow->master_product_id);

        $resolved = $this->mapping->resolve($user->id, [
            'shopee_product_name' => 'Teh Botol Variantless',
            'shopee_variant_name' => 'Original',
        ]);
        $this->assertSame($productA->id, $resolved['mapping']->master_product_id);

        $nameOnly = $this->mapping->resolve($user->id, ['shopee_product_name' => 'Teh Botol Variantless']);
        $this->assertSame('ambiguous', $nameOnly['status']);
    }

    public function test_exact_variant_override_still_updates_existing_mapping_in_place(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-EO-A', 'Kopi Override');
        $productB = $this->registerProduct($user, 'IT-EO-B', 'Kopi Override Susu');

        $this->createMapping($user, $productA, 'exact', 'SP-EO-1', 'SV-EO-1', 'Kopi Override', 'Original');

        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, [
            'shopee_product_id' => 'SP-EO-1',
            'shopee_variant_id' => 'SV-EO-1',
            'shopee_product_name' => 'Kopi Override',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $mappings = ShopeeProductMapping::query()->forUser($user->id)->get();

        $this->assertCount(1, $mappings);
        $this->assertSame($productB->id, $mappings->first()->master_product_id);
        $this->assertSame('manual', $mappings->first()->match_method);

        $resolved = $this->mapping->resolve($user->id, [
            'shopee_product_id' => 'SP-EO-1',
            'shopee_variant_id' => 'SV-EO-1',
            'shopee_product_name' => 'Kopi Override',
            'shopee_variant_name' => 'Original',
        ]);
        $this->assertSame('manual', $resolved['match_method']);
        $this->assertSame($productB->id, $resolved['mapping']->master_product_id);
    }

    public function test_same_product_multiple_variants_resolve_deterministically(): void
    {
        $user = User::factory()->create();
        $productLevel = $this->registerProduct($user, 'IT-MV-A', 'Aqua Botol');
        $productOriginal = $this->registerProduct($user, 'IT-MV-B', 'Aqua Botol Original');
        $productMelati = $this->registerProduct($user, 'IT-MV-C', 'Aqua Botol Melati');

        $this->mapping->createManualMapping($user->id, $productLevel, $productLevel->baseUnit->id, [
            'shopee_product_name' => 'Aqua Botol',
            'manual_override_by' => $user->id,
        ]);
        $this->mapping->createManualMapping($user->id, $productOriginal, $productOriginal->baseUnit->id, [
            'shopee_product_name' => 'Aqua Botol',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);
        $this->mapping->createManualMapping($user->id, $productMelati, $productMelati->baseUnit->id, [
            'shopee_product_name' => 'Aqua Botol',
            'shopee_variant_name' => 'Melati',
            'manual_override_by' => $user->id,
        ]);

        $this->assertSame(3, ShopeeProductMapping::query()->forUser($user->id)->count());

        $original = $this->mapping->resolve($user->id, ['shopee_product_name' => 'Aqua Botol', 'shopee_variant_name' => 'Original']);
        $melati = $this->mapping->resolve($user->id, ['shopee_product_name' => 'Aqua Botol', 'shopee_variant_name' => 'Melati']);
        $nameOnly = $this->mapping->resolve($user->id, ['shopee_product_name' => 'Aqua Botol']);

        $this->assertSame($productOriginal->id, $original['mapping']->master_product_id);
        $this->assertSame($productMelati->id, $melati['mapping']->master_product_id);
        $this->assertSame('ambiguous', $nameOnly['status']);

        $this->import($this->writeOrderReport([
            $this->orderLine('ORD-MV', 'Aqua Botol', 'Original', 2, 100.0, '2026-08-10 10:00:00', 0, 'SP-MV-1', 'SV-MV-1'),
        ]), $user->id);

        $allocation = OrderCostAllocation::query()->forUser($user->id)->first();
        $this->assertSame($productOriginal->id, $allocation->master_product_id);
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame('20000.00', (string) $allocation->total_hpp);
    }
}
