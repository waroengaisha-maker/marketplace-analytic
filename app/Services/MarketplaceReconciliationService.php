<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketplaceReconciliationService
{
    public function dashboardStats(int $userId, ?string $from = null, ?string $to = null): array
    {
        $orders = $this->joinedQuery($userId, true)
            ->when($from, fn ($query) => $query->whereDate('orders.order_created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('orders.order_created_at', '<=', $to))
            ->get();

        $nonCancelled = $orders->filter(fn (object $row): bool => strtolower(trim((string) $row->order_status)) !== 'batal');
        $valid = $nonCancelled->filter(fn (object $row): bool => $this->hasTrackingNumber($row->tracking_number));
        $settled = $valid->filter(fn (object $row): bool => $row->total_income !== null);
        $pending = $valid->filter(fn (object $row): bool => $row->total_income === null);
        $profit = fn ($rows): float => (float) $rows->sum(function (object $row): float {
            return (float) ($row->total_income ?? $this->salesLine($row))
                + (float) ($row->platform_fee ?? 0)
                + (float) ($row->order_processing_fee ?? 0)
                + (float) ($row->free_shipping_xtra_fee ?? 0)
                + (float) ($row->promo_xtra_service_fee ?? 0)
                + (float) ($row->pph22 ?? 0);
        });

        return [
            'gross_sales' => $this->salesTotal($orders),
            'net_sales' => $this->salesTotal($valid),
            'settled_sales' => $this->salesTotal($settled),
            'pending_sales' => $this->salesTotal($pending),
            'settled_profit' => $profit($settled),
            'pending_profit' => $profit($pending),
            'total_profit' => $profit($valid),
            'valid_without_tracking' => $nonCancelled->filter(fn (object $row): bool => ! $this->hasTrackingNumber($row->tracking_number))->unique('order_number')->count(),
            'valid_without_tracking_sales' => $this->salesTotal($nonCancelled->filter(fn (object $row): bool => ! $this->hasTrackingNumber($row->tracking_number))),
            'cancelled_sales' => $this->salesTotal($orders->filter(fn (object $row): bool => strtolower(trim((string) $row->order_status)) === 'batal')),
            'cancelled_order_count' => $orders->filter(fn (object $row): bool => strtolower(trim((string) $row->order_status)) === 'batal')->unique('order_number')->count(),
            'gross_order_count' => $orders->unique('order_number')->count(),
            'net_order_count' => $valid->unique('order_number')->count(),
            'settled_order_count' => $settled->unique('order_number')->count(),
            'pending_order_count' => $pending->unique('order_number')->count(),
        ];
    }

    private function salesTotal(Collection $rows): float
    {
        return (float) $rows->sum(fn (object $row): float => $this->salesLine($row));
    }

    private function salesLine(object $row): float
    {
        return (float) ($row->discounted_price ?? 0) * (int) ($row->quantity ?? 0);
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
        $incomeBase = DB::table('marketplace_income')
            ->where('total_income', '>', 0)
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                item_index,
                variation_key,
                product_price,
                quantity,
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
                'item_index',
                'variation_key',
                'product_price',
                'quantity'
            );

        $incomeExact = DB::query()
            ->fromSub($incomeBase, 'income_lines')
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                variation_key,
                product_price,
                quantity,
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

        $incomeByItem = DB::query()
            ->fromSub($incomeBase, 'income_lines')
            ->selectRaw('
                user_id,
                order_number,
                product_key,
                item_index,
                MAX(total_income) AS total_income,
                MAX(order_processing_fee) AS order_processing_fee,
                MAX(platform_fee) AS platform_fee,
                MAX(refund_to_buyer) AS refund_to_buyer,
                MAX(free_shipping_xtra_fee) AS free_shipping_xtra_fee,
                MAX(promo_xtra_service_fee) AS promo_xtra_service_fee,
                MAX(pph22) AS pph22
            ')
            ->groupBy('user_id', 'order_number', 'product_key', 'item_index');

        return DB::table('marketplace_orders as orders')
            ->leftJoinSub($incomeExact, 'income_exact', function ($join): void {
                $join->on('income_exact.user_id', '=', 'orders.user_id')
                    ->on('income_exact.order_number', '=', 'orders.order_number')
                    ->on('income_exact.product_key', '=', 'orders.product_key')
                    ->on('income_exact.quantity', '=', 'orders.quantity')
                    ->whereRaw('income_exact.variation_key <=> orders.variation_key')
                    ->whereRaw('income_exact.product_price = orders.unit_price * GREATEST(orders.quantity - COALESCE(orders.returned_quantity, 0), 0)');
            })
            ->leftJoinSub($incomeByItem, 'income_fallback', function ($join): void {
                $join->on('income_fallback.user_id', '=', 'orders.user_id')
                    ->on('income_fallback.order_number', '=', 'orders.order_number')
                    ->on('income_fallback.product_key', '=', 'orders.product_key')
                    ->on('income_fallback.item_index', '=', 'orders.item_index')
                    ->whereNull('income_exact.total_income');
            })
            ->where('orders.user_id', $userId)
            ->when(! $includeAll, function (Builder $query): void {
                $this->withTrackingNumber($query)
                    ->where(function ($query): void {
                        $query->whereNull('orders.order_status')
                            ->orWhereRaw("LOWER(TRIM(orders.order_status)) <> 'batal'");
                    });
            })
            ->select([
                'orders.*',
                'orders.product_name as order_product_name',
                'orders.variation_name as order_variation_name',
                DB::raw('COALESCE(income_exact.total_income, income_fallback.total_income) AS total_income'),
                DB::raw('COALESCE(income_exact.order_processing_fee, income_fallback.order_processing_fee) AS order_processing_fee'),
                DB::raw('COALESCE(income_exact.platform_fee, income_fallback.platform_fee) AS platform_fee'),
                DB::raw('COALESCE(income_exact.refund_to_buyer, income_fallback.refund_to_buyer) AS refund_to_buyer'),
                DB::raw('COALESCE(income_exact.free_shipping_xtra_fee, income_fallback.free_shipping_xtra_fee) AS free_shipping_xtra_fee'),
                DB::raw('COALESCE(income_exact.promo_xtra_service_fee, income_fallback.promo_xtra_service_fee) AS promo_xtra_service_fee'),
                DB::raw('COALESCE(income_exact.pph22, income_fallback.pph22) AS pph22'),
                DB::raw("CASE WHEN COALESCE(income_exact.total_income, income_fallback.total_income) IS NULL THEN 'Belum Settlement' ELSE 'Settled' END AS settlement_status"),
            ]);
    }

    private function withTrackingNumber(Builder $query): Builder
    {
        return $query
            ->whereNotNull('orders.tracking_number')
            ->whereRaw("TRIM(orders.tracking_number) <> ''");
    }

    private function hasTrackingNumber(mixed $trackingNumber): bool
    {
        return $trackingNumber !== null && trim((string) $trackingNumber) !== '';
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