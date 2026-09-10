<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MarketplaceReconciliationService
{
    public function reconciliationRows(int $userId, ?string $from = null, ?string $to = null): array
    {
        return $this->joinedQuery($userId, true)
            ->when($from, fn (Builder $query) => $query->where('orders.order_created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('orders.order_created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()))
            ->orderBy('orders.order_number')
            ->orderBy('orders.item_index')
            ->get()
            ->map(fn (object $row): object => $this->calculateFinancials($row))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function reconciliationPage(int $userId, ?string $from, ?string $to, array $parameters): LengthAwarePaginator
    {
        $query = $this->reconciliationQuery($userId, $from, $to, $parameters);
        $perPage = min(max((int) ($parameters['per_page'] ?? 100), 25), 500);

        $page = $query->paginate($perPage)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (object $row): object => $this->calculateFinancials($row)));

        return $page;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, object>
     */
    public function reconciliationSummaryRows(int $userId, ?string $from, ?string $to, array $parameters): array
    {
        return $this->reconciliationQuery($userId, $from, $to, $parameters)
            ->get()
            ->map(fn (object $row): object => $this->calculateFinancials($row))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function reconciliationQuery(int $userId, ?string $from, ?string $to, array $parameters): Builder
    {
        $base = $this->joinedQuery($userId, true)
            ->when($from, fn (Builder $query) => $query->where('orders.order_created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('orders.order_created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()));

        $query = DB::query()->fromSub($base, 'rows');
        $search = trim((string) ($parameters['search'] ?? ''));
        $statuses = array_values(array_filter((array) ($parameters['statuses'] ?? [])));
        $statuses = array_map(
            fn (string $status): string => match ($status) {
                'Unsettled' => 'Unmatched',
                'Batal' => 'Cancelled',
                'Tidak Valid' => 'Invalid',
                default => $status,
            },
            $statuses,
        );
        $columnFilters = json_decode((string) ($parameters['column_filters'] ?? '{}'), true);
        $columnFilters = is_array($columnFilters) ? $columnFilters : [];
        $statusExpression = 'rows.business_status';

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $query) use ($like, $statusExpression): void {
                foreach (['order_number', 'order_product_name', 'variation_name', 'tracking_number'] as $field) {
                    $query->orWhere("rows.{$field}", 'like', $like);
                }

                $query->orWhereRaw("{$statusExpression} LIKE ?", [$like]);
            });
        }

        if ($statuses !== []) {
            $query->whereIn(DB::raw($statusExpression), $statuses);
        }

        $netQuantityExpression = 'CASE WHEN rows.quantity - COALESCE(rows.returned_quantity, 0) > 0 THEN rows.quantity - COALESCE(rows.returned_quantity, 0) ELSE 0 END';
        $filterExpressions = [
            'business_status' => $statusExpression,
            'settlement_status' => $statusExpression,
            'order_number' => 'rows.order_number',
            'order_product_name' => 'rows.order_product_name',
            'quantity' => 'rows.quantity',
            'discounted_price' => 'rows.discounted_price',
            'returned_quantity' => 'rows.returned_quantity',
            'net_quantity' => $netQuantityExpression,
            'order_subtotal' => '(rows.discounted_price * '.$netQuantityExpression.')',
            'platform_fee' => 'COALESCE(rows.platform_fee, 0)',
            'free_shipping_xtra_fee' => 'COALESCE(rows.free_shipping_xtra_fee, 0)',
            'promo_xtra_service_fee' => 'COALESCE(rows.promo_xtra_service_fee, 0)',
            'order_processing_fee' => 'COALESCE(rows.order_processing_fee, 0)',
            'tax' => 'COALESCE(rows.pph22, 0)',
        ];

        foreach ($filterExpressions as $field => $expression) {
            $value = trim((string) ($columnFilters[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $query->whereRaw("CAST({$expression} AS CHAR) LIKE ?", ['%'.addcslashes($value, '%_\\').'%']);
        }

        $sortFields = [
            'business_status' => DB::raw($statusExpression),
            'settlement_status' => DB::raw($statusExpression),
            'order_number' => 'rows.order_number',
            'order_product_name' => 'rows.order_product_name',
            'quantity' => 'rows.quantity',
            'discounted_price' => 'rows.discounted_price',
            'order_created_at' => 'rows.order_created_at',
        ];
        $multiSortMeta = json_decode((string) ($parameters['multi_sort_meta'] ?? '[]'), true);

        if (is_array($multiSortMeta) && $multiSortMeta !== []) {
            foreach ($multiSortMeta as $sort) {
                $sortField = (string) ($sort['field'] ?? '');
                $sortOrder = (int) ($sort['order'] ?? 1) === -1 ? 'desc' : 'asc';

                if (isset($sortFields[$sortField])) {
                    $query->orderBy($sortFields[$sortField], $sortOrder);
                }
            }
        } else {
            $sortField = (string) ($parameters['sort_field'] ?? 'order_number');
            $sortOrder = strtolower((string) ($parameters['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortFields[$sortField] ?? $sortFields['order_number'], $sortOrder);
        }

        $query->orderBy('rows.item_index');

        return $query;
    }

    public function dashboardStats(int $userId, ?string $from = null, ?string $to = null): array
    {
        $orders = $this->joinedQuery($userId, true)
            ->when($from, fn (Builder $query) => $query->where('orders.order_created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('orders.order_created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()));

        $hasTracking = "(rows.tracking_number IS NOT NULL AND TRIM(rows.tracking_number) <> '')";
        $isCancelled = "rows.business_status = 'Cancelled'";
        $isInvalid = "rows.business_status = 'Invalid'";
        $isSettled = "rows.business_status = 'Settled'";
        $isUnmatched = "rows.business_status = 'Unmatched'";
        $sales = $this->salesExpression('rows');
        $profit = "COALESCE(rows.total_income, {$sales}) + COALESCE(rows.platform_fee, 0) + COALESCE(rows.order_processing_fee, 0) + COALESCE(rows.free_shipping_xtra_fee, 0) + COALESCE(rows.promo_xtra_service_fee, 0) + COALESCE(rows.pph22, 0)";
        $aggregate = DB::query()
            ->fromSub($orders, 'rows')
            ->selectRaw("
                COALESCE(SUM({$sales}), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN {$sales} ELSE 0 END), 0) AS net_sales,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN COALESCE(rows.platform_fee, 0) + COALESCE(rows.free_shipping_xtra_fee, 0) + COALESCE(rows.promo_xtra_service_fee, 0) + COALESCE(rows.order_processing_fee, 0) ELSE 0 END), 0) AS total_fee,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN COALESCE(rows.pph22, 0) ELSE 0 END), 0) AS total_tax,
                COALESCE(SUM(CASE WHEN {$isSettled} AND {$hasTracking} THEN {$sales} ELSE 0 END), 0) AS settled_sales,
                COALESCE(SUM(CASE WHEN {$isUnmatched} AND {$hasTracking} THEN {$sales} ELSE 0 END), 0) AS pending_sales,
                COALESCE(SUM(CASE WHEN {$isSettled} AND {$hasTracking} THEN {$profit} ELSE 0 END), 0) AS settled_profit,
                COALESCE(SUM(CASE WHEN {$isUnmatched} AND {$hasTracking} THEN {$profit} ELSE 0 END), 0) AS pending_profit,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN {$profit} ELSE 0 END), 0) AS total_profit,
                COUNT(DISTINCT CASE WHEN {$isInvalid} THEN rows.order_number END) AS valid_without_tracking,
                COALESCE(SUM(CASE WHEN {$isInvalid} THEN {$sales} ELSE 0 END), 0) AS valid_without_tracking_sales,
                COALESCE(SUM(CASE WHEN {$isCancelled} THEN {$sales} ELSE 0 END), 0) AS cancelled_sales,
                COUNT(DISTINCT CASE WHEN {$isCancelled} THEN rows.order_number END) AS cancelled_order_count,
                COUNT(DISTINCT rows.order_number) AS gross_order_count,
                COUNT(DISTINCT CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN rows.order_number END) AS net_order_count,
                COUNT(DISTINCT CASE WHEN {$isSettled} AND {$hasTracking} THEN rows.order_number END) AS settled_order_count,
                COUNT(DISTINCT CASE WHEN {$isUnmatched} AND {$hasTracking} THEN rows.order_number END) AS pending_order_count
            ")
            ->first();

        $netSales = (float) $aggregate->net_sales;
        $totalFee = (float) $aggregate->total_fee;
        $totalTax = (float) $aggregate->total_tax;
        $grossProfit = $netSales - $totalFee - $totalTax;

        return [
            'gross_sales' => (float) $aggregate->gross_sales,
            'net_sales' => (float) $aggregate->net_sales,
            'total_fee' => $totalFee,
            'total_tax' => $totalTax,
            'total_hpp' => 0.0,
            'gross_profit' => $grossProfit,
            'net_profit' => $grossProfit,
            'settled_sales' => (float) $aggregate->settled_sales,
            'pending_sales' => (float) $aggregate->pending_sales,
            'settled_profit' => (float) $aggregate->settled_profit,
            'pending_profit' => (float) $aggregate->pending_profit,
            'total_profit' => (float) $aggregate->total_profit,
            'valid_without_tracking' => (int) $aggregate->valid_without_tracking,
            'valid_without_tracking_sales' => (float) $aggregate->valid_without_tracking_sales,
            'cancelled_sales' => (float) $aggregate->cancelled_sales,
            'cancelled_order_count' => (int) $aggregate->cancelled_order_count,
            'gross_order_count' => (int) $aggregate->gross_order_count,
            'net_order_count' => (int) $aggregate->net_order_count,
            'settled_order_count' => (int) $aggregate->settled_order_count,
            'pending_order_count' => (int) $aggregate->pending_order_count,
        ];
    }

    private function salesExpression(string $tableAlias): string
    {
        return "COALESCE({$tableAlias}.discounted_price, 0) * COALESCE({$tableAlias}.quantity, 0)";
    }

    /** @return array{min: ?string, max: ?string} */
    public function orderDateRange(int $userId): array
    {
        $range = DB::table('marketplace_orders')
            ->where('user_id', $userId)
            ->selectRaw('MIN(order_created_at) AS date_min, MAX(order_created_at) AS date_max')
            ->first();

        return [
            'min' => $range?->date_min ? substr($range->date_min, 0, 10) : null,
            'max' => $range?->date_max ? substr($range->date_max, 0, 10) : null,
        ];
    }

    public function joinedQuery(int $userId, bool $includeAll = false): Builder
    {
        /*
         * Reconciliation contract:
         * Refund and return evidence is evaluated before item_index fallback
         * allocation; matching metadata is kept separate from business status.
         */
        $incomeExact = DB::table('marketplace_income')
            ->whereNotNull('total_income')
            ->where('total_income', '<>', 0)
            ->selectRaw('
                user_id,
                order_number,
                product_name,
                product_key,
                variation_key,
                COALESCE(unit_price, product_price) AS unit_price,
                product_price,
                quantity,
                COUNT(*) AS candidate_count,
                MAX(total_income) AS total_income,
                MAX(order_processing_fee) AS order_processing_fee,
                MAX(platform_fee) AS platform_fee,
                MAX(refund_to_buyer) AS refund_to_buyer,
                MAX(free_shipping_xtra_fee) AS free_shipping_xtra_fee,
                MAX(promo_xtra_service_fee) AS promo_xtra_service_fee,
                MAX(pph22) AS pph22
            ')
            ->groupBy(
                'user_id',
                'order_number',
                'product_name',
                'product_key',
                'variation_key',
                'unit_price',
                'product_price',
                'quantity'
            );

        $incomeFallback = DB::table('marketplace_income')
            ->whereNotNull('total_income')
            ->where('total_income', '<>', 0)
            ->where(function (Builder $query): void {
                $query->whereNull('refund_to_buyer')->orWhere('refund_to_buyer', '>=', 0);
            })
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                item_index,
                COUNT(*) AS candidate_count,
                SUM(product_price) AS income_amount,
                SUM(total_income) AS total_income_sum,
                SUM(order_processing_fee) AS processing_total,
                SUM(platform_fee) AS platform_total,
                SUM(refund_to_buyer) AS refund_total,
                SUM(free_shipping_xtra_fee) AS shipping_total,
                SUM(promo_xtra_service_fee) AS promo_total,
                SUM(pph22) AS tax_total
            ')
            ->groupBy(
                'user_id',
                'order_number',
                'product_key',
                'item_index'
            );

        $incomeRefund = DB::table('marketplace_income')
            ->where('refund_to_buyer', '<', 0)
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                item_index,
                variation_key,
                MAX(product_price) AS product_price,
                MAX(total_income) AS total_income,
                MAX(refund_to_buyer) AS refund_to_buyer
            ')
            ->groupBy('user_id', 'order_number', 'product_key', 'item_index', 'variation_key');

        $netQuantitySql = 'CASE WHEN quantity - COALESCE(returned_quantity, 0) > 0 THEN quantity - COALESCE(returned_quantity, 0) ELSE 0 END';
        $defaultOrderProcessingFee = -(float) config('marketplace.order_processing_fee', 1250);

        $settledSkuFee = DB::table('marketplace_income')
            ->where('user_id', $userId)
            ->whereNotNull('total_income')
            ->where('total_income', '<>', 0)
            ->where(function (Builder $query): void {
                $query->whereNull('refund_to_buyer')->orWhere('refund_to_buyer', '>=', 0);
            })
            ->selectRaw('
                user_id,
                product_key,
                AVG(CASE
                    WHEN COALESCE(product_price, 0) * COALESCE(quantity, 0) <> 0 THEN COALESCE(platform_fee, 0) / (COALESCE(product_price, 0) * COALESCE(quantity, 0))
                    ELSE 0
                END) AS avg_platform_fee_rate,
                AVG(CASE
                    WHEN COALESCE(product_price, 0) * COALESCE(quantity, 0) <> 0 THEN COALESCE(free_shipping_xtra_fee, 0) / (COALESCE(product_price, 0) * COALESCE(quantity, 0))
                    ELSE 0
                END) AS avg_free_shipping_xtra_fee_rate,
                AVG(CASE
                    WHEN COALESCE(product_price, 0) * COALESCE(quantity, 0) <> 0 THEN COALESCE(promo_xtra_service_fee, 0) / (COALESCE(product_price, 0) * COALESCE(quantity, 0))
                    ELSE 0
                END) AS avg_promo_xtra_service_fee_rate
            ')
            ->groupBy('user_id', 'product_key');

        $orderGroups = DB::table('marketplace_orders')
            ->where('user_id', $userId)
            ->whereNotNull('tracking_number')
            ->whereRaw("TRIM(tracking_number) <> ''")
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                item_index,
                COUNT(*) AS order_line_count,
                SUM(discounted_price * '.$netQuantitySql.') AS order_amount
            ')
            ->groupBy('user_id', 'order_number', 'product_key', 'item_index');

        $orderColumns = [
            'orders.id',
            'orders.user_id',
            'orders.order_number',
            'orders.item_index',
            'orders.order_status',
            'orders.cancellation_reason',
            'orders.return_status',
            'orders.tracking_number',
            'orders.shipping_option',
            'orders.order_type',
            'orders.payment_method',
            'orders.parent_sku',
            'orders.product_name',
            'orders.product_key',
            'orders.sku_reference',
            'orders.variation_name',
            'orders.variation_key',
            'orders.original_price',
            'orders.discounted_price',
            'orders.unit_price',
            'orders.quantity',
            'orders.returned_quantity',
            DB::raw('CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END AS net_quantity'),
            DB::raw('(COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) AS order_subtotal'),
            'orders.total_payment',
            'orders.order_created_at',
        ];
        $refundAmount = 'COALESCE(NULLIF(income_exact.refund_to_buyer, 0), income_refund.refund_to_buyer)';
        $refundEvidence = "({$refundAmount} < 0)";
        $exactMatch = 'income_exact.candidate_count = 1';
        $groupedMatch = '(income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR income_fallback.candidate_count = 1))';
        $ambiguousMatch = '(income_exact.candidate_count > 1 OR (income_exact.candidate_count IS NULL AND income_fallback.candidate_count > 1 AND (income_fallback.candidate_count <> order_group.order_line_count OR income_fallback.income_amount <> order_group.order_amount)))';
        $estimatedMatch = '(income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL)';
        $returnEvidence = "(COALESCE(orders.returned_quantity, 0) > 0 OR NULLIF(TRIM(COALESCE(orders.return_status, '')), '') IS NOT NULL)";
        $cancelledEvidence = "(LOWER(TRIM(COALESCE(orders.order_status, ''))) = 'batal' OR NULLIF(TRIM(COALESCE(orders.cancellation_reason, '')), '') IS NOT NULL)";
        $invalidEvidence = "(orders.tracking_number IS NULL OR TRIM(orders.tracking_number) = '')";
        $businessStatus = "CASE WHEN {$refundEvidence} THEN CASE WHEN ABS({$refundAmount}) >= COALESCE(orders.discounted_price, 0) * COALESCE(orders.quantity, 0) THEN 'Refunded' ELSE 'Partially Refunded' END WHEN {$returnEvidence} THEN 'Returned' WHEN {$cancelledEvidence} THEN 'Cancelled' WHEN {$invalidEvidence} THEN 'Invalid' WHEN {$exactMatch} OR {$groupedMatch} THEN 'Settled' ELSE 'Unmatched' END";
        $matchMethod = "CASE WHEN {$refundEvidence} OR {$cancelledEvidence} OR {$invalidEvidence} OR {$ambiguousMatch} THEN 'None' WHEN {$exactMatch} THEN 'Exact' WHEN {$groupedMatch} THEN 'Grouped' WHEN {$estimatedMatch} THEN 'Estimated' ELSE 'None' END";
        $matchConfidence = "CASE WHEN {$refundEvidence} OR {$cancelledEvidence} OR {$invalidEvidence} THEN 'None' WHEN {$ambiguousMatch} THEN 'Ambiguous' WHEN {$exactMatch} THEN 'Exact' WHEN {$groupedMatch} THEN 'Grouped' WHEN {$estimatedMatch} THEN 'Estimated' ELSE 'None' END";
        $settlementStatus = "CASE WHEN ({$businessStatus}) = 'Settled' AND ({$matchMethod}) = 'Grouped' THEN 'Grouped Match' WHEN ({$businessStatus}) = 'Settled' THEN 'Settled' WHEN ({$businessStatus}) = 'Refunded' THEN 'Refunded' WHEN ({$businessStatus}) = 'Partially Refunded' THEN 'Partially Refunded' WHEN ({$businessStatus}) = 'Returned' THEN 'Returned' WHEN ({$businessStatus}) = 'Unmatched' AND ({$matchMethod}) = 'Estimated' THEN 'Estimated' WHEN ({$businessStatus}) = 'Unmatched' THEN 'Belum Settlement' WHEN ({$businessStatus}) = 'Cancelled' THEN 'Batal' WHEN ({$businessStatus}) = 'Invalid' THEN 'Tidak Valid' END";

        return DB::table('marketplace_orders as orders')
            ->leftJoinSub($incomeExact, 'income_exact', function ($join): void {
                $join->on('income_exact.user_id', '=', 'orders.user_id')
                    ->on('income_exact.order_number', '=', 'orders.order_number')
                    ->on('income_exact.product_key', '=', 'orders.product_key')
                    ->on('income_exact.quantity', '=', 'orders.quantity')
                    ->whereRaw('LOWER(COALESCE(income_exact.product_name, \'\')) = LOWER(COALESCE(orders.product_name, \'\'))')
                    ->whereRaw('COALESCE(income_exact.variation_key, \'\') = COALESCE(orders.variation_key, \'\')')
                    ->whereRaw('(
                        COALESCE(income_exact.unit_price, income_exact.product_price) = COALESCE(orders.unit_price, orders.discounted_price)
                        OR income_exact.product_price = orders.discounted_price * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END
                    )');
            })
            ->leftJoinSub($incomeRefund, 'income_refund', function ($join): void {
                $join->on('income_refund.user_id', '=', 'orders.user_id')
                    ->on('income_refund.order_number', '=', 'orders.order_number')
                    ->on('income_refund.product_key', '=', 'orders.product_key')
                    ->on('income_refund.item_index', '=', 'orders.item_index')
                    ->where(function ($join): void {
                        $join->whereNull('orders.tracking_number')
                            ->orWhereRaw("TRIM(orders.tracking_number) = ''")
                            ->orWhereRaw("COALESCE(income_refund.variation_key, '') = COALESCE(orders.variation_key, '')");
                    });
            })
            ->leftJoinSub($incomeFallback, 'income_fallback', function ($join): void {
                $join->on('income_fallback.user_id', '=', 'orders.user_id')
                    ->on('income_fallback.order_number', '=', 'orders.order_number')
                    ->on('income_fallback.product_key', '=', 'orders.product_key')
                    ->on('income_fallback.item_index', '=', 'orders.item_index')
                    ->whereNull('income_exact.user_id');
            })
            ->leftJoinSub($settledSkuFee, 'settled_sku_fee', function ($join): void {
                $join->on('settled_sku_fee.user_id', '=', 'orders.user_id')
                    ->on('settled_sku_fee.product_key', '=', 'orders.product_key');
            })
            ->leftJoinSub($orderGroups, 'order_group', function ($join): void {
                $join->on('order_group.user_id', '=', 'orders.user_id')
                    ->on('order_group.order_number', '=', 'orders.order_number')
                    ->on('order_group.product_key', '=', 'orders.product_key')
                    ->on('order_group.item_index', '=', 'orders.item_index');
            })
            ->where('orders.user_id', $userId)
            ->when(! $includeAll, function (Builder $query): void {
                $this->withTrackingNumber($query)
                    ->where(fn (Builder $query) => $query->whereNull('orders.order_status')->orWhereRaw("LOWER(TRIM(orders.order_status)) <> 'batal'"));
            })
            ->select([
                ...$orderColumns,
                'orders.product_name as order_product_name',
                'orders.variation_name as order_variation_name',
                DB::raw("CASE WHEN {$refundEvidence} THEN COALESCE(income_exact.total_income, income_refund.total_income, 0) WHEN {$exactMatch} THEN income_exact.total_income WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.total_income_sum / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.total_income_sum END AS total_income"),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.order_processing_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.processing_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.processing_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN '.(string) $defaultOrderProcessingFee.' ELSE 0 END AS order_processing_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.platform_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.platform_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.platform_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN (COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) * COALESCE(settled_sku_fee.avg_platform_fee_rate, 0) ELSE 0 END AS platform_fee'),
                DB::raw("CASE WHEN {$refundEvidence} THEN {$refundAmount} WHEN {$exactMatch} THEN income_exact.refund_to_buyer WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.refund_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.refund_total END AS refund_to_buyer"),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.free_shipping_xtra_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.shipping_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.shipping_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN (COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) * COALESCE(settled_sku_fee.avg_free_shipping_xtra_fee_rate, 0) ELSE 0 END AS free_shipping_xtra_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.promo_xtra_service_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.promo_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.promo_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN (COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) * COALESCE(settled_sku_fee.avg_promo_xtra_service_fee_rate, 0) ELSE 0 END AS promo_xtra_service_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.pph22 WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount THEN 1.0 * income_fallback.tax_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.tax_total END AS pph22'),
                DB::raw("{$businessStatus} AS business_status"),
                DB::raw("{$matchMethod} AS match_method"),
                DB::raw("{$matchConfidence} AS match_confidence"),
                DB::raw("{$refundAmount} AS refund_amount"),
                DB::raw("CASE WHEN {$refundEvidence} THEN CASE WHEN ABS({$refundAmount}) >= COALESCE(orders.discounted_price, 0) * COALESCE(orders.quantity, 0) THEN 'Full' ELSE 'Partial' END END AS refund_type"),
                DB::raw("{$settlementStatus} AS settlement_status"),
            ]);
    }

    private function withTrackingNumber(Builder $query): Builder
    {
        return $query
            ->whereNotNull('orders.tracking_number')
            ->whereRaw("TRIM(orders.tracking_number) <> ''");
    }

    public function calculateFinancials(object $row): object
    {
        $quantity = (float) ($row->quantity ?? 0);
        $returned = (float) ($row->returned_quantity ?? 0);
        $discountedPrice = (float) ($row->discounted_price ?? 0);
        $admin = -abs((float) ($row->platform_fee ?? 0));
        $shipping = -abs((float) ($row->free_shipping_xtra_fee ?? 0));
        $promo = -abs((float) ($row->promo_xtra_service_fee ?? 0));
        $processing = -abs((float) ($row->order_processing_fee ?? 0));
        $tax = -abs((float) ($row->pph22 ?? 0));
        $net = max($quantity - $returned, 0);
        $subtotal = $discountedPrice * $net;
        $feeSubtotal = $admin + $shipping + $promo;
        $totalFee = $feeSubtotal + $processing;
        $earnings = $subtotal + ($totalFee + $tax);
        $hpp = 0.0;

        $row->net_quantity = $net;
        $row->order_subtotal = $subtotal;
        $row->admin_fee_percent = $this->percent($admin, $subtotal);
        $row->free_shipping_xtra_fee_percent = $this->percent($shipping, $subtotal);
        $row->promo_xtra_fee_percent = $this->percent($promo, $subtotal);
        $row->fee_subtotal = $feeSubtotal;
        $row->fee_subtotal_percent = $this->percent($feeSubtotal, $subtotal);
        $row->total_fee = $totalFee;
        $row->tax = $tax;
        $row->penghasilan = $earnings;
        $row->hpp = $hpp;
        $row->laba = $earnings - $hpp;

        return $row;
    }

    private function percent(float $value, float $base): float
    {
        return $base == 0.0 ? 0.0 : abs($value) / abs($base) * 100;
    }

    public function forOrder(int $userId, string $orderNumber): Builder
    {
        return $this->joinedQuery($userId)->where('orders.order_number', $orderNumber)->orderBy('orders.item_index');
    }
}
