<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\UploadReportsRequest;
use App\Models\User;
use App\Services\IncomeReconciliationService;
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
use Illuminate\Support\Facades\Schema;
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

    public function test_income_reconciliation_preserves_one_row_per_income_and_classifies_kewpie_orphan(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('k', 64);

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'KEWPIE-ORPHAN',
            'product_name' => 'Kewpie',
            'product_key' => $productKey,
            'product_price' => 543000,
            'unit_price' => 543000,
            'total_income' => 299155,
            'refund_to_buyer' => -181000,
        ]));

        $page = app(IncomeReconciliationService::class)->page($user->id, null, null, ['per_page' => 25]);
        $row = collect($page->items())->first();

        $this->assertSame(1, $page->total());
        $this->assertSame('Orphan', $row->income_match_status);
        $this->assertSame('None', $row->match_method);
        $this->assertSame('None', $row->match_confidence);
        $this->assertSame(-181000.0, (float) $row->refund_amount);
        $this->assertSame('Partial', $row->refund_type);
        $this->assertSame(299155.0, (float) $row->total_income);
    }

    public function test_income_refund_with_one_compatible_order_is_exact_without_grouped_allocation(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('d', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'DOWNY-EXACT-REFUND',
            'product_name' => 'Downy',
            'product_key' => $productKey,
            'variation_key' => str_repeat('v', 64),
            'variation_name' => 'Sunrise Fresh',
            'discounted_price' => 38000,
            'unit_price' => 38000,
            'quantity' => 1,
            'item_index' => 1001,
        ]));
        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'DOWNY-EXACT-REFUND',
            'product_name' => 'Downy',
            'product_key' => $productKey,
            'product_price' => 38000,
            'unit_price' => 38000,
            'quantity' => 1,
            'item_index' => 1001,
            'total_income' => 0,
            'refund_to_buyer' => -38000,
        ]));

        $row = app(IncomeReconciliationService::class)->page($user->id, null, null, ['per_page' => 25])->items()[0];

        $this->assertSame('Matched', $row->income_match_status);
        $this->assertSame('Exact', $row->match_method);
        $this->assertSame('Exact', $row->match_confidence);
        $this->assertSame('Full', $row->refund_type);
        $this->assertSame('-38000.00', $row->refund_amount);
    }

    public function test_income_matching_keeps_refund_and_candidate_outcomes_independent(): void
    {
        $user = User::factory()->create();
        $exactKey = str_repeat('e', 64);
        $groupedKey = str_repeat('g', 64);
        $ambiguousKey = str_repeat('a', 64);
        $partialKey = str_repeat('p', 64);

        DB::table('marketplace_orders')->insert([
            $this->order($user->id, [
                'order_number' => 'INCOME-EXACT',
                'product_name' => 'Exact',
                'product_key' => $exactKey,
                'variation_key' => str_repeat('x', 64),
                'item_index' => 1001,
                'discounted_price' => 100,
                'unit_price' => 100,
            ]),
            $this->order($user->id, [
                'order_number' => 'INCOME-GROUPED',
                'product_name' => 'Grouped',
                'product_key' => $groupedKey,
                'variation_key' => str_repeat('y', 64),
                'item_index' => 2002,
                'discounted_price' => 200,
                'unit_price' => 200,
            ]),
            $this->order($user->id, [
                'order_number' => 'INCOME-AMBIGUOUS',
                'product_name' => 'Ambiguous',
                'product_key' => $ambiguousKey,
                'variation_key' => str_repeat('m', 64),
                'item_index' => 3003,
                'discounted_price' => 300,
                'unit_price' => 300,
            ]),
            $this->order($user->id, [
                'order_number' => 'INCOME-AMBIGUOUS',
                'product_name' => 'Ambiguous',
                'product_key' => $ambiguousKey,
                'variation_key' => str_repeat('n', 64),
                'item_index' => 3003,
                'discounted_price' => 300,
                'unit_price' => 300,
            ]),
            $this->order($user->id, [
                'order_number' => 'INCOME-PARTIAL',
                'product_name' => 'Partial',
                'product_key' => $partialKey,
                'variation_key' => str_repeat('z', 64),
                'item_index' => 4004,
                'discounted_price' => 400,
                'unit_price' => 400,
            ]),
        ]);

        DB::table('marketplace_income')->insert([
            $this->income($user->id, ['order_number' => 'INCOME-EXACT', 'product_name' => 'Exact', 'product_key' => $exactKey, 'variation_key' => str_repeat('x', 64), 'product_price' => 100, 'unit_price' => 100, 'item_index' => 1001]),
            $this->income($user->id, ['order_number' => 'INCOME-GROUPED', 'product_name' => 'Grouped', 'product_key' => $groupedKey, 'product_price' => 200, 'unit_price' => 200, 'item_index' => 2002]),
            $this->income($user->id, ['order_number' => 'INCOME-AMBIGUOUS', 'product_name' => 'Ambiguous', 'product_key' => $ambiguousKey, 'product_price' => 300, 'unit_price' => 300, 'item_index' => 3003]),
            $this->income($user->id, ['order_number' => 'INCOME-PARTIAL', 'product_name' => 'Partial', 'product_key' => $partialKey, 'product_price' => 400, 'unit_price' => 400, 'item_index' => 4004, 'total_income' => 200, 'refund_to_buyer' => -100]),
            $this->income($user->id, ['order_number' => 'INCOME-ZERO', 'product_name' => 'Zero', 'product_key' => str_repeat('0', 64), 'product_price' => 500, 'unit_price' => 500, 'total_income' => 0, 'refund_to_buyer' => 0, 'item_index' => null]),
        ]);

        $rows = collect(app(IncomeReconciliationService::class)->page($user->id, null, null, ['per_page' => 25])->items())->keyBy('order_number');

        $this->assertSame(['Matched', 'Exact', 'Exact'], [$rows['INCOME-EXACT']->income_match_status, $rows['INCOME-EXACT']->match_method, $rows['INCOME-EXACT']->match_confidence]);
        $this->assertSame(['Matched', 'Grouped', 'Grouped'], [$rows['INCOME-GROUPED']->income_match_status, $rows['INCOME-GROUPED']->match_method, $rows['INCOME-GROUPED']->match_confidence]);
        $this->assertSame(['Ambiguous', 'None', 'Ambiguous'], [$rows['INCOME-AMBIGUOUS']->income_match_status, $rows['INCOME-AMBIGUOUS']->match_method, $rows['INCOME-AMBIGUOUS']->match_confidence]);
        $this->assertSame(['Matched', 'Exact', 'Exact', 'Partial'], [$rows['INCOME-PARTIAL']->income_match_status, $rows['INCOME-PARTIAL']->match_method, $rows['INCOME-PARTIAL']->match_confidence, $rows['INCOME-PARTIAL']->refund_type]);
        $this->assertSame(['Orphan', 'None', 'None'], [$rows['INCOME-ZERO']->income_match_status, $rows['INCOME-ZERO']->match_method, $rows['INCOME-ZERO']->match_confidence]);
        $this->assertSame(0, $rows->filter(fn ($row) => (float) ($row->refund_amount ?? 0) < 0 && $row->match_method === 'Grouped')->count());
    }

    public function test_income_reconciliation_endpoint_requires_authentication(): void
    {
        $this->get(route('finance.income-reconciliation'))->assertRedirect('/login');
    }

    public function test_income_reconciliation_endpoint_exposes_paginated_contract_and_filters(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
            'trial_ends_at' => now()->addDay(),
        ]);
        $productKey = str_repeat('i', 64);

        DB::table('marketplace_income')->insert([
            $this->income($user->id, [
                'order_number' => 'HTTP-ORPHAN',
                'product_name' => 'HTTP Product',
                'product_key' => $productKey,
                'total_income' => 100,
                'refund_to_buyer' => -25,
            ]),
            $this->income($user->id, [
                'order_number' => 'HTTP-NORMAL',
                'product_name' => 'Normal Product',
                'product_key' => str_repeat('n', 64),
                'total_income' => 100,
                'refund_to_buyer' => 0,
            ]),
        ]);

        $request = Request::create(route('finance.income-reconciliation'));
        $version = app(HandleInertiaRequests::class)->version($request);
        $response = $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->get(route('finance.income-reconciliation', [
            'per_page' => 25,
            'statuses' => ['Orphan'],
            'refund_type' => 'Partial',
            'search' => 'HTTP-ORPHAN',
        ]));

        $response->assertOk()->assertJsonStructure([
            'props' => [
                'rows' => [[
                    'income_match_status',
                    'match_method',
                    'match_confidence',
                    'refund_amount',
                    'refund_type',
                    'order_number',
                    'product_name',
                    'product_key',
                    'variation_key',
                    'item_index',
                    'product_price',
                    'unit_price',
                    'quantity',
                    'total_income',
                ]],
                'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                'filters',
            ],
        ]);
        $response->assertJsonPath('props.pagination.total', 1);
        $response->assertJsonPath('props.rows.0.order_number', 'HTTP-ORPHAN');
        $response->assertJsonPath('props.rows.0.income_match_status', 'Orphan');
        $response->assertJsonPath('props.rows.0.refund_type', 'Partial');
    }

    public function test_exact_match_exposes_settled_business_status_and_exact_match_contract(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('a', 64);
        $variationKey = str_repeat('b', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-CONTRACT-EXACT',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 101,
            'unit_price' => 100,
            'discounted_price' => 80,
            'quantity' => 2,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-CONTRACT-EXACT',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 999,
            'product_price' => 160,
            'quantity' => 2,
            'total_income' => 180,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-CONTRACT-EXACT')
            ->first();

        $this->assertSame('Settled', $row->business_status);
        $this->assertSame('Exact', $row->match_method);
        $this->assertSame('Exact', $row->match_confidence);
    }

    public function test_rawon_full_refund_is_not_grouped_or_allocated_to_non_refund_income(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('r', 64);
        $itemIndex = 2320329593;

        foreach ([
            ['variation' => 'Rawon', 'tracking' => null],
            ['variation' => 'Rendang', 'tracking' => 'TRACKING'],
            ['variation' => 'Gulai', 'tracking' => 'TRACKING'],
        ] as $line) {
            DB::table('marketplace_orders')->insert($this->order($user->id, [
                'order_number' => '260819RTG8Y8NS',
                'product_key' => $productKey,
                'item_index' => $itemIndex,
                'discounted_price' => 7750,
                'unit_price' => 7750,
                'quantity' => 1,
                'variation_name' => $line['variation'],
                'variation_key' => hash('sha256', $line['variation']),
                'tracking_number' => $line['tracking'],
            ]));
        }

        DB::table('marketplace_income')->insert([
            $this->income($user->id, [
                'order_number' => '260819RTG8Y8NS',
                'product_key' => $productKey,
                'item_index' => $itemIndex,
                'product_price' => 7750,
                'quantity' => 1,
                'total_income' => 6304,
                'refund_to_buyer' => 0,
            ]),
            $this->income($user->id, [
                'order_number' => '260819RTG8Y8NS',
                'product_key' => $productKey,
                'item_index' => $itemIndex,
                'product_price' => 7750,
                'quantity' => 1,
                'total_income' => 6305,
                'refund_to_buyer' => 0,
            ]),
            $this->income($user->id, [
                'order_number' => '260819RTG8Y8NS',
                'product_key' => $productKey,
                'item_index' => $itemIndex,
                'product_price' => 7750,
                'quantity' => 1,
                'total_income' => 0,
                'refund_to_buyer' => -7750,
            ]),
        ]);

        $rows = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', '260819RTG8Y8NS')
            ->get()
            ->keyBy('variation_name');

        $rawon = $rows->get('Rawon');
        $this->assertSame('Refunded', $rawon->business_status);
        $this->assertNotSame('Unmatched', $rawon->business_status);
        $this->assertNotSame('Invalid', $rawon->business_status);
        $this->assertSame(-7750.0, (float) $rawon->refund_amount);
        $this->assertSame('Full', $rawon->refund_type);
        $this->assertSame(0.0, (float) $rawon->total_income);
        $this->assertSame(0, $rawon->returned_quantity);
        $this->assertNull($rawon->tracking_number);
        $this->assertSame('None', $rawon->match_method);
        $this->assertSame('None', $rawon->match_confidence);
        $this->assertNotSame('Grouped', $rawon->match_method);
        $this->assertSame(0.0, (float) $rawon->total_income);
        $this->assertNotSame(6304.5, (float) $rawon->total_income);
        $this->assertSame(7750.0, (float) ($rawon->order_subtotal ?? 0));

        $this->assertNotSame('Refunded', $rows->get('Rendang')->business_status);
        $this->assertNotSame('Refunded', $rows->get('Gulai')->business_status);
    }

    public function test_downy_full_refund_with_zero_return_is_refunded_even_without_tracking(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('d', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => '2608125PE48NT9',
            'product_key' => $productKey,
            'item_index' => 4025513973,
            'discounted_price' => 38000,
            'unit_price' => 38000,
            'quantity' => 1,
            'tracking_number' => null,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => '2608125PE48NT9',
            'product_key' => $productKey,
            'item_index' => 4025513973,
            'product_price' => 38000,
            'quantity' => 1,
            'total_income' => 0,
            'refund_to_buyer' => -38000,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', '2608125PE48NT9')
            ->first();

        $this->assertSame('Refunded', $row->business_status);
        $this->assertSame(-38000.0, (float) $row->refund_amount);
        $this->assertSame('Full', $row->refund_type);
        $this->assertSame(0.0, (float) $row->total_income);
        $this->assertSame(0, $row->returned_quantity);
        $this->assertNull($row->tracking_number);
        $this->assertSame('None', $row->match_method);
        $this->assertSame('None', $row->match_confidence);
        $this->assertNotSame('Returned', $row->business_status);
        $this->assertNotSame('Unmatched', $row->business_status);
    }

    /**
     * Uses Kewpie's audited refund values with a synthetic Order counterpart.
     *
     * The actual Kewpie Income row is orphaned and is not exposed by the
     * current order-centric reconciliation result.
     */
    public function test_synthetic_partial_refund_is_classified_by_refund_amount(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('k', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-PARTIAL-REFUND-CONTRACT',
            'product_key' => $productKey,
            'item_index' => 2057022040,
            'discounted_price' => 543000,
            'unit_price' => 543000,
            'quantity' => 1,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-PARTIAL-REFUND-CONTRACT',
            'product_key' => $productKey,
            'item_index' => 2057022040,
            'product_price' => 543000,
            'quantity' => 1,
            'total_income' => 299155,
            'refund_to_buyer' => -181000,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-PARTIAL-REFUND-CONTRACT')
            ->first();

        $this->assertSame('Partially Refunded', $row->business_status);
        $this->assertNotSame('Refunded', $row->business_status);
        $this->assertSame(-181000.0, (float) $row->refund_amount);
        $this->assertSame('Partial', $row->refund_type);
        $this->assertSame(299155.0, (float) $row->total_income);
    }

    public function test_order_without_income_and_without_refund_evidence_is_unmatched(): void
    {
        $user = User::factory()->create();

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-UNMATCHED-CONTRACT',
            'product_key' => str_repeat('u', 64),
            'item_index' => 109,
            'tracking_number' => 'TRACKING',
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-UNMATCHED-CONTRACT')
            ->first();

        $this->assertSame('Unmatched', $row->business_status);
        $this->assertSame('None', $row->match_method);
        $this->assertSame('None', $row->match_confidence);
        $this->assertNull($row->refund_amount);
        $this->assertNull($row->total_income);
    }

    public function test_zero_income_with_refund_evidence_is_not_unmatched(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('z', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-REFUND-CONTRACT',
            'product_key' => $productKey,
            'item_index' => 110,
            'discounted_price' => 100,
            'unit_price' => 100,
            'quantity' => 1,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-REFUND-CONTRACT',
            'product_key' => $productKey,
            'item_index' => 110,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 0,
            'refund_to_buyer' => -100,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-REFUND-CONTRACT')
            ->first();

        $this->assertSame('Refunded', $row->business_status);
        $this->assertNotSame('Unmatched', $row->business_status);
        $this->assertSame(-100.0, (float) $row->refund_amount);
        $this->assertSame('Full', $row->refund_type);
        $this->assertSame(0.0, (float) $row->total_income);
        $this->assertSame(0, $row->returned_quantity);
        $this->assertSame('None', $row->match_method);
        $this->assertSame('None', $row->match_confidence);
    }

    public function test_returned_quantity_produces_returned_business_status(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('t', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-RETURN-CONTRACT',
            'product_key' => $productKey,
            'item_index' => 111,
            'discounted_price' => 100,
            'unit_price' => 100,
            'quantity' => 2,
            'returned_quantity' => 1,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-RETURN-CONTRACT',
            'product_key' => $productKey,
            'item_index' => 111,
            'product_price' => 100,
            'quantity' => 2,
            'total_income' => 90,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-RETURN-CONTRACT')
            ->first();

        $this->assertSame('Returned', $row->business_status);
        $this->assertSame(1, $row->returned_quantity);
        $this->assertSame('Exact', $row->match_method);
        $this->assertSame('Exact', $row->match_confidence);
    }

    public function test_reconciliation_filter_accepts_all_business_status_values(): void
    {
        $user = User::factory()->create([
            'account_status' => AccountStatus::Active,
        ]);

        $existingStatuses = [
            'Settled',
            'Unsettled',
            'Batal',
            'Tidak Valid',
        ];
        $newBusinessStatuses = [
            'Refunded',
            'Partially Refunded',
            'Returned',
            'Unmatched',
            'Cancelled',
            'Invalid',
        ];

        $this->actingAs($user)->get(route('finance.reconciliation', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'statuses' => $existingStatuses,
        ]))->assertOk();

        $this->actingAs($user)->get(route('finance.reconciliation', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'statuses' => $newBusinessStatuses,
        ]))->assertOk();

        $this->actingAs($user)
            ->get(route('finance.reconciliation', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'statuses' => ['Not A Status'],
            ]))
            ->assertSessionHasErrors('statuses.0');
    }

    public function test_line_identity_unique_indexes_are_scoped_per_user_and_canonical(): void
    {
        $ordersIndexes = collect(Schema::getIndexes('marketplace_orders'));
        $incomeIndexes = collect(Schema::getIndexes('marketplace_income'));

        $this->assertTrue($ordersIndexes->contains(function (array $index): bool {
            return ($index['name'] ?? null) === 'orders_user_line_identity_unique'
                && ($index['columns'] ?? []) === ['user_id', 'line_identity'];
        }));
        $this->assertTrue($incomeIndexes->contains(function (array $index): bool {
            return ($index['name'] ?? null) === 'income_user_line_identity_unique'
                && ($index['columns'] ?? []) === ['user_id', 'line_identity'];
        }));

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $lineIdentity = \App\Services\ReportLineIdentity::make('ORDER-LINE', str_repeat('x', 64), hash('sha256', 'variant-a'), 250.0, 2);

        DB::table('marketplace_orders')->insert($this->order($userA->id, [
            'order_number' => 'ORDER-LINE',
            'product_key' => str_repeat('x', 64),
            'variation_key' => hash('sha256', 'variant-a'),
            'unit_price' => 250.0,
            'quantity' => 2,
            'line_identity' => $lineIdentity,
        ]));

        DB::table('marketplace_orders')->insert($this->order($userB->id, [
            'order_number' => 'ORDER-LINE',
            'product_key' => str_repeat('x', 64),
            'variation_key' => hash('sha256', 'variant-a'),
            'unit_price' => 250.0,
            'quantity' => 2,
            'line_identity' => $lineIdentity,
        ]));

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('marketplace_orders')->insert($this->order($userA->id, [
            'order_number' => 'ORDER-LINE-DUPLICATE',
            'product_key' => str_repeat('x', 64),
            'variation_key' => hash('sha256', 'variant-a'),
            'unit_price' => 250.0,
            'quantity' => 2,
            'line_identity' => $lineIdentity,
        ]));
    }

    public function test_canonical_identity_takes_priority_over_item_index_fallback(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('f', 64);
        $variationKey = hash('sha256', 'variant-p');
        $lineIdentity = \App\Services\ReportLineIdentity::make('ORDER-CANONICAL', $productKey, $variationKey, 100.0, 1);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-CANONICAL',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 404,
            'unit_price' => 100,
            'discounted_price' => 100,
            'quantity' => 1,
            'line_identity' => $lineIdentity,
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-CANONICAL',
            'product_key' => $productKey,
            'variation_key' => $variationKey,
            'item_index' => 999,
            'unit_price' => 100,
            'product_price' => 100,
            'quantity' => 1,
            'total_income' => 100,
            'line_identity' => $lineIdentity,
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-CANONICAL')
            ->first();

        $this->assertSame('100.00', $row->total_income);
        $this->assertSame('Exact', $row->match_method);
        $this->assertSame('Settled', $row->business_status);
        $this->assertNotSame(999, $row->item_index);
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
        $this->assertSame('Belum Settlement', $row->settlement_status);
        $this->assertSame('Unmatched', $row->business_status);
        $this->assertSame('None', $row->match_method);
        $this->assertSame('Ambiguous', $row->match_confidence);
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
        $this->assertSame(['Settled'], $rows->pluck('business_status')->unique()->values()->all());
        $this->assertSame(['Grouped'], $rows->pluck('match_method')->unique()->values()->all());
        $this->assertSame(['Grouped'], $rows->pluck('match_confidence')->unique()->values()->all());
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
        $this->assertSame('Unmatched', $rows->first()->business_status);
        $this->assertSame('Estimated', $rows->first()->match_method);
        $this->assertSame('Estimated', $rows->first()->match_confidence);
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

    public function test_dashboard_uses_returned_business_status_instead_of_income_for_settled_totals(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('v', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-RETURNED-DASHBOARD',
            'product_key' => $productKey,
            'item_index' => 114,
            'discounted_price' => 250,
            'unit_price' => 250,
            'quantity' => 1,
            'returned_quantity' => 1,
            'tracking_number' => 'TRACKING-RETURNED',
            'order_created_at' => '2026-08-12 10:00:00',
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-RETURNED-DASHBOARD',
            'product_key' => $productKey,
            'item_index' => 114,
            'product_price' => 250,
            'quantity' => 1,
            'total_income' => 200,
        ]));

        $stats = app(MarketplaceReconciliationService::class)
            ->dashboardStats($user->id, '2026-08-12', '2026-08-12');

        $this->assertSame(0.0, $stats['net_sales']);
        $this->assertSame(0.0, $stats['settled_sales']);
        $this->assertSame(0, $stats['settled_order_count']);
        $this->assertSame(0.0, $stats['pending_sales']);
        $this->assertSame(0, $stats['pending_order_count']);
    }

    public function test_dashboard_net_sales_uses_remaining_quantity_for_tracked_returned_orders(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('n', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-NET-SALES-RETURN',
            'product_key' => $productKey,
            'item_index' => 116,
            'discounted_price' => 250,
            'unit_price' => 250,
            'quantity' => 2,
            'returned_quantity' => 1,
            'tracking_number' => 'TRACKING-NET-SALES',
            'order_created_at' => '2026-08-12 10:00:00',
        ]));

        DB::table('marketplace_income')->insert($this->income($user->id, [
            'order_number' => 'ORDER-NET-SALES-RETURN',
            'product_key' => $productKey,
            'item_index' => 116,
            'product_price' => 500,
            'quantity' => 2,
            'total_income' => 400,
        ]));

        $stats = app(MarketplaceReconciliationService::class)
            ->dashboardStats($user->id, '2026-08-12', '2026-08-12');

        $this->assertSame(500.0, $stats['gross_sales']);
        $this->assertSame(250.0, $stats['net_sales']);
    }

    public function test_cancelled_business_status_deterministically_maps_to_batal_legacy_status(): void
    {
        $user = User::factory()->create();
        $productKey = str_repeat('w', 64);

        DB::table('marketplace_orders')->insert($this->order($user->id, [
            'order_number' => 'ORDER-CANCELLED-LEGACY',
            'product_key' => $productKey,
            'item_index' => 115,
            'order_status' => 'Batal',
            'tracking_number' => 'TRACKING-CANCELLED',
        ]));

        $row = app(MarketplaceReconciliationService::class)
            ->joinedQuery($user->id, true)
            ->where('orders.order_number', 'ORDER-CANCELLED-LEGACY')
            ->first();

        $this->assertSame('Cancelled', $row->business_status);
        $this->assertSame('Batal', $row->settlement_status);
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

        $this->assertSame(-175.0, $result->fee_subtotal);
        $this->assertSame(-185.0, $result->total_fee);
        $this->assertSame(810.0, $result->penghasilan);
        $this->assertSame(0.0, $result->hpp);
        $this->assertSame(810.0, $result->laba);
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
        $orderIdentity = DB::table('marketplace_orders')->where('order_number', 'ORDER-DECIMAL')->value('line_identity');
        $this->assertNotNull($orderIdentity);
        $this->assertDatabaseHas('marketplace_income', [
            'user_id' => $user->id,
            'order_number' => 'ORDER-DECIMAL',
            'product_key' => hash('sha256', 'baju'),
            'variation_key' => hash('sha256', 'merah'),
            'quantity' => 2,
            'unit_price' => 100.5,
            'total_income' => 201.0,
        ]);
        $this->assertSame($orderIdentity, DB::table('marketplace_income')->where('order_number', 'ORDER-DECIMAL')->value('line_identity'));

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

    public function test_order_import_uses_discounted_price_as_unit_price(): void
    {
        $user = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'shopee-order-report-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('orders');
        $sheet->fromArray([
            ['No. Pesanan', 'Nama Produk', 'Jumlah', 'Harga Awal', 'Harga Setelah Diskon'],
            ['SHOPEE-ORDER-1', 'Produk Shopee', 2, 100.5, 90.5],
        ], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        $file = File::createWithContent('order-report.xlsx', file_get_contents($path));
        $request = UploadReportsRequest::create('/imports/upload', 'POST', [], [], ['order_report' => $file], [], [], []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        app(OrderReportImporter::class)->import($path, $user->id);

        $this->assertDatabaseHas('marketplace_orders', [
            'user_id' => $user->id,
            'order_number' => 'SHOPEE-ORDER-1',
            'unit_price' => 90.5,
        ]);

        unlink($path);
    }

    public function test_income_report_validation_matches_shopee_income_headers(): void
    {
        $user = new User;
        $path = tempnam(sys_get_temp_dir(), 'shopee-income-report-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Penghasilan');
        $sheet->fromArray([
            ['Laporan Penghasilan'],
            [],
            ['No. Pesanan', 'Nama Produk', 'Harga Produk', 'Total Penghasilan', 'Lihat berdasarkan'],
            ['SHOPEE-INCOME-1', 'Produk Shopee', 90.5, 90.5, 'Sku'],
        ], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        $file = File::createWithContent('income-report.xlsx', file_get_contents($path));
        $request = UploadReportsRequest::create('/imports/upload', 'POST', [], [], ['income_report' => $file], [], [], []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $request->validateResolved();

        unlink($path);
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
