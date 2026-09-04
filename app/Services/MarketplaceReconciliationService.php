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
                    ->on('income.item_index', '=', 'orders.item_index')
                    ->whereRaw('LOWER(TRIM(income.product_name)) = LOWER(TRIM(orders.product_name))');
            })
            ->where('orders.user_id', $userId)
            ->select(['orders.*', 'income.total_income', 'income.platform_fee', 'income.refund_to_buyer', 'income.free_shipping_xtra_fee', 'income.promo_xtra_service_fee', 'income.pph22']);
    }

    public function forOrder(int $userId, string $orderNumber): Builder
    {
        return $this->joinedQuery($userId)->where('orders.order_number', $orderNumber)->orderBy('orders.item_index');
    }
}