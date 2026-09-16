<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\MarketplaceReconciliationService;
use App\Services\ReportLineIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderSummaryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_endpoint_requires_authentication(): void
    {
        $this->get(route('orders.index'))->assertRedirect('/login');
    }

    public function test_orders_endpoint_exposes_paginated_order_summaries(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('o', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'SUM-A',
                'product_key' => $productKey,
                'item_index' => 1,
                'discounted_price' => 500,
                'unit_price' => 500,
                'quantity' => 3,
                'returned_quantity' => 1,
                'order_created_at' => '2026-08-15 10:00:00',
                'buyer_username' => 'budi.santoso',
            ]),
            $this->order($user->id, [
                'order_number' => 'SUM-B',
                'product_key' => str_repeat('p', 64),
                'item_index' => 2,
                'discounted_price' => 300,
                'unit_price' => 300,
                'quantity' => 2,
                'order_created_at' => '2026-08-16 11:00:00',
            ]),
            $this->order($user->id, [
                'order_number' => 'SUM-B',
                'product_key' => str_repeat('p', 64),
                'item_index' => 3,
                'discounted_price' => 100,
                'unit_price' => 100,
                'quantity' => 1,
                'order_created_at' => '2026-08-16 11:00:00',
            ]),
        ]);

        DB::table('marketplace_income')->insert([
            $this->income($user->id, [
                'order_number' => 'SUM-A',
                'product_key' => $productKey,
                'item_index' => 1,
                'product_price' => 500,
                'quantity' => 3,
                'total_income' => 620,
            ]),
            $this->income($user->id, [
                'order_number' => 'SUM-B',
                'product_key' => str_repeat('p', 64),
                'item_index' => 2,
                'product_price' => 300,
                'quantity' => 2,
                'total_income' => 250,
            ]),
            $this->income($user->id, [
                'order_number' => 'SUM-B',
                'product_key' => str_repeat('p', 64),
                'item_index' => 3,
                'product_price' => 100,
                'quantity' => 1,
                'total_income' => 80,
            ]),
        ]);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'per_page' => 25,
        ]));

        $response->assertOk()->assertJsonStructure([
            'props' => [
                'orders' => [[
                    'order_number',
                    'order_created_at',
                    'buyer_username',
                    'business_status',
                    'line_count',
                    'quantity',
                    'net_quantity',
                    'discounted_price',
                    'subtotal',
                    'admin',
                    'shipping',
                    'promo',
                    'processing',
                    'tax',
                    'total_fee',
                    'penghasilan',
                    'hpp',
                    'laba',
                ]],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                'appliedFrom',
                'appliedTo',
                'summaries' => ['subtotal', 'total_fee', 'tax', 'penghasilan', 'hpp', 'laba'],
                'details',
            ],
        ]);

        $orders = collect(data_get($response->json(), 'props.orders'));
        $ordersByNumber = $orders->keyBy('order_number');

        $this->assertSame(2, data_get($response->json(), 'props.pagination.total'));

        $this->assertEquals(1700.0, data_get($response->json(), 'props.summaries.subtotal'));
        $this->assertEquals(0.0, data_get($response->json(), 'props.summaries.total_fee'));
        $this->assertEquals(0.0, data_get($response->json(), 'props.summaries.tax'));
        $this->assertEquals(1700.0, data_get($response->json(), 'props.summaries.penghasilan'));
        $this->assertEquals(0.0, data_get($response->json(), 'props.summaries.hpp'));
        $this->assertEquals(1700.0, data_get($response->json(), 'props.summaries.laba'));

        $orderA = $ordersByNumber->get('SUM-A');
        $this->assertSame('budi.santoso', $orderA['buyer_username']);
        $this->assertSame(1, $orderA['line_count']);
        $this->assertSame(3, $orderA['quantity']);
        $this->assertSame(2, $orderA['net_quantity']);
        $this->assertEquals(500.0, $orderA['discounted_price']);
        $this->assertEquals(1000.0, $orderA['subtotal']);
        $this->assertEquals(1000.0, $orderA['penghasilan']);
        $this->assertEquals(0.0, $orderA['hpp']);
        $this->assertEquals(1000.0, $orderA['laba']);

        $orderB = $ordersByNumber->get('SUM-B');
        $this->assertSame(2, $orderB['line_count']);
        $this->assertSame(3, $orderB['net_quantity']);
        $this->assertEquals(233.33, $orderB['discounted_price']);
        $this->assertEquals(700.0, $orderB['subtotal']);
        $this->assertEquals(700.0, $orderB['penghasilan']);
        $this->assertEquals(0.0, $orderB['hpp']);
        $this->assertEquals(700.0, $orderB['laba']);
    }

    public function test_orders_search_filters_by_order_number(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'SEARCH-HIT', 'item_index' => 1, 'order_created_at' => '2026-08-15 10:00:00']),
            $this->order($user->id, ['order_number' => 'SEARCH-MISS', 'item_index' => 2, 'order_created_at' => '2026-08-16 10:00:00']),
        ]);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['from' => '2026-08-01', 'to' => '2026-08-31', 'search' => 'SEARCH-HIT']));

        $response->assertOk();

        $orders = data_get($response->json(), 'props.orders');
        $this->assertCount(1, $orders);
        $this->assertSame('SEARCH-HIT', $orders[0]['order_number']);
        $this->assertEquals(100.0, data_get($response->json(), 'props.summaries.subtotal'));
    }

    public function test_orders_summaries_totals_reflect_active_filters(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'SUM-FILTER-HIT',
                'item_index' => 1,
                'discounted_price' => 200,
                'unit_price' => 200,
                'quantity' => 2,
                'order_created_at' => '2026-08-15 10:00:00',
            ]),
            $this->order($user->id, [
                'order_number' => 'SUM-FILTER-MISS',
                'item_index' => 2,
                'order_created_at' => '2026-09-05 10:00:00',
            ]),
        ]);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['from' => '2026-08-01', 'to' => '2026-08-31', 'search' => 'SUM-FILTER-HIT']));

        $response->assertOk();

        $orders = data_get($response->json(), 'props.orders');
        $this->assertCount(1, $orders);
        $this->assertSame('SUM-FILTER-HIT', $orders[0]['order_number']);
        $this->assertEquals(400.0, data_get($response->json(), 'props.summaries.subtotal'));
        $this->assertEquals(0.0, data_get($response->json(), 'props.summaries.total_fee'));
        $this->assertEquals(400.0, data_get($response->json(), 'props.summaries.penghasilan'));
        $this->assertEquals(0.0, data_get($response->json(), 'props.summaries.hpp'));
        $this->assertEquals(400.0, data_get($response->json(), 'props.summaries.laba'));
    }

    public function test_orders_detail_returns_per_item_rows(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('x', 64);
        $variationKey = str_repeat('y', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'DET-ORDER',
                'product_key' => $productKey,
                'variation_key' => $variationKey,
                'variation_name' => 'Size L',
                'item_index' => 1,
                'discounted_price' => 250,
                'unit_price' => 250,
                'quantity' => 4,
                'returned_quantity' => 1,
                'order_created_at' => '2026-08-10 09:00:00',
            ]),
            $this->order($user->id, [
                'order_number' => 'DET-ORDER',
                'product_key' => $productKey,
                'variation_key' => str_repeat('z', 64),
                'variation_name' => 'Size M',
                'item_index' => 2,
                'discounted_price' => 150,
                'unit_price' => 150,
                'quantity' => 2,
                'order_created_at' => '2026-08-10 09:00:00',
            ]),
        ]);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['order' => 'DET-ORDER', 'from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();

        $details = data_get($response->json(), 'props.details');
        $this->assertSame('DET-ORDER', $details['order_number']);
        $this->assertCount(2, $details['rows']);

        $row1 = collect($details['rows'])->firstWhere('variation_name', 'Size L');
        $this->assertSame(3, $row1['net_quantity']);
        $this->assertEquals(750.0, $row1['order_subtotal']);
        $this->assertSame('no_allocation', $row1['hpp_status']);
        $this->assertEquals(750.0, $row1['laba']);

        $row2 = collect($details['rows'])->firstWhere('variation_name', 'Size M');
        $this->assertSame(2, $row2['net_quantity']);
        $this->assertEquals(300.0, $row2['order_subtotal']);
        $this->assertEquals(300.0, $row2['laba']);
    }

    public function test_orders_endpoint_accepts_date_range(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'DATE-IN-RANGE',
                'item_index' => 1,
                'order_created_at' => '2026-08-15 10:00:00',
            ]),
            $this->order($user->id, [
                'order_number' => 'DATE-OUT-RANGE',
                'item_index' => 2,
                'order_created_at' => '2026-09-01 10:00:00',
            ]),
        ]);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();

        $orders = data_get($response->json(), 'props.orders');
        $this->assertCount(1, $orders);
        $this->assertSame('DATE-IN-RANGE', $orders[0]['order_number']);
        $this->assertSame(1, data_get($response->json(), 'props.pagination.total'));
    }

    public function test_orders_summary_totals_match_line_level_calculate_financials(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('t', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'SUM-FIN',
                'product_key' => $productKey,
                'item_index' => 100,
                'discounted_price' => 500,
                'unit_price' => 500,
                'quantity' => 3,
                'returned_quantity' => 1,
                'order_created_at' => '2026-08-20 12:00:00',
            ]),
        ]);

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'SUM-FIN',
            'product_key' => $productKey,
            'item_index' => 100,
            'product_price' => 500,
            'quantity' => 3,
            'total_income' => 810,
            'refund_to_buyer' => 0,
            'platform_fee' => -100,
            'free_shipping_xtra_fee' => -50,
            'promo_xtra_service_fee' => -25,
            'order_processing_fee' => -10,
            'pph22' => -5,
        ]));

        $lineRow = app(MarketplaceReconciliationService::class)
            ->orderLines($user->id, 'SUM-FIN')[0];

        $this->assertSame(2.0, $lineRow->net_quantity);
        $this->assertSame(1000.0, $lineRow->order_subtotal);
        $this->assertSame(-100.0, $lineRow->admin);
        $this->assertSame(-50.0, $lineRow->shipping);
        $this->assertSame(-25.0, $lineRow->promo);
        $this->assertSame(-10.0, $lineRow->processing);
        $this->assertSame(-5.0, $lineRow->tax);
        $this->assertSame(810.0, $lineRow->penghasilan);
        $this->assertSame(810.0, $lineRow->laba);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();

        $order = collect(data_get($response->json(), 'props.orders'))->first();
        $this->assertSame('SUM-FIN', $order['order_number']);
        $this->assertSame(1, $order['line_count']);
        $this->assertEquals(1000.0, $order['subtotal']);
        $this->assertEquals(-185.0, $order['total_fee']);
        $this->assertEquals(-5.0, $order['tax']);
        $this->assertEquals(810.0, $order['penghasilan']);
        $this->assertEquals(810.0, $order['laba']);
    }

    public function test_orders_without_date_range_defaults_to_current_month_through_today(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'NO-FILTER-1', 'item_index' => 1, 'order_created_at' => now()->subMonth()->startOfMonth()->format('Y-m-d 10:00:00')]),
            $this->order($user->id, ['order_number' => 'NO-FILTER-2', 'item_index' => 2, 'order_created_at' => now()->format('Y-m-d 10:00:00')]),
        ]);

        $response = $this->ordersRequest($user);

        $response->assertOk();
        $this->assertSame(1, data_get($response->json(), 'props.pagination.total'));
        $this->assertSame('NO-FILTER-2', data_get($response->json(), 'props.orders.0.order_number'));
        $this->assertSame(now()->startOfMonth()->format('Y-m-d'), data_get($response->json(), 'props.appliedFrom'));
        $this->assertSame(now()->format('Y-m-d'), data_get($response->json(), 'props.appliedTo'));
    }

    public function test_orders_statuses_filter_limits_orders_and_totals(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'STATUS-UNMATCHED',
                'order_status' => 'Selesai',
                'item_index' => 1,
                'discounted_price' => 150,
                'unit_price' => 150,
                'quantity' => 2,
                'order_created_at' => '2026-08-15 10:00:00',
            ]),
            $this->order($user->id, [
                'order_number' => 'STATUS-CANCELLED',
                'order_status' => 'Batal',
                'item_index' => 2,
                'discounted_price' => 100,
                'unit_price' => 100,
                'quantity' => 1,
                'order_created_at' => '2026-08-16 10:00:00',
            ]),
        ]);

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['statuses' => ['Unmatched'], 'from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();

        $orders = data_get($response->json(), 'props.orders');
        $this->assertCount(1, $orders);
        $this->assertSame('STATUS-UNMATCHED', $orders[0]['order_number']);
        $this->assertSame(1, data_get($response->json(), 'props.pagination.total'));
        $this->assertEquals(300.0, data_get($response->json(), 'props.summaries.subtotal'));
        $this->assertEquals(300.0, data_get($response->json(), 'props.summaries.penghasilan'));
    }

    public function test_orders_detail_with_hpp_allocations_shows_ok_status(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('q', 64);
        $variationKey = str_repeat('r', 64);

        $lineIdentity = ReportLineIdentity::make('HPP-DETAIL', $productKey, $variationKey, 100.0, 5);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'HPP-DETAIL',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 200,
            'discounted_price' => 100,
            'quantity' => 5,
            'line_identity' => $lineIdentity,
            'order_created_at' => '2026-08-10 09:00:00',
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'HPP-DETAIL',
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

        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', ['order' => 'HPP-DETAIL', 'from' => '2026-08-01', 'to' => '2026-08-31']));

        $response->assertOk();

        $row = collect(data_get($response->json(), 'props.details.rows'))->first();
        $this->assertSame('ok', $row['hpp_status']);
        $this->assertEquals(100.0, $row['hpp']);
        $this->assertEquals(400.0, $row['laba']);
    }

    public function test_orders_export_lines_returns_item_rows_for_filtered_orders(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('e', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'EXP-ORDER',
                'product_key' => $productKey,
                'variation_key' => str_repeat('v', 64),
                'variation_name' => 'Size XL',
                'item_index' => 1,
                'discounted_price' => 500,
                'unit_price' => 500,
                'quantity' => 3,
                'returned_quantity' => 1,
                'buyer_username' => 'aina.putri',
                'order_created_at' => '2026-08-15 10:00:00',
            ]),
            $this->order($user->id, [
                'order_number' => 'EXP-OTHER',
                'item_index' => 2,
                'order_created_at' => '2026-09-10 10:00:00',
            ]),
        ]);

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'EXP-ORDER',
            'product_key' => $productKey,
            'item_index' => 1,
            'product_price' => 500,
            'quantity' => 3,
            'total_income' => 1310,
            'platform_fee' => -100,
            'free_shipping_xtra_fee' => -50,
            'promo_xtra_service_fee' => -25,
            'order_processing_fee' => -10,
            'pph22' => -5,
        ]));

        $response = $this->actingAs($user)->get(route('orders.export-lines', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]));

        $response->assertOk()->assertJsonStructure([
            'rows' => [[
                'order_number',
                'order_created_at',
                'buyer_username',
                'order_product_name',
                'variation_name',
                'net_quantity',
                'discounted_price',
                'order_subtotal',
                'admin',
                'shipping',
                'promo',
                'processing',
                'tax',
                'total_fee',
                'penghasilan',
                'hpp',
                'hpp_status',
                'laba',
            ]],
        ]);

        $rows = data_get($response->json(), 'rows');
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame('EXP-ORDER', $row['order_number']);
        $this->assertSame('aina.putri', $row['buyer_username']);
        $this->assertSame('Size XL', $row['variation_name']);
        $this->assertSame(2, $row['net_quantity']);
        $this->assertEquals(1000.0, $row['order_subtotal']);
        $this->assertEquals(-100.0, $row['admin']);
        $this->assertEquals(-50.0, $row['shipping']);
        $this->assertEquals(-25.0, $row['promo']);
        $this->assertEquals(-10.0, $row['processing']);
        $this->assertEquals(-5.0, $row['tax']);
        $this->assertEquals(-185.0, $row['total_fee']);
        $this->assertEquals(810.0, $row['penghasilan']);
        $this->assertEquals(0.0, $row['hpp']);
        $this->assertSame('no_allocation', $row['hpp_status']);
        $this->assertEquals(810.0, $row['laba']);
    }

    public function test_orders_search_matches_buyer_username(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'BUYER-HIT', 'item_index' => 1, 'buyer_username' => 'siti.rahayu', 'order_created_at' => '2026-08-15 10:00:00']),
            $this->order($user->id, ['order_number' => 'BUYER-MISS', 'item_index' => 2, 'buyer_username' => 'budy.andika', 'order_created_at' => '2026-08-16 10:00:00']),
        ]);

        $response = $this->ordersRequest($user, ['from' => '2026-08-01', 'to' => '2026-08-31', 'search' => 'siti.rahayu']);

        $response->assertOk();

        $orders = data_get($response->json(), 'props.orders');
        $this->assertCount(1, $orders);
        $this->assertSame('BUYER-HIT', $orders[0]['order_number']);
    }

    public function test_orders_export_data_returns_all_orders_within_date_range(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        $rows = [];
        for ($i = 1; $i <= 30; $i++) {
            $rows[] = $this->order($user->id, [
                'order_number' => 'EXP-ALL-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'item_index' => $i,
                'order_created_at' => '2026-08-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT).' 10:00:00',
            ]);
        }
        $rows[] = $this->order($user->id, [
            'order_number' => 'EXP-OUTSIDE',
            'item_index' => 31,
            'order_created_at' => '2026-09-10 10:00:00',
        ]);
        DB::table('marketplace_orders')->insert($rows);

        $response = $this->actingAs($user)->get(route('orders.export-data', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ]));

        $response->assertOk()->assertJsonStructure(['orders']);

        $orders = data_get($response->json(), 'orders');
        $this->assertCount(30, $orders);

        $numbers = collect($orders)->pluck('order_number')->all();
        $this->assertNotContains('EXP-OUTSIDE', $numbers);
        $this->assertContains('EXP-ALL-01', $numbers);
        $this->assertContains('EXP-ALL-30', $numbers);
    }

    public function test_orders_supports_sorting_by_every_column(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, ['order_number' => 'SORT-2', 'item_index' => 1, 'quantity' => 1, 'discounted_price' => 100, 'unit_price' => 100, 'order_created_at' => now()->subWeek()->format('Y-m-d H:i:s')]),
            $this->order($user->id, ['order_number' => 'SORT-1', 'item_index' => 2, 'quantity' => 3, 'discounted_price' => 200, 'unit_price' => 200, 'order_created_at' => now()->format('Y-m-d H:i:s')]),
        ]);

        $response = $this->ordersRequest($user, ['sort_field' => 'net_quantity', 'sort_order' => 'asc']);
        $response->assertOk();
        $orders = data_get($response->json(), 'props.orders');
        $this->assertSame(['SORT-2', 'SORT-1'], collect($orders)->pluck('order_number')->all());

        $response = $this->ordersRequest($user, ['sort_field' => 'business_status', 'sort_order' => 'asc']);
        $response->assertOk();

        $response = $this->ordersRequest($user, ['sort_field' => 'buyer_username', 'sort_order' => 'desc']);
        $response->assertOk();

        $response = $this->ordersRequest($user, ['sort_field' => 'total_fee', 'sort_order' => 'asc']);
        $response->assertOk();
    }

    private function ordersRequest(User $user, array $params = []): TestResponse
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create(route('orders.index')));

        return $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('orders.index', $params));
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
