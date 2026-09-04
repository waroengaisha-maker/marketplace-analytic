<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MarketplaceReconciliationService
{
    public function dashboardStats(int $userId, ?string $from = null, ?string $to = null): array
    {
        $orders = $this->joinedQuery($userId, true)
            ->when($from, fn (Builder $query) => $query->where('orders.order_created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('orders.order_created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()));

        $statusIsValid = "(rows.order_status IS NULL OR LOWER(TRIM(rows.order_status)) <> 'batal')";
        $hasTracking = "(rows.tracking_number IS NOT NULL AND TRIM(rows.tracking_number) <> '')";
        $withoutTracking = "(rows.tracking_number IS NULL OR TRIM(rows.tracking_number) = '')";
        $sales = $this->salesExpression('rows');
        $profit = "COALESCE(rows.total_income, {$sales}) + COALESCE(rows.platform_fee, 0) + COALESCE(rows.order_processing_fee, 0) + COALESCE(rows.free_shipping_xtra_fee, 0) + COALESCE(rows.promo_xtra_service_fee, 0) + COALESCE(rows.pph22, 0)";
        $aggregate = DB::query()
            ->fromSub($orders, 'rows')
            ->selectRaw("
                COALESCE(SUM({$sales}), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$hasTracking} THEN {$sales} ELSE 0 END), 0) AS net_sales,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$hasTracking} AND rows.total_income IS NOT NULL THEN {$sales} ELSE 0 END), 0) AS settled_sales,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$hasTracking} AND rows.total_income IS NULL THEN {$sales} ELSE 0 END), 0) AS pending_sales,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$hasTracking} AND rows.total_income IS NOT NULL THEN {$profit} ELSE 0 END), 0) AS settled_profit,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$hasTracking} AND rows.total_income IS NULL THEN {$profit} ELSE 0 END), 0) AS pending_profit,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$hasTracking} THEN {$profit} ELSE 0 END), 0) AS total_profit,
                COUNT(DISTINCT CASE WHEN {$statusIsValid} AND {$withoutTracking} THEN rows.order_number END) AS valid_without_tracking,
                COALESCE(SUM(CASE WHEN {$statusIsValid} AND {$withoutTracking} THEN {$sales} ELSE 0 END), 0) AS valid_without_tracking_sales,
                COALESCE(SUM(CASE WHEN NOT {$statusIsValid} THEN {$sales} ELSE 0 END), 0) AS cancelled_sales,
                COUNT(DISTINCT CASE WHEN NOT {$statusIsValid} THEN rows.order_number END) AS cancelled_order_count,
                COUNT(DISTINCT rows.order_number) AS gross_order_count,
                COUNT(DISTINCT CASE WHEN {$statusIsValid} AND {$hasTracking} THEN rows.order_number END) AS net_order_count,
                COUNT(DISTINCT CASE WHEN {$statusIsValid} AND {$hasTracking} AND rows.total_income IS NOT NULL THEN rows.order_number END) AS settled_order_count,
                COUNT(DISTINCT CASE WHEN {$statusIsValid} AND {$hasTracking} AND rows.total_income IS NULL THEN rows.order_number END) AS pending_order_count
            ")
            ->first();

        return [
            'gross_sales' => (float) $aggregate->gross_sales,
            'net_sales' => (float) $aggregate->net_sales,
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
        $incomeExact = DB::table('marketplace_income')
            ->whereNotNull('total_income')
            ->where('total_income', '<>', 0)
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                variation_key,
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
                'product_key',
                'variation_key',
                'product_price',
                'quantity'
            );

        $incomeFallback = DB::table('marketplace_income')
            ->whereNotNull('total_income')
            ->where('total_income', '<>', 0)
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
                SUM(discounted_price * GREATEST(quantity - COALESCE(returned_quantity, 0), 0)) AS order_amount
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
            'orders.order_subtotal',
            'orders.total_payment',
            'orders.order_created_at',
        ];

        return DB::table('marketplace_orders as orders')
            ->leftJoinSub($incomeExact, 'income_exact', function ($join): void {
                $join->on('income_exact.user_id', '=', 'orders.user_id')
                    ->on('income_exact.order_number', '=', 'orders.order_number')
                    ->on('income_exact.product_key', '=', 'orders.product_key')
                    ->on('income_exact.quantity', '=', 'orders.quantity')
                    ->whereRaw('income_exact.variation_key <=> orders.variation_key')
                    ->whereRaw('income_exact.product_price = orders.discounted_price * GREATEST(orders.quantity - COALESCE(orders.returned_quantity, 0), 0)');
            })
            ->leftJoinSub($incomeFallback, 'income_fallback', function ($join): void {
                $join->on('income_fallback.user_id', '=', 'orders.user_id')
                    ->on('income_fallback.order_number', '=', 'orders.order_number')
                    ->on('income_fallback.product_key', '=', 'orders.product_key')
                    ->on('income_fallback.item_index', '=', 'orders.item_index')
                    ->whereNull('income_exact.user_id');
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
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.total_income WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.total_income_sum / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.total_income_sum END AS total_income'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.order_processing_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.processing_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.processing_total END AS order_processing_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.platform_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.platform_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.platform_total END AS platform_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.refund_to_buyer WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.refund_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.refund_total END AS refund_to_buyer'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.free_shipping_xtra_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.shipping_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.shipping_total END AS free_shipping_xtra_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.promo_xtra_service_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.promo_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.promo_total END AS promo_xtra_service_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.pph22 WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount                 THEN income_fallback.tax_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.tax_total END AS pph22'),
                DB::raw("CASE WHEN income_exact.candidate_count > 1 OR (income_exact.candidate_count IS NULL AND income_fallback.candidate_count > 1 AND (income_fallback.candidate_count <> order_group.order_line_count OR income_fallback.income_amount <> order_group.order_amount)) THEN 'Ambiguous' WHEN income_exact.candidate_count = 1 THEN 'Settled' WHEN income_fallback.candidate_count = 1 OR (income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) THEN CASE WHEN income_fallback.candidate_count > 1 THEN 'Grouped Match' ELSE 'Settled' END ELSE 'Belum Settlement' END AS settlement_status"),
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
        $subtotal = (float) ($row->order_subtotal ?? 0);
        $admin = (float) ($row->platform_fee ?? 0);
        $shipping = (float) ($row->free_shipping_xtra_fee ?? 0);
        $promo = (float) ($row->promo_xtra_service_fee ?? 0);
        $processing = (float) ($row->order_processing_fee ?? 0);
        $tax = (float) ($row->pph22 ?? 0);
        $net = $quantity - $returned;
        $feeSubtotal = $admin + $shipping + $promo + $processing;

        $row->net_quantity = $net;
        $row->admin_fee_percent = $this->percent($admin, $subtotal);
        $row->free_shipping_xtra_fee_percent = $this->percent($shipping, $subtotal);
        $row->promo_xtra_fee_percent = $this->percent($promo, $subtotal);
        $row->fee_subtotal = $feeSubtotal;
        $row->fee_per_unit = $net > 0 ? $feeSubtotal / $net : 0;
        $row->total_fee = $feeSubtotal + $tax;
        $row->tax = $tax;

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
