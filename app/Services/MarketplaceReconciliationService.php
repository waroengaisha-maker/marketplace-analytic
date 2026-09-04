<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MarketplaceReconciliationService
{
    public function joinedQuery(int $userId): Builder
    {
        return DB::table('marketplace_orders as orders')
            ->leftJoin('marketplace_income as income', function ($join): void {
                $join->on('income.user_id', '=', 'orders.user_id')
                    ->on('income.order_number', '=', 'orders.order_number')
                    ->whereNotNull('income.total_income')
                    ->where(function ($query): void {
                        $query->where(function ($primary): void {
                            $primary->whereRaw('LOWER(TRIM(income.product_name)) = LOWER(TRIM(orders.product_name))')
                                ->whereRaw('(income.variation_key = orders.variation_key OR (income.variation_key IS NULL AND orders.variation_key IS NULL))')
                                ->whereRaw('(income.unit_price = orders.unit_price OR (income.unit_price IS NULL AND orders.unit_price IS NULL))')
                                ->whereRaw('(income.quantity = orders.quantity OR (income.quantity IS NULL AND orders.quantity IS NULL))');
                        })->orWhere(function ($fallback): void {
                            $fallback->whereColumn('income.item_index', 'orders.item_index')
                                ->whereColumn('income.product_key', 'orders.product_key')
                                ->whereColumn('income.unit_price', 'orders.unit_price')
                                ->whereColumn('income.quantity', 'orders.quantity');
                        });
                    });
            })
            ->where('orders.user_id', $userId)
            ->whereNotNull('orders.tracking_number')
            ->where(function ($query): void {
                $query->whereNull('orders.order_status')
                    ->orWhereRaw("LOWER(TRIM(orders.order_status)) <> 'batal'");
            })
            ->select(['orders.*', 'orders.product_name as order_product_name', 'orders.variation_name as order_variation_name', 'income.total_income', 'income.order_processing_fee', 'income.platform_fee', 'income.refund_to_buyer', 'income.free_shipping_xtra_fee', 'income.promo_xtra_service_fee', 'income.pph22']);
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