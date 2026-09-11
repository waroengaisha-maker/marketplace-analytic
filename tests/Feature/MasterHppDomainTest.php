<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MasterProductCatalogService;
use App\Services\OrderCostAllocationService;
use App\Services\ShopeeProductMappingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MasterHppDomainTest extends TestCase
{
    use RefreshDatabase;

    protected MasterProductCatalogService $catalog;

    protected ShopeeProductMappingService $mapping;

    protected OrderCostAllocationService $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = app(MasterProductCatalogService::class);
        $this->mapping = app(ShopeeProductMappingService::class);
        $this->allocation = app(OrderCostAllocationService::class);
    }

    public function test_same_user_duplicate_template_item_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT0096',
            'template_name' => '91 Mi Telur 500 gr',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 10000,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT0096',
            'template_name' => 'Duplicate',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 11000,
                'effective_from' => '2026-08-02 00:00:00',
            ]],
        ]);
    }

    public function test_different_users_can_share_the_same_template_item_code(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->catalog->registerTemplateItem($userA->id, [
            'template_item_code' => 'IT0096',
            'template_name' => '91 Mi Telur 500 gr',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 10000,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $this->catalog->registerTemplateItem($userB->id, [
            'template_item_code' => 'IT0096',
            'template_name' => 'Same code different tenant',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 9500,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $this->assertSame(1, $userA->masterProducts()->count());
        $this->assertSame(1, $userB->masterProducts()->count());
    }

    public function test_multi_unit_product_selects_base_unit_and_calculates_hpp_per_base_unit(): void
    {
        $user = User::factory()->create();

        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT0096',
            'template_name' => '91 Mi Telur 500 gr',
            'units' => [
                [
                    'unit_code' => 'PAK',
                    'unit_name' => 'PAK',
                    'conversion_to_base' => 14,
                    'hpp_amount' => 140000,
                    'effective_from' => '2026-08-01 00:00:00',
                ],
                [
                    'unit_code' => 'PCS',
                    'unit_name' => 'PCS',
                    'conversion_to_base' => 1,
                    'hpp_amount' => 10000,
                    'effective_from' => '2026-08-01 00:00:00',
                ],
            ],
        ]);

        $this->assertSame('PCS', $product->fresh()->baseUnit->unit_code);
        $this->assertSame('10000.000000', (string) $product->hppRecords()->first()->hpp_per_base_unit);
        $this->assertSame('140000.00', (string) $product->hppRecords()->where('master_unit_id', $product->units()->where('unit_code', 'PAK')->value('id'))->first()->hpp_amount);
    }

    public function test_hpp_history_resolution_uses_effective_date_and_missing_value_is_null(): void
    {
        $user = User::factory()->create();

        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT0216',
            'template_name' => 'ABC Baterai AA',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 1834,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $unitId = $product->baseUnit->id;
        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 2000,
            'effective_from' => '2026-08-15 00:00:00',
            'source_type' => 'template',
        ]);

        $oldRecord = $this->catalog->resolveEffectiveHpp($user->id, $product->id, $unitId, CarbonImmutable::parse('2026-08-05 12:00:00'));
        $newRecord = $this->catalog->resolveEffectiveHpp($user->id, $product->id, $unitId, CarbonImmutable::parse('2026-08-20 12:00:00'));

        $this->assertSame('1834.00', (string) $oldRecord->hpp_amount);
        $this->assertSame('2000.00', (string) $newRecord->hpp_amount);
        $this->assertSame('2000.00', (string) $this->catalog->resolveEffectiveHpp($user->id, $product->id, $unitId, CarbonImmutable::parse('2027-01-01 00:00:00'))->hpp_amount);
    }

    public function test_overlapping_hpp_periods_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT1122',
            'template_name' => 'Sample Item',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 500,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 600,
            'effective_from' => '2026-08-10 00:00:00',
            'source_type' => 'template',
        ]);

        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 700,
            'effective_from' => '2026-08-09 00:00:00',
            'effective_to' => '2026-08-20 00:00:00',
            'source_type' => 'template',
        ]);
    }

    public function test_manual_mapping_overrides_exact_auto_match(): void
    {
        $user = User::factory()->create();
        $productA = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT0097',
            'template_name' => 'Susu UHT',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 5000,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $productB = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT0098',
            'template_name' => 'Susu UHT 1L',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 5500,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        \App\Models\ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $productB->id,
            'master_unit_id' => $productB->baseUnit->id,
            'shopee_product_id' => 'SP-100',
            'shopee_variant_id' => 'SV-100',
            'shopee_product_name' => 'Susu UHT',
            'shopee_variant_name' => 'Plain',
            'normalized_shopee_name' => 'susu uht',
            'match_method' => 'exact',
            'match_confidence' => 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, [
            'shopee_product_id' => 'SP-100',
            'shopee_variant_id' => 'SV-100',
            'shopee_product_name' => 'Susu UHT',
            'shopee_variant_name' => 'Plain',
            'manual_override_by' => $user->id,
            'manual_override_note' => 'Manual validation',
        ]);

        $resolved = $this->mapping->resolve($user->id, [
            'shopee_product_id' => 'SP-100',
            'shopee_variant_id' => 'SV-100',
            'shopee_product_name' => 'Susu UHT',
            'shopee_variant_name' => 'Plain',
        ]);

        $this->assertSame('matched', $resolved['status']);
        $this->assertSame('manual', $resolved['match_method']);
        $this->assertSame($productB->id, $resolved['mapping']->master_product_id);

        $manualByName = $this->mapping->resolve($user->id, [
            'shopee_product_name' => 'Susu UHT',
        ]);

        $this->assertSame('matched', $manualByName['status']);
        $this->assertSame('manual', $manualByName['match_method']);
        $this->assertSame($productB->id, $manualByName['mapping']->master_product_id);
    }

    public function test_missing_hpp_allocation_sets_explicit_missing_status(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT1001',
            'template_name' => 'Teh Botol',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 3500,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $allocation = $this->allocation->allocate($user->id, 'LINE-MISSING', $product->id, $product->baseUnit->id, 999999, 5, 0);

        $this->assertSame('hpp_missing', $allocation->cost_status);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
        $this->assertSame('0.000000', (string) $allocation->hpp_per_base_unit);
        $this->assertSame('0.000000', (string) $allocation->quantity_base_unit);
    }

    public function test_hpp_overlap_validation_uses_candidate_dates_not_now(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT2000',
            'template_name' => 'Sample overlap',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 1000,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 1200,
            'effective_from' => '2026-08-10 00:00:00',
            'effective_to' => '2026-08-15 00:00:00',
            'source_type' => 'template',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 1300,
            'effective_from' => '2026-08-14 12:00:00',
            'effective_to' => '2026-08-20 00:00:00',
            'source_type' => 'template',
        ]);
    }

    public function test_order_allocation_formula_is_idempotent_and_uses_non_returned_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT1000',
            'template_name' => 'Kopi Hitam',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 12000,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $hpp = $product->hppRecords()->first();
        $allocation = $this->allocation->allocate($user->id, 'LINE-1', $product->id, $product->baseUnit->id, $hpp->id, 5, 2);
        $sameAllocation = $this->allocation->allocate($user->id, 'LINE-1', $product->id, $product->baseUnit->id, $hpp->id, 5, 2);

        $this->assertSame('LINE-1', $allocation->order_line_identity);
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame('36000.00', (string) $allocation->total_hpp);
        $this->assertSame($allocation->id, $sameAllocation->id);
    }
}
