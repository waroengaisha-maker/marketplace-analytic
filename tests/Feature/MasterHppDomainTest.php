<?php

namespace Tests\Feature;

use App\Models\OrderCostAllocation;
use App\Models\ShopeeProductMapping;
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

    protected function registerProduct(User $user, string $code, string $name)
    {
        return $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => $code,
            'template_name' => $name,
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 10000,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);
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

        ShopeeProductMapping::query()->create([
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

        $this->expectException(InvalidArgumentException::class);
        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 1300,
            'effective_from' => '2026-08-14 12:00:00',
            'effective_to' => '2026-08-20 00:00:00',
            'source_type' => 'template',
        ]);
    }

    public function test_manual_override_updates_existing_mapping_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT1001', 'Teh Botol');
        $productB = $this->registerProduct($user, 'IT1002', 'Teh Botol 600ml');

        $identity = [
            'shopee_product_id' => 'SP-1',
            'shopee_variant_id' => 'SV-1',
            'shopee_product_name' => 'Teh Botol',
            'shopee_variant_name' => 'Original',
        ];

        $this->mapping->createManualMapping($user->id, $productA, $productA->baseUnit->id, $identity + ['manual_override_by' => $user->id]);
        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, $identity + ['manual_override_by' => $user->id]);

        $mappings = ShopeeProductMapping::query()->forUser($user->id)->get();
        $this->assertCount(1, $mappings);
        $this->assertSame($productB->id, $mappings->first()->master_product_id);

        $resolved = $this->mapping->resolve($user->id, $identity);
        $this->assertSame('manual', $resolved['match_method']);
        $this->assertSame($productB->id, $resolved['mapping']->master_product_id);
    }

    public function test_product_and_variation_mappings_do_not_swap(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT2001', 'Teh Botol');
        $productB = $this->registerProduct($user, 'IT2002', 'Teh Botol Kemasan');

        $this->mapping->createManualMapping($user->id, $productA, $productA->baseUnit->id, [
            'shopee_product_name' => 'Teh Botol',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);
        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, [
            'shopee_product_name' => 'Teh Botol',
            'shopee_variant_name' => 'Rasa Melati',
            'manual_override_by' => $user->id,
        ]);

        $original = $this->mapping->resolve($user->id, [
            'shopee_product_name' => 'Teh Botol',
            'shopee_variant_name' => 'Original',
        ]);
        $melati = $this->mapping->resolve($user->id, [
            'shopee_product_name' => 'Teh Botol',
            'shopee_variant_name' => 'Rasa Melati',
        ]);

        $this->assertSame('manual', $original['match_method']);
        $this->assertSame($productA->id, $original['mapping']->master_product_id);
        $this->assertSame('manual', $melati['match_method']);
        $this->assertSame($productB->id, $melati['mapping']->master_product_id);

        $nameOnly = $this->mapping->resolve($user->id, ['shopee_product_name' => 'Teh Botol']);
        $this->assertSame('ambiguous', $nameOnly['status']);
    }

    public function test_manual_mapping_rejects_unit_not_owned_by_product(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT3001', 'Kopi Hitam');
        $productB = $this->registerProduct($user, 'IT3002', 'Kopi Susu');

        $this->expectException(InvalidArgumentException::class);
        $this->mapping->createManualMapping($user->id, $productA, $productB->baseUnit->id, [
            'shopee_product_name' => 'Kopi Hitam',
            'shopee_variant_name' => 'Panjang',
            'manual_override_by' => $user->id,
        ]);

        $this->assertSame(0, ShopeeProductMapping::query()->forUser($user->id)->count());

        $this->expectException(InvalidArgumentException::class);
        $this->mapping->createManualMapping($user->id, $productA, null, [
            'shopee_product_name' => 'Kopi Hitam',
            'manual_override_by' => $user->id,
        ]);
    }

    public function test_idless_mapping_is_deterministic_and_updates_existing_record(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT4001', 'Susu Kedelai');
        $productB = $this->registerProduct($user, 'IT4002', 'Susu Kedelai Instant');

        $identity = [
            'shopee_product_name' => 'Susu Kedelai',
            'shopee_variant_name' => 'Vanilla',
            'manual_override_by' => $user->id,
        ];

        $this->mapping->createManualMapping($user->id, $productA, $productA->baseUnit->id, $identity);
        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, $identity);

        $mappings = ShopeeProductMapping::query()->forUser($user->id)->get();
        $this->assertCount(1, $mappings);
        $this->assertSame($productB->id, $mappings->first()->master_product_id);
    }

    public function test_manual_mapping_is_isolated_per_tenant(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productA = $this->registerProduct($userA, 'IT5001', 'Aqua 600ml');
        $productB = $this->registerProduct($userB, 'IT5001', 'Aqua 600ml');

        $identity = [
            'shopee_product_id' => 'SP-9',
            'shopee_variant_id' => 'SV-9',
            'shopee_product_name' => 'Aqua 600ml',
            'shopee_variant_name' => 'Regular',
        ];

        $this->mapping->createManualMapping($userA->id, $productA, $productA->baseUnit->id, $identity + ['manual_override_by' => $userA->id]);
        $this->mapping->createManualMapping($userB->id, $productB, $productB->baseUnit->id, $identity + ['manual_override_by' => $userB->id]);

        $resolvedA = $this->mapping->resolve($userA->id, $identity);
        $resolvedB = $this->mapping->resolve($userB->id, $identity);

        $this->assertSame($productA->id, $resolvedA['mapping']->master_product_id);
        $this->assertSame($productB->id, $resolvedB['mapping']->master_product_id);
    }

    public function test_truncated_identity_is_deterministic_when_unique(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT7001', 'Produk Nama Sangat Panjang Sekali');

        $longName = str_repeat('X', 400);
        $longVariant = str_repeat('Y', 400);

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_name' => $longName,
            'shopee_variant_name' => $longVariant,
            'manual_override_by' => $user->id,
        ]);

        $stored = ShopeeProductMapping::query()->forUser($user->id)->first();
        $this->assertSame(255, mb_strlen($stored->normalized_shopee_name));
        $this->assertNotSame($longName.'|'.$longVariant, $stored->normalized_shopee_name);

        $resolved = $this->mapping->resolve($user->id, [
            'shopee_product_name' => $longName,
            'shopee_variant_name' => $longVariant,
        ]);

        $this->assertSame('matched', $resolved['status']);
        $this->assertSame('manual', $resolved['match_method']);
        $this->assertSame($product->id, $resolved['mapping']->master_product_id);
    }

    public function test_truncated_identity_collision_returns_ambiguous_not_arbitrary(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT7002', 'Produk A Collision');
        $productB = $this->registerProduct($user, 'IT7003', 'Produk B Collision');

        $longName = str_repeat('X', 400);

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $productA->id,
            'master_unit_id' => $productA->baseUnit->id,
            'shopee_product_id' => 'SP-COLLIDE-1',
            'shopee_variant_id' => 'SV-COLLIDE-1',
            'shopee_product_name' => $longName,
            'shopee_variant_name' => 'Varian Alpha Panjang Sekali',
            'normalized_shopee_name' => mb_substr(strtolower($longName), 0, 255),
            'match_method' => 'manual',
            'match_confidence' => 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $productB->id,
            'master_unit_id' => $productB->baseUnit->id,
            'shopee_product_id' => 'SP-COLLIDE-2',
            'shopee_variant_id' => 'SV-COLLIDE-2',
            'shopee_product_name' => $longName,
            'shopee_variant_name' => 'Varian Beta Panjang Sekali Berbeda',
            'normalized_shopee_name' => mb_substr(strtolower($longName), 0, 255),
            'match_method' => 'manual',
            'match_confidence' => 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        $resolved = $this->mapping->resolve($user->id, [
            'shopee_product_name' => $longName,
        ]);

        $this->assertSame('ambiguous', $resolved['status']);
        $this->assertNull($resolved['mapping'] ?? null);
        $this->assertCount(2, $resolved['candidates']);
    }

    public function test_manual_override_has_priority_over_exact_and_normalized_matches(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT6001', 'Mi Instan Goreng');
        $productB = $this->registerProduct($user, 'IT6002', 'Mi Instan Goreng Jumbo');
        $productC = $this->registerProduct($user, 'IT6003', 'Mi Instan Goreng Premium');

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $productA->id,
            'master_unit_id' => $productA->baseUnit->id,
            'shopee_product_id' => 'SP-5',
            'shopee_variant_id' => 'SV-5',
            'shopee_product_name' => 'Mi Instan',
            'shopee_variant_name' => 'Kari',
            'normalized_shopee_name' => 'mi instan|kari',
            'match_method' => 'exact',
            'match_confidence' => 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $productB->id,
            'master_unit_id' => $productB->baseUnit->id,
            'shopee_product_name' => 'Mi Instan',
            'shopee_variant_name' => 'Kari',
            'normalized_shopee_name' => 'mi instan|kari',
            'match_method' => 'normalized',
            'match_confidence' => 0.90,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        $identity = [
            'shopee_product_id' => 'SP-5',
            'shopee_variant_id' => 'SV-5',
            'shopee_product_name' => 'Mi Instan',
            'shopee_variant_name' => 'Kari',
        ];

        $exactMatch = $this->mapping->resolve($user->id, $identity);
        $this->assertSame('exact', $exactMatch['match_method']);
        $this->assertSame($productA->id, $exactMatch['mapping']->master_product_id);

        $this->mapping->createManualMapping($user->id, $productC, $productC->baseUnit->id, $identity + ['manual_override_by' => $user->id]);

        $manualMatch = $this->mapping->resolve($user->id, $identity);
        $this->assertSame('manual', $manualMatch['match_method']);
        $this->assertSame($productC->id, $manualMatch['mapping']->master_product_id);

        $this->assertSame(2, ShopeeProductMapping::query()->forUser($user->id)->count());
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

    public function test_end_to_end_exact_mapping_allocates_hpp(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-1', 'Teh Botol E2E');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-E2E-1',
            'shopee_variant_id' => 'SV-E2E-1',
            'shopee_product_name' => 'Teh Botol E2E',
            'shopee_variant_name' => 'Original',
            'manual_override_by' => $user->id,
        ]);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-1', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_id' => 'SP-E2E-1',
            'shopee_variant_id' => 'SV-E2E-1',
            'shopee_product_name' => 'Teh Botol E2E',
            'shopee_variant_name' => 'Original',
        ], 3, 1);

        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame('LINE-E2E-1', $allocation->order_line_identity);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame($product->baseUnit->id, $allocation->master_unit_id);
        $this->assertSame('2.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('20000.00', (string) $allocation->total_hpp);
        $this->assertSame($product->hppRecords()->first()->id, $allocation->effective_hpp_record_id);
    }

    public function test_end_to_end_manual_mapping_normalized_fallback_allocates_hpp(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-2', 'Susu Kedelai E2E');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_name' => 'Susu Kedelai E2E',
            'shopee_variant_name' => 'Vanilla',
            'manual_override_by' => $user->id,
        ]);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-2', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_name' => 'SUSU KEDELAI E2E !!!',
            'shopee_variant_name' => 'VANILLA.',
        ], 2, 0);

        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame('20000.00', (string) $allocation->total_hpp);
    }

    public function test_end_to_end_ambiguous_mapping_produces_no_hpp(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-E2E-3A', 'Kopi E2E');
        $productB = $this->registerProduct($user, 'IT-E2E-3B', 'Kopi E2E Lain');

        $identityA = ['shopee_product_name' => 'Kopi E2E', 'shopee_variant_name' => 'Original', 'manual_override_by' => $user->id];
        $identityB = ['shopee_product_name' => 'Kopi E2E', 'shopee_variant_name' => 'Melati', 'manual_override_by' => $user->id];
        $this->mapping->createManualMapping($user->id, $productA, $productA->baseUnit->id, $identityA);
        $this->mapping->createManualMapping($user->id, $productB, $productB->baseUnit->id, $identityB);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-3', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_name' => 'Kopi E2E',
        ], 4, 0);

        $this->assertSame('mapping_ambiguous', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertNull($allocation->master_unit_id);
        $this->assertNull($allocation->effective_hpp_record_id);
        $this->assertSame('0.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_end_to_end_missing_mapping_produces_no_hpp(): void
    {
        $user = User::factory()->create();

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-4', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_name' => 'Item Tidak Terdaftar',
            'shopee_variant_name' => 'Ukuran Kecil',
        ], 4, 0);

        $this->assertSame('mapping_missing', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_end_to_end_missing_hpp_sets_hpp_missing(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT-E2E-5',
            'template_name' => 'Produk Tanpa HPP',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
            ]],
        ]);

        $this->assertNull($product->hppRecords()->first());

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-E2E-5',
            'shopee_variant_id' => 'SV-E2E-5',
            'shopee_product_name' => 'Produk Tanpa HPP',
            'shopee_variant_name' => 'Regular',
            'manual_override_by' => $user->id,
        ]);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-5', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_id' => 'SP-E2E-5',
            'shopee_variant_id' => 'SV-E2E-5',
            'shopee_product_name' => 'Produk Tanpa HPP',
            'shopee_variant_name' => 'Regular',
        ], 2, 0);

        $this->assertSame('hpp_missing', $allocation->cost_status);
        $this->assertSame($product->id, $allocation->master_product_id);
        $this->assertSame($product->baseUnit->id, $allocation->master_unit_id);
        $this->assertNull($allocation->effective_hpp_record_id);
        $this->assertSame('0.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_zero_hpp_is_valid_and_not_missing(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT-E2E-6',
            'template_name' => 'Produk Gratis HPP Nol',
            'units' => [[
                'unit_code' => 'PCS',
                'unit_name' => 'PCS',
                'conversion_to_base' => 1,
                'hpp_amount' => 0,
                'effective_from' => '2026-08-01 00:00:00',
            ]],
        ]);

        $this->assertNotNull($product->hppRecords()->first());
        $this->assertSame('0.00', (string) $product->hppRecords()->first()->hpp_amount);

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-E2E-6',
            'shopee_variant_id' => 'SV-E2E-6',
            'shopee_product_name' => 'Produk Gratis HPP Nol',
            'shopee_variant_name' => 'Regular',
            'manual_override_by' => $user->id,
        ]);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-6', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_id' => 'SP-E2E-6',
            'shopee_variant_id' => 'SV-E2E-6',
            'shopee_product_name' => 'Produk Gratis HPP Nol',
            'shopee_variant_name' => 'Regular',
        ], 5, 0);

        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame('5.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
        $this->assertNotNull($allocation->effective_hpp_record_id);
    }

    public function test_hpp_effective_to_boundary_is_inclusive(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-7', 'Boundary Product');

        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 5000,
            'effective_from' => '2026-08-10 00:00:00',
            'effective_to' => '2026-08-31 23:59:59',
            'source_type' => 'template',
        ]);

        $resolved = $this->catalog->resolveEffectiveHpp($user->id, $product->id, $product->baseUnit->id, CarbonImmutable::parse('2026-08-31 23:59:59'));

        $this->assertNotNull($resolved);
        $this->assertSame('5000.00', (string) $resolved->hpp_amount);

        $after = $this->catalog->resolveEffectiveHpp($user->id, $product->id, $product->baseUnit->id, CarbonImmutable::parse('2026-09-01 00:00:00'));
        $this->assertNull($after);
    }

    public function test_hpp_effective_from_boundary_is_inclusive(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-8', 'Boundary From');

        $resolved = $this->catalog->resolveEffectiveHpp($user->id, $product->id, $product->baseUnit->id, CarbonImmutable::parse('2026-08-01 00:00:00'));

        $this->assertNotNull($resolved);
        $this->assertSame('10000.00', (string) $resolved->hpp_amount);
    }

    public function test_hpp_version_change_preserves_historical_allocation(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-9', 'Produk Historis');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-E2E-9',
            'shopee_variant_id' => 'SV-E2E-9',
            'shopee_product_name' => 'Produk Historis',
            'shopee_variant_name' => 'Regular',
            'manual_override_by' => $user->id,
        ]);

        $identity = [
            'shopee_product_id' => 'SP-E2E-9',
            'shopee_variant_id' => 'SV-E2E-9',
            'shopee_product_name' => 'Produk Historis',
            'shopee_variant_name' => 'Regular',
        ];

        $historical = $this->allocation->allocateForOrderLine($user->id, 'LINE-HIST-1', CarbonImmutable::parse('2026-08-05 10:00:00'), $identity, 2, 0);
        $this->assertSame('20000.00', (string) $historical->total_hpp);

        $this->catalog->persistHppVersion($product, $product->baseUnit, [
            'hpp_amount' => 25000,
            'effective_from' => '2026-08-20 00:00:00',
            'source_type' => 'template',
        ]);

        $reprocessed = $this->allocation->allocateForOrderLine($user->id, 'LINE-HIST-1', CarbonImmutable::parse('2026-08-05 10:00:00'), $identity, 2, 0);

        $this->assertSame($historical->id, $reprocessed->id);
        $this->assertSame('20000.00', (string) $reprocessed->total_hpp);
        $this->assertSame($historical->effective_hpp_record_id, $reprocessed->effective_hpp_record_id);

        $newLine = $this->allocation->allocateForOrderLine($user->id, 'LINE-HIST-2', CarbonImmutable::parse('2026-08-25 10:00:00'), $identity, 2, 0);
        $this->assertSame('50000.00', (string) $newLine->total_hpp);
    }

    public function test_conversion_calculation_uses_base_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT-E2E-10',
            'template_name' => 'Multi Unit E2E',
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

        $pak = $product->units()->where('unit_code', 'PAK')->first();

        $this->mapping->createManualMapping($user->id, $product, $pak->id, [
            'shopee_product_id' => 'SP-E2E-10',
            'shopee_variant_id' => 'SV-E2E-10',
            'shopee_product_name' => 'Multi Unit E2E',
            'shopee_variant_name' => 'Grosir',
            'manual_override_by' => $user->id,
        ]);

        $allocation = $this->allocation->allocate($user->id, 'LINE-E2E-10', $product->id, $pak->id, $pak->hppRecords()->first()->id, 2, 1);

        $this->assertSame('14.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('140000.00', (string) $allocation->total_hpp);
        $this->assertSame('10000.000000', (string) $allocation->hpp_per_base_unit);
    }

    public function test_decimal_precision_preserves_partial_base_amounts(): void
    {
        $user = User::factory()->create();
        $product = $this->catalog->registerTemplateItem($user->id, [
            'template_item_code' => 'IT-E2E-11',
            'template_name' => 'Presisi E2E',
            'units' => [
                [
                    'unit_code' => 'BOX',
                    'unit_name' => 'BOX',
                    'conversion_to_base' => 3,
                    'hpp_amount' => 100,
                    'effective_from' => '2026-08-01 00:00:00',
                ],
                [
                    'unit_code' => 'PCS',
                    'unit_name' => 'PCS',
                    'conversion_to_base' => 1,
                    'hpp_amount' => 33.333333,
                    'effective_from' => '2026-08-01 00:00:00',
                ],
            ],
        ]);

        $box = $product->units()->where('unit_code', 'BOX')->first();
        $boxHpp = $box->hppRecords()->first();

        $this->assertSame('33.333333', (string) $boxHpp->hpp_per_base_unit);

        $allocation = $this->allocation->allocate($user->id, 'LINE-E2E-11', $product->id, $box->id, $boxHpp->id, 2, 0);

        $this->assertSame('6.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('200.00', (string) $allocation->total_hpp);
    }

    public function test_reprocessing_is_idempotent_and_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-12', 'Produk Idempoten');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-E2E-12',
            'shopee_variant_id' => 'SV-E2E-12',
            'shopee_product_name' => 'Produk Idempoten',
            'shopee_variant_name' => 'Regular',
            'manual_override_by' => $user->id,
        ]);

        $identity = [
            'shopee_product_id' => 'SP-E2E-12',
            'shopee_variant_id' => 'SV-E2E-12',
            'shopee_product_name' => 'Produk Idempoten',
            'shopee_variant_name' => 'Regular',
        ];

        $first = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-12', CarbonImmutable::parse('2026-08-10 10:00:00'), $identity, 2, 0);
        $second = $this->allocation->allocateForOrderLine($user->id, 'LINE-E2E-12', CarbonImmutable::parse('2026-08-10 10:00:00'), $identity, 2, 0);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrderCostAllocation::query()->forUser($user->id)->count());
    }

    public function test_allocation_is_isolated_per_tenant(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $productA = $this->registerProduct($userA, 'IT-E2E-13A', 'Aqua E2E');
        $productB = $this->registerProduct($userB, 'IT-E2E-13B', 'Aqua E2E');

        $identity = [
            'shopee_product_id' => 'SP-E2E-13',
            'shopee_variant_id' => 'SV-E2E-13',
            'shopee_product_name' => 'Aqua E2E',
            'shopee_variant_name' => 'Regular',
        ];

        $this->mapping->createManualMapping($userA->id, $productA, $productA->baseUnit->id, $identity + ['manual_override_by' => $userA->id]);
        $this->mapping->createManualMapping($userB->id, $productB, $productB->baseUnit->id, $identity + ['manual_override_by' => $userB->id]);

        $allocationA = $this->allocation->allocateForOrderLine($userA->id, 'LINE-TENANT-A', CarbonImmutable::parse('2026-08-10 10:00:00'), $identity, 1, 0);
        $allocationB = $this->allocation->allocateForOrderLine($userB->id, 'LINE-TENANT-B', CarbonImmutable::parse('2026-08-10 10:00:00'), $identity, 1, 0);

        $this->assertSame($productA->id, $allocationA->master_product_id);
        $this->assertSame($productB->id, $allocationB->master_product_id);
        $this->assertSame($userA->id, $allocationA->user_id);
        $this->assertSame($userB->id, $allocationB->user_id);
        $this->assertSame(1, OrderCostAllocation::query()->forUser($userA->id)->count());
        $this->assertSame(1, OrderCostAllocation::query()->forUser($userB->id)->count());
    }

    public function test_mapping_pointing_to_foreign_product_is_not_trusted(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignProduct = $this->registerProduct($otherUser, 'IT-E2E-14C', 'Produk Milik User Lain');

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $foreignProduct->id,
            'master_unit_id' => $foreignProduct->baseUnit->id,
            'shopee_product_id' => 'SP-E2E-14',
            'shopee_variant_id' => 'SV-E2E-14',
            'shopee_product_name' => 'Produk B E2E',
            'shopee_variant_name' => 'Regular',
            'normalized_shopee_name' => 'produk b e2e|regular',
            'match_method' => 'manual',
            'match_confidence' => 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-FOREIGN', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_id' => 'SP-E2E-14',
            'shopee_variant_id' => 'SV-E2E-14',
            'shopee_product_name' => 'Produk B E2E',
            'shopee_variant_name' => 'Regular',
        ], 2, 0);

        $this->assertSame('mapping_missing', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertNull($allocation->master_unit_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_mapping_with_foreign_unit_is_not_trusted(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-E2E-15A', 'Kopi A E2E');
        $productB = $this->registerProduct($user, 'IT-E2E-15B', 'Kopi B E2E');

        ShopeeProductMapping::query()->create([
            'user_id' => $user->id,
            'master_product_id' => $productA->id,
            'master_unit_id' => $productB->baseUnit->id,
            'shopee_product_id' => 'SP-E2E-15',
            'shopee_variant_id' => 'SV-E2E-15',
            'shopee_product_name' => 'Kopi A E2E',
            'shopee_variant_name' => 'Panjang',
            'normalized_shopee_name' => 'kopi a e2e|panjang',
            'match_method' => 'manual',
            'match_confidence' => 1.00,
            'is_active' => true,
            'ambiguous' => false,
        ]);

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-FOREIGN-UNIT', CarbonImmutable::parse('2026-08-10 10:00:00'), [
            'shopee_product_id' => 'SP-E2E-15',
            'shopee_variant_id' => 'SV-E2E-15',
            'shopee_product_name' => 'Kopi A E2E',
            'shopee_variant_name' => 'Panjang',
        ], 2, 0);

        $this->assertSame('mapping_missing', $allocation->cost_status);
        $this->assertNull($allocation->master_product_id);
        $this->assertSame('0.00', (string) $allocation->total_hpp);
    }

    public function test_allocate_rejects_hpp_record_not_belonging_to_product_and_unit(): void
    {
        $user = User::factory()->create();
        $productA = $this->registerProduct($user, 'IT-E2E-16A', 'Item A Cross');
        $productB = $this->registerProduct($user, 'IT-E2E-16B', 'Item B Cross');

        $foreignHpp = $productB->hppRecords()->first();

        $this->expectException(InvalidArgumentException::class);
        $this->allocation->allocate($user->id, 'LINE-CROSS', $productA->id, $productA->baseUnit->id, $foreignHpp->id, 1, 0);
    }

    public function test_refund_returns_product_reduce_allocated_quantity(): void
    {
        $user = User::factory()->create();
        $product = $this->registerProduct($user, 'IT-E2E-17', 'Produk Retur');

        $this->mapping->createManualMapping($user->id, $product, $product->baseUnit->id, [
            'shopee_product_id' => 'SP-E2E-17',
            'shopee_variant_id' => 'SV-E2E-17',
            'shopee_product_name' => 'Produk Retur',
            'shopee_variant_name' => 'Regular',
            'manual_override_by' => $user->id,
        ]);

        $identity = [
            'shopee_product_id' => 'SP-E2E-17',
            'shopee_variant_id' => 'SV-E2E-17',
            'shopee_product_name' => 'Produk Retur',
            'shopee_variant_name' => 'Regular',
        ];

        $allocation = $this->allocation->allocateForOrderLine($user->id, 'LINE-RETUR', CarbonImmutable::parse('2026-08-10 10:00:00'), $identity, 10, 4);

        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame('6.000000', (string) $allocation->quantity_base_unit);
        $this->assertSame('60000.00', (string) $allocation->total_hpp);
    }
}
