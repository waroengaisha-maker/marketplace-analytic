<?php

namespace App\Services;

use Carbon\Carbon;

class ShopeeResponseNormalizer
{
    /**
     * Maps Shopee order headers onto the canonical marketplace_orders columns.
     * Display-only: nothing is persisted by the Integration Lab.
     *
     * @param  array<int, array<string, mixed>>  $orderList
     * @return array<int, array<string, mixed>>
     */
    public function normalizeOrderHeaders(array $orderList): array
    {
        return array_map(function (array $order): array {
            $packages = $order['package_list'] ?? [];

            return [
                'order_number' => $order['order_sn'] ?? null,
                'order_status' => $order['order_status'] ?? null,
                'payment_method' => $order['payment_method'] ?? null,
                'shipping_option' => $packages !== [] ? (data_get($packages[0], 'shipping_carrier') ?? null) : null,
                'tracking_number' => $this->firstTrackingNumber($packages),
                'buyer_username' => $order['buyer_username'] ?? $order['buyer_login_id'] ?? null,
                'order_created_at' => $this->timestamp($order['create_time'] ?? null),
                'payment_at' => $this->timestamp($order['pay_time'] ?? null),
                'shipped_at' => $this->timestamp($order['pickup_done_time'] ?? null),
            ];
        }, $orderList);
    }

    /**
     * Returns the first non-empty tracking number found across the order packages.
     *
     * @param  array<int, array<string, mixed>>  $packages
     */
    private function firstTrackingNumber(array $packages): ?string
    {
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $tracking = trim((string) ($package['tracking_number'] ?? ''));
            if ($tracking !== '') {
                return $tracking;
            }
        }

        return null;
    }

    /**
     * Expands escrow items into marketplace_orders line-shaped rows.
     *
     * @param  array<string, mixed>  $orderIncome
     * @return array<int, array<string, mixed>>
     */
    public function normalizeOrderLines(array $orderIncome): array
    {
        $orderSn = $orderIncome['order_sn'] ?? null;
        $items = $orderIncome['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_map(function (array $item) use ($orderSn): array {
            $sku = $item['item_sku'] ?? $item['model_sku'] ?? null;

            return [
                'order_number' => $orderSn,
                'parent_sku' => $item['item_sku'] ?? null,
                'product_name' => $item['item_name'] ?? null,
                'sku_reference' => $item['model_sku'] ?? null,
                'variation_name' => $item['model_name'] ?? null,
                'original_price' => $item['original_price'] ?? null,
                'discounted_price' => $item['discounted_price'] ?? null,
                'quantity' => $item['quantity_purchased'] ?? null,
                'sku' => $sku,
            ];
        }, $items);
    }

    /**
     * Normalizes the get_escrow_detail order_income object for display.
     *
     * @param  array<string, mixed>  $orderIncome
     * @return array<string, mixed>
     */
    public function normalizeEscrow(array $orderIncome): array
    {
        return [
            'order_number' => $orderIncome['order_sn'] ?? null,
            'buyer_amount' => $orderIncome['buyer_total_amount'] ?? null,
            'escrow_amount' => $orderIncome['escrow_amount'] ?? null,
            'currency' => $orderIncome['currency'] ?? null,
            'coins' => $orderIncome['coins'] ?? null,
            'voucher_from_seller' => $orderIncome['voucher_from_seller'] ?? null,
            'voucher_from_shopee' => $orderIncome['voucher_from_shopee'] ?? null,
            'credit_card_promotion' => $orderIncome['credit_card_promotion'] ?? null,
        ];
    }

    /**
     * Maps income detail rows onto the canonical marketplace_income columns.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function normalizeIncomeRows(array $items): array
    {
        return array_map(function (array $item): array {
            return [
                'order_number' => $item['order_sn'] ?? null,
                'row_type' => $item['description'] ?? null,
                'status' => $item['status'] ?? null,
                'payment_method' => $item['payment_method'] ?? null,
                'currency' => $item['currency'] ?? null,
                'total_income' => $item['total_income'] ?? $item['amount'] ?? null,
                'income_released_at' => $this->timestamp($item['release_time'] ?? $item['release_date'] ?? null),
            ];
        }, $items);
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value)->toDateTimeString();
        }

        return (string) $value;
    }
}
