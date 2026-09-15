<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\MasterProduct;
use App\Models\MasterProductHpp;
use App\Models\MasterProductUnit;
use App\Models\TemplateItemRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppMappingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function activeUser(): User
    {
        return User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
    }

    protected function createTemplateRows(User $user, array $rows): void
    {
        foreach ($rows as $row) {
            TemplateItemRow::query()->create(array_merge([
                'user_id' => $user->id,
                'kode_item' => 'IT0096',
                'barcode' => null,
                'sku' => null,
                'nama_item' => 'Produk Contoh',
                'jenis' => null,
                'merek' => null,
                'rak' => null,
                'conversion_to_base' => 1,
                'tipe_item' => null,
                'satuan' => 'PCS',
                'hpp_amount' => 10000,
                'harga_jual' => null,
                'keterangan' => null,
                'source_file' => 'template-item.xlsx',
                'is_active' => true,
            ], $row));
        }
    }

    public function test_sync_template_catalog_pulls_template_item_rows_into_master_catalog(): void
    {
        $user = $this->activeUser();

        $this->createTemplateRows($user, [
            ['kode_item' => 'IT0096', 'nama_item' => '91 Mi Telur 500 gr', 'satuan' => 'PCS', 'conversion_to_base' => 1, 'hpp_amount' => 10000],
            ['kode_item' => 'IT0096', 'nama_item' => '91 Mi Telur 500 gr', 'satuan' => 'PAK', 'conversion_to_base' => 14, 'hpp_amount' => 140000],
            ['kode_item' => 'IT0216', 'nama_item' => 'ABC Baterai AA', 'satuan' => 'PCS', 'conversion_to_base' => 1, 'hpp_amount' => 1834],
        ]);

        $response = $this->actingAs($user)->postJson(route('products.hpp-mapping.sync-template-catalog'));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'created' => 2,
                'existing' => 0,
            ]);

        $this->assertSame(2, MasterProduct::query()->forUser($user->id)->count());
        $this->assertSame(3, MasterProductUnit::query()->whereHas('product', fn ($q) => $q->forUser($user->id))->count());
        $this->assertSame(3, MasterProductHpp::query()->whereHas('product', fn ($q) => $q->forUser($user->id))->count());

        $options = $response->json('templateOptions');
        $this->assertCount(2, $options);

        $product = MasterProduct::query()->forUser($user->id)->where('template_item_code', 'IT0096')->firstOrFail();
        $this->assertSame('active', $product->status);
        $this->assertCount(2, $product->units);
    }

    public function test_sync_template_catalog_is_idempotent(): void
    {
        $user = $this->activeUser();

        $this->createTemplateRows($user, [
            ['kode_item' => 'IT0096', 'nama_item' => '91 Mi Telur 500 gr'],
        ]);

        $this->actingAs($user)->postJson(route('products.hpp-mapping.sync-template-catalog'))->assertOk();

        $response = $this->actingAs($user)->postJson(route('products.hpp-mapping.sync-template-catalog'));

        $response->assertOk()->assertJson(['ok' => true, 'created' => 0, 'existing' => 1]);
        $this->assertSame(1, MasterProduct::query()->forUser($user->id)->count());
        $this->assertSame(1, MasterProductUnit::query()->whereHas('product', fn ($q) => $q->forUser($user->id))->count());
    }

    public function test_sync_template_catalog_is_tenant_scoped(): void
    {
        $userA = $this->activeUser();
        $userB = $this->activeUser();

        $this->createTemplateRows($userA, [['kode_item' => 'IT0096']]);
        $this->createTemplateRows($userB, [['kode_item' => 'IT9999', 'nama_item' => 'Milik B']]);

        $response = $this->actingAs($userA)->postJson(route('products.hpp-mapping.sync-template-catalog'));

        $response->assertOk()->assertJson(['created' => 1, 'existing' => 0]);
        $this->assertSame(1, MasterProduct::query()->forUser($userA->id)->count());
        $this->assertSame(0, MasterProduct::query()->forUser($userB->id)->count());
    }

    public function test_sync_template_catalog_skips_inactive_rows(): void
    {
        $user = $this->activeUser();

        $this->createTemplateRows($user, [
            ['kode_item' => 'IT0096', 'is_active' => true],
            ['kode_item' => 'IT0097', 'is_active' => false],
        ]);

        $this->actingAs($user)->postJson(route('products.hpp-mapping.sync-template-catalog'))
            ->assertOk()
            ->assertJson(['created' => 1, 'existing' => 0]);

        $this->assertSame(1, MasterProduct::query()->forUser($user->id)->count());
    }

    public function test_hpp_mapping_page_lists_synced_template_options(): void
    {
        $user = $this->activeUser();

        $this->createTemplateRows($user, [
            ['kode_item' => 'IT0096', 'nama_item' => '91 Mi Telur 500 gr', 'satuan' => 'PCS', 'conversion_to_base' => 1, 'hpp_amount' => 10000],
        ]);

        $this->actingAs($user)->postJson(route('products.hpp-mapping.sync-template-catalog'))->assertOk();

        $this->actingAs($user)->get(route('products.hpp-mapping'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('templateOptions', 1)
                ->where('templateOptions.0.value', 'IT0096'));
    }

    public function test_sync_template_catalog_requires_authentication(): void
    {
        $this->postJson(route('products.hpp-mapping.sync-template-catalog'))->assertUnauthorized();
    }
}
