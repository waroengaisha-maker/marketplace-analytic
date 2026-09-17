<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\ReportLineIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerSummariesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_endpoint_requires_authentication(): void
    {
        $this->get(route('customers.index'))->assertRedirect('/login');
    }

    public function test_customers_endpoint_exposes_paginated_customer_summaries(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productA = str_repeat('a', 64);
        $productB = str_repeat('b', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'CUS-A',
                'product_key' => $productA,
                'item_index' => 1,
                'discounted_price' => 500,
                'unit_price' => 500,
                'quantity' => 3,
                'returned_quantity' => 1,
                'order_created_at' => '2026-08-15 10:00:00',
                'buyer_username' => 'budi.santoso',
            ]),
            $this->order($user->id, [
                'order_number' => 'CUS-B',
                'product_key' => $productB,
                'item_index' => 2,
                'discounted_price' => 300,
                'unit_price' => 300,
                'quantity' => 2,
                'order_created_at' => '2026-08-16 11:00:00',
                'buyer_username' => 'siti.rahayu',
            ]),
            $this->order($user->id, [
                'order_number' => 'CUS-B',
                'product_key' => $productB,
                'item_index' => 3,
                'discounted_price' => 100,
                'unit_price' => 100,
                'quantity' => 1,
                'order_created_at' => '2026-08-16 11:00:00',
                'buyer_username' => 'siti.rahayu',
            ]),
        ]);

        $response = $this->customersRequest($user, [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'per_page' => 25,
        ]);

        $response->assertOk()->assertJsonStructure([
            'props' => [
                'customers' => [[
                    'buyer_username',
                    'order_count',
                    'line_count',
                    'net_quantity',
                    'subtotal',
                    'total_fee',
                    'penghasilan',
                    'hpp',
                    'laba',
                ]],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                'appliedFrom',
                'appliedTo',
                'summaries' => ['order_count', 'subtotal', 'hpp', 'laba'],
                'details',
            ],
        ]);

        $customers = collect(data_get($response->json(), 'props.customers'));

        $this->assertSame(2, data_get($response->json(), 'props.pagination.total'));
        $this->assertSame(2, data_get($response->json(), 'props.summaries.order_count'));
        $this->assertEquals(1700.0, data_get($response->json(), 'props.summaries.subtotal'));
        $this->assertEquals(0.0, data_get($response->json(), 'props.summaries.hpp'));
        $this->assertEquals(1700.0, data_get($response->json(), 'props.summaries.laba'));

        $budi = $customers->firstWhere('buyer_username', 'budi.santoso');
        $this->assertSame(1, $budi['order_count']);
        $this->assertSame(1, $budi['line_count']);
        $this->assertEquals(2.0, $budi['net_quantity']);
        $this->assertEquals(1000.0, $budi['subtotal']);
        $this->assertEquals(1000.0, $budi['penghasilan']);
        $this->assertEquals(0.0, $budi['hpp']);
        $this->assertEquals(1000.0, $budi['laba']);

        $siti = $customers->firstWhere('buyer_username', 'siti.rahayu');
        $this->assertSame(1, $siti['order_count']);
        $this->assertSame(2, $siti['line_count']);
        $this->assertEquals(3.0, $siti['net_quantity']);
        $this->assertEquals(700.0, $siti['subtotal']);
        $this->assertEquals(700.0, $siti['penghasilan']);
        $this->assertEquals(700.0, $siti['laba']);
    }

    public function test_customers_search_filters_by_buyer_username(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'SEARCH-HIT',
                'item_index' => 1,
                'discounted_price' => 100,
                'unit_price' => 100,
                'quantity' => 2,
                'order_created_at' => '2026-08-15 10:00:00',
                'buyer_username' => 'siti.rahayu',
            ]),
            $this->order($user->id, [
                'order_number' => 'SEARCH-MISS',
                'item_index' => 2,
                'order_created_at' => '2026-08-16 10:00:00',
                'buyer_username' => 'budi.santoso',
            ]),
        ]);

        $response = $this->customersRequest($user, [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'search' => 'siti.rahayu',
        ]);

        $response->assertOk();

        $customers = data_get($response->json(), 'props.customers');
        $this->assertCount(1, $customers);
        $this->assertSame('siti.rahayu', $customers[0]['buyer_username']);
        $this->assertSame(1, data_get($response->json(), 'props.summaries.order_count'));
        $this->assertEquals(200.0, data_get($response->json(), 'props.summaries.subtotal'));
    }

    public function test_customers_groups_blank_username_under_fallback(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'BLANK-1', 'item_index' => 1, 'buyer_username' => null, 'order_created_at' => '2026-08-15 10:00:00']),
            $this->order($user->id, ['order_number' => 'BLANK-2', 'item_index' => 2, 'buyer_username' => '', 'order_created_at' => '2026-08-16 10:00:00']),
        ]);

        $response = $this->customersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $response->assertOk();

        $customers = data_get($response->json(), 'props.customers');
        $this->assertCount(1, $customers);
        $this->assertSame('(tanpa username)', $customers[0]['buyer_username']);
        $this->assertSame(2, $customers[0]['order_count']);
        $this->assertSame(2, $customers[0]['line_count']);
    }

    public function test_customers_detail_returns_item_rows_per_transaction(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productA = str_repeat('h', 64);
        $variationL = str_repeat('v', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'DET-HISTORY-1',
                'product_name' => 'Sepatu Lari',
                'product_key' => $productA,
                'variation_name' => 'Size L',
                'variation_key' => $variationL,
                'item_index' => 1,
                'discounted_price' => 250,
                'unit_price' => 250,
                'quantity' => 4,
                'returned_quantity' => 1,
                'order_created_at' => '2026-08-10 09:00:00',
                'buyer_username' => 'dew.lestari',
            ]),
            $this->order($user->id, [
                'order_number' => 'DET-HISTORY-2',
                'product_name' => 'Sepatu Lari',
                'product_key' => $productA,
                'variation_name' => 'Size L',
                'variation_key' => $variationL,
                'item_index' => 2,
                'discounted_price' => 250,
                'unit_price' => 250,
                'quantity' => 1,
                'order_created_at' => '2026-08-11 09:00:00',
                'buyer_username' => 'dew.lestari',
            ]),
            $this->order($user->id, [
                'order_number' => 'DET-OTHER-BUYER',
                'item_index' => 3,
                'order_created_at' => '2026-08-12 09:00:00',
                'buyer_username' => 'siti.rahayu',
            ]),
        ]);

        $response = $this->customersRequest($user, [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'customer' => 'dew.lestari',
        ]);

        $response->assertOk();

        $details = data_get($response->json(), 'props.details');
        $this->assertSame('dew.lestari', $details['buyer_username']);
        $this->assertCount(2, $details['rows']);

        $row1 = $details['rows'][0];
        $this->assertSame('DET-HISTORY-2', $row1['order_number']);
        $this->assertSame('2026-08-11 09:00:00', $row1['order_created_at']);
        $this->assertSame('Sepatu Lari', $row1['product_name']);
        $this->assertSame('Size L', $row1['variation_name']);
        $this->assertEquals(1.0, $row1['net_quantity']);
        $this->assertEquals(250.0, $row1['subtotal']);
        $this->assertEquals(250.0, $row1['laba']);

        $row2 = $details['rows'][1];
        $this->assertSame('DET-HISTORY-1', $row2['order_number']);
        $this->assertSame('2026-08-10 09:00:00', $row2['order_created_at']);
        $this->assertSame('Sepatu Lari', $row2['product_name']);
        $this->assertSame('Size L', $row2['variation_name']);
        $this->assertEquals(3.0, $row2['net_quantity']);
        $this->assertEquals(750.0, $row2['subtotal']);
        $this->assertEquals(750.0, $row2['laba']);
    }

    public function test_customers_include_hpp_allocations_in_totals_and_laba(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('c', 64);
        $variationKey = str_repeat('d', 64);

        $lineIdentity = ReportLineIdentity::make('CUS-HPP', $productKey, $variationKey, 100.0, 5);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'CUS-HPP',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 200,
            'discounted_price' => 100,
            'quantity' => 5,
            'line_identity' => $lineIdentity,
            'order_created_at' => '2026-08-10 09:00:00',
            'buyer_username' => 'hpp.buyer',
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'CUS-HPP',
            'product_key' => $productKey,
            'item_index' => 200,
            'product_price' => 100,
            'quantity' => 5,
            'total_income' => 400,
            'line_identity' => $lineIdentity,
        ]));

        DB::table('order_cost_allocations')->insert([
            'user_id' => $user->id,
            'order_line_identity' => $lineIdentity,
            'hpp_per_base_unit' => 20,
            'quantity_base_unit' => 5,
            'total_hpp' => 100,
            'cost_status' => 'ok',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->customersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $response->assertOk();

        $customer = collect(data_get($response->json(), 'props.customers'))->firstWhere('buyer_username', 'hpp.buyer');
        $this->assertEquals(100.0, $customer['hpp']);
        $this->assertEquals(400.0, $customer['laba']);

        $this->assertEquals(100.0, data_get($response->json(), 'props.summaries.hpp'));
        $this->assertEquals(400.0, data_get($response->json(), 'props.summaries.laba'));
    }

    public function test_customers_without_date_range_includes_all_order_dates(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'NO-FILTER-1', 'item_index' => 1, 'order_created_at' => now()->subMonth()->startOfMonth()->format('Y-m-d 10:00:00'), 'buyer_username' => 'old.buyer']),
            $this->order($user->id, ['order_number' => 'NO-FILTER-2', 'item_index' => 2, 'order_created_at' => now()->format('Y-m-d 10:00:00'), 'buyer_username' => 'new.buyer']),
        ]);

        $response = $this->customersRequest($user);

        $response->assertOk();
        $this->assertSame(2, data_get($response->json(), 'props.pagination.total'));
        $this->assertSame(2, data_get($response->json(), 'props.summaries.order_count'));

        $names = collect(data_get($response->json(), 'props.customers'))->pluck('buyer_username')->all();
        $this->assertContains('old.buyer', $names);
        $this->assertContains('new.buyer', $names);

        $this->assertNull(data_get($response->json(), 'props.appliedFrom'));
        $this->assertNull(data_get($response->json(), 'props.appliedTo'));
    }

    public function test_customers_export_data_returns_all_customers_within_date_range(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        $rows = [];
        for ($i = 1; $i <= 30; $i++) {
            $rows[] = $this->order($user->id, [
                'order_number' => 'CUS-EXP-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'item_index' => $i,
                'order_created_at' => '2026-08-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT).' 10:00:00',
                'buyer_username' => 'buyer.'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }
        $rows[] = $this->order($user->id, [
            'order_number' => 'CUS-EXP-OUTSIDE',
            'item_index' => 31,
            'order_created_at' => '2026-09-10 10:00:00',
            'buyer_username' => 'buyer.outside',
        ]);
        DB::table('marketplace_orders')->insert($rows);

        $response = $this->actingAs($user)->get(route('customers.export-data', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]));

        $response->assertOk()->assertJsonStructure(['customers']);

        $customers = data_get($response->json(), 'customers');
        $this->assertCount(30, $customers);

        $names = collect($customers)->pluck('buyer_username')->all();
        $this->assertNotContains('buyer.outside', $names);
        $this->assertContains('buyer.01', $names);
        $this->assertContains('buyer.30', $names);
    }

    public function test_customers_supports_sorting_by_supported_columns(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'SORT-A1', 'item_index' => 1, 'discounted_price' => 100, 'unit_price' => 100, 'quantity' => 1, 'order_created_at' => '2026-08-15 10:00:00', 'buyer_username' => 'dev.buyer']),
            $this->order($user->id, ['order_number' => 'SORT-A2', 'item_index' => 2, 'discounted_price' => 50, 'unit_price' => 50, 'quantity' => 1, 'order_created_at' => '2026-08-16 10:00:00', 'buyer_username' => 'dev.buyer']),
            $this->order($user->id, ['order_number' => 'SORT-B1', 'item_index' => 3, 'discounted_price' => 200, 'unit_price' => 200, 'quantity' => 1, 'order_created_at' => '2026-08-17 10:00:00', 'buyer_username' => 'ave.buyer']),
        ]);

        $response = $this->customersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31', 'sort_field' => 'order_count', 'sort_order' => 'asc']);
        $response->assertOk();
        $customers = data_get($response->json(), 'props.customers');
        $this->assertSame(['ave.buyer', 'dev.buyer'], collect($customers)->pluck('buyer_username')->all());

        $response = $this->customersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31', 'sort_field' => 'order_count', 'sort_order' => 'desc']);
        $response->assertOk();

        foreach (['line_count', 'net_quantity', 'subtotal', 'total_fee', 'penghasilan', 'hpp', 'laba', 'buyer_username'] as $field) {
            $response = $this->customersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31', 'sort_field' => $field, 'sort_order' => 'asc']);
            $response->assertOk();
        }
    }

    public function test_customers_default_sort_is_laba_descending(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'DEFAULT-SMALL',
                'item_index' => 1,
                'discounted_price' => 50,
                'unit_price' => 50,
                'quantity' => 1,
                'order_created_at' => '2026-08-15 10:00:00',
                'buyer_username' => 'kecil.buyer',
            ]),
            $this->order($user->id, [
                'order_number' => 'DEFAULT-BIG',
                'item_index' => 2,
                'discounted_price' => 200,
                'unit_price' => 200,
                'quantity' => 1,
                'order_created_at' => '2026-08-16 10:00:00',
                'buyer_username' => 'besar.buyer',
            ]),
        ]);

        $response = $this->customersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31']);

        $response->assertOk();

        $customers = data_get($response->json(), 'props.customers');
        $this->assertSame(['besar.buyer', 'kecil.buyer'], collect($customers)->pluck('buyer_username')->all());
        $this->assertEquals(200.0, $customers[0]['laba']);
        $this->assertEquals(50.0, $customers[1]['laba']);
    }

    private function customersRequest(User $user, array $params = []): TestResponse
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('customers.index')));

        return $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('customers.index', $params));
    }

    private function order(int $userId, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $userId,
            'order_number' => 'ORDER',
            'order_status' => 'Selesai',
            'tracking_number' => 'TRACKING',
            'buyer_username' => null,
            'product_name' => 'Product',
            'product_key' => str_repeat('f', 64),
            'variation_key' => null,
            'variation_name' => null,
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
            'item_index' => null,
            'product_name' => 'Product',
            'product_key' => str_repeat('f', 64),
            'variation_key' => null,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 100,
            'refund_to_buyer' => 0,
            'raw_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }
}
