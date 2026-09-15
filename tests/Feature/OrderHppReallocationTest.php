<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\MasterProduct;
use App\Models\OrderCostAllocation;
use App\Models\ShopeeProductMapping;
use App\Models\TemplateItemRow;
use App\Models\User;
use App\Services\ReportLineIdentity;
use App\Services\ShopeeProductMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderHppReallocationTest extends TestCase
{
    use RefreshDatabase;

    protected function activeUser(): User
    {
        return User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
    }

    protected function syncTemplate(User $user, string $code = 'IT0096', string $satuan = 'PCS', int $conversion = 1, int $hppAmount = 10000): MasterProduct
    {
        TemplateItemRow::query()->create([
            'user_id' => $user->id,
            'kode_item' => $code,
            'nama_item' => 'Produk Contoh',
            'satuan' => $satuan,
            'conversion_to_base' => $conversion,
            'hpp_amount' => $hppAmount,
            'is_active' => true,
            'source_file' => 'template-item.xlsx',
        ]);

        $this->actingAs($user)->postJson(route('products.hpp-mapping.sync-template-catalog'))->assertOk();

        return MasterProduct::query()->forUser($user->id)->where('template_item_code', $code)->firstOrFail();
    }

    protected function insertOrderLine(User $user, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $user->id,
            'order_number' => 'ORD-1',
            'item_index' => 1,
            'product_name' => 'Produk Contoh',
            'product_key' => hash('sha256', 'produk contoh'),
            'variation_name' => null,
            'variation_key' => null,
            'parent_sku' => 'SKU1',
            'sku_reference' => null,
            'unit_price' => 5000.0,
            'discounted_price' => 5000.0,
            'quantity' => 2,
            'returned_quantity' => 0,
            'order_created_at' => now(),
            'raw_data' => json_encode(['order_number' => 'ORD-1']),
        ];

        $defaults['line_identity'] = ReportLineIdentity::make(
            $defaults['order_number'],
            $defaults['product_key'],
            $defaults['variation_key'],
            $defaults['unit_price'],
            $defaults['quantity'],
        );

        DB::table('marketplace_orders')->insert(array_merge($defaults, $overrides));
    }

    public function test_reallocate_applies_hpp_for_mapped_order_lines(): void
    {
        $user = $this->activeUser();
        $product = $this->syncTemplate($user);

        $unit = $product->units()->where('unit_code', 'PCS')->firstOrFail();
        app(ShopeeProductMappingService::class)->createManualMapping($user->id, $product, $unit->id, [
            'shopee_product_id' => 'SKU1',
            'shopee_variant_id' => null,
            'shopee_product_name' => 'Produk Contoh',
            'shopee_variant_name' => null,
            'match_confidence' => 1.00,
        ]);

        $this->insertOrderLine($user);

        $response = $this->actingAs($user)->postJson(route('products.hpp-mapping.reallocate'));

        $response->assertOk()->assertJson([
            'ok' => true,
            'total' => 1,
            'ok_count' => 1,
            'mapping_missing' => 0,
            'hpp_missing' => 0,
            'failed' => 0,
        ]);

        $allocation = OrderCostAllocation::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('ok', $allocation->cost_status);
        $this->assertSame(2.0, (float) $allocation->quantity_base_unit);
        $this->assertSame(20000.0, (float) $allocation->total_hpp);
    }

    public function test_reallocate_reports_missing_mapping(): void
    {
        $user = $this->activeUser();
        $this->insertOrderLine($user);

        $this->actingAs($user)->postJson(route('products.hpp-mapping.reallocate'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'total' => 1,
                'ok_count' => 0,
                'mapping_missing' => 1,
                'failed' => 0,
            ]);

        $this->assertSame('mapping_missing', OrderCostAllocation::query()->where('user_id', $user->id)->value('cost_status'));
    }

    public function test_reallocate_is_idempotent(): void
    {
        $user = $this->activeUser();
        $product = $this->syncTemplate($user);
        $unit = $product->units()->where('unit_code', 'PCS')->firstOrFail();
        app(ShopeeProductMappingService::class)->createManualMapping($user->id, $product, $unit->id, [
            'shopee_product_id' => 'SKU1',
            'shopee_product_name' => 'Produk Contoh',
            'match_confidence' => 1.00,
        ]);

        $this->insertOrderLine($user);

        $this->actingAs($user)->postJson(route('products.hpp-mapping.reallocate'))->assertOk();
        $this->actingAs($user)->postJson(route('products.hpp-mapping.reallocate'))->assertOk();

        $this->assertSame(1, OrderCostAllocation::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, ShopeeProductMapping::query()->where('user_id', $user->id)->count());
    }

    public function test_hpp_mapping_page_returns_server_side_pagination(): void
    {
        $user = $this->activeUser();
        $this->syncTemplate($user);
        $this->insertOrderLine($user);

        $this->actingAs($user)->get(route('products.hpp-mapping', ['page' => 1, 'per_page' => 10]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('pagination.total', 1)
                ->where('pagination.per_page', 10)
                ->where('rows.0.productName', 'Produk Contoh'));
    }

    public function test_hpp_mapping_page_filters_by_status_server_side(): void
    {
        $user = $this->activeUser();
        $this->syncTemplate($user);
        $this->insertOrderLine($user);

        $this->actingAs($user)->get(route('products.hpp-mapping', ['status' => 'missing', 'page' => 1, 'per_page' => 10]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1));

        $this->actingAs($user)->get(route('products.hpp-mapping', ['status' => 'exact', 'page' => 1, 'per_page' => 10]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 0)
                ->where('pagination.total', 0));
    }

    public function test_hpp_page_returns_server_side_pagination_and_summary(): void
    {
        $user = $this->activeUser();
        $product = $this->syncTemplate($user);
        $unit = $product->units()->where('unit_code', 'PCS')->firstOrFail();
        app(ShopeeProductMappingService::class)->createManualMapping($user->id, $product, $unit->id, [
            'shopee_product_id' => 'SKU1',
            'shopee_product_name' => 'Produk Contoh',
            'match_confidence' => 1.00,
        ]);

        $this->actingAs($user)->get(route('products.hpp'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('products', 1)
                ->where('pagination.total', 1)
                ->where('pagination.per_page', 10)
                ->where('summary.total', 1)
                ->where('summary.active', 1)
                ->where('products.0.status', 'active'));
    }

    public function test_hpp_page_search_is_server_side(): void
    {
        $user = $this->activeUser();
        $this->syncTemplate($user, 'IT0096', 'PCS', 1, 10000);

        $this->actingAs($user)->get(route('products.hpp', ['search' => 'NOTFOUND']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('products', 0)
                ->where('pagination.total', 0));

        $this->actingAs($user)->get(route('products.hpp', ['search' => 'Produk']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('products', 1));
    }

    public function test_store_manual_mapping_requires_unit_for_selected_template(): void
    {
        $user = $this->activeUser();
        $this->syncTemplate($user);

        $this->actingAs($user)->from(route('products.hpp-mapping'))
            ->post(route('products.hpp-mapping'), [
                'mappings' => [[
                    'id' => 999,
                    'shopee_product_id' => 'SKU1',
                    'shopee_product_name' => 'Produk Contoh',
                    'template_item_code' => 'IT0096',
                    'template_unit_code' => null,
                    'manual_override_note' => '',
                ]],
            ])
            ->assertSessionHasErrors('mappings.0.template_unit_code');

        $this->assertSame(0, ShopeeProductMapping::query()->where('user_id', $user->id)->count());
    }

    public function test_store_manual_mapping_creates_mapping_for_complete_row(): void
    {
        $user = $this->activeUser();
        $product = $this->syncTemplate($user);
        $unit = $product->units()->where('unit_code', 'PCS')->firstOrFail();

        $this->actingAs($user)->from(route('products.hpp-mapping'))
            ->post(route('products.hpp-mapping'), [
                'mappings' => [[
                    'id' => 999,
                    'shopee_product_id' => 'SKU1',
                    'shopee_variant_id' => null,
                    'shopee_product_name' => 'Produk Contoh',
                    'shopee_variant_name' => null,
                    'template_item_code' => 'IT0096',
                    'template_product_id' => null,
                    'template_unit_code' => 'PCS',
                    'template_unit_id' => null,
                    'manual_override_note' => 'Test',
                ]],
            ])
            ->assertRedirect(route('products.hpp-mapping'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shopee_product_mapping', [
            'user_id' => $user->id,
            'master_product_id' => $product->id,
            'master_unit_id' => $unit->id,
            'match_method' => 'manual',
            'match_confidence' => 1.0,
        ]);
    }
}