<?php

namespace App\Services;

class ShopeeApiResearchService
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_UNVERIFIED = 'unverified';

    public function payload(): array
    {
        return [
            'scope' => [
                'title' => 'Shopee Open Platform API - Feasibility Research',
                'generated_at' => now()->toDateTimeString(),
                'api_version' => 'V2.0',
                'purpose' => 'Assess whether Shopee Open Platform APIs can replace the Excel-based order and income report imports, preserving the Phase 3 reconciliation and Phase 4 Master HPP / HPP-mapping / cost-allocation pipeline.',
                'disclaimer' => 'Research-only page. No credentials are requested, stored, or transmitted. Live verification requires Shopee sandbox credentials and is intentionally outside this task scope.',
            ],
            'capabilities' => $this->capabilities(),
            'endpoints' => $this->endpoints(),
            'fieldMatrix' => [
                'orders' => $this->orderFieldCoverage(),
                'income' => $this->incomeFieldCoverage(),
            ],
            'importRates' => $this->importerReplacementAnalysis(),
            'readiness' => $this->readiness(),
            'architecture' => $this->architecture(),
        ];
    }

    private function capabilities(): array
    {
        return [
            [
                'name' => 'Order retrieval',
                'status' => self::STATUS_AVAILABLE,
                'description' => 'Searchable order list with status filters and full per-order detail (items, timeline, shipping).',
                'apis' => 'v2.order.get_order_list, v2.order.get_order_detail',
            ],
            [
                'name' => 'Order accounting / escrow breakdown',
                'status' => self::STATUS_AVAILABLE,
                'description' => 'Per-order buyer paid, discounts, Shopee Coins, vouchers, shipping, item-level prices and SKUs.',
                'apis' => 'v2.payment.get_escrow_detail, v2.payment.get_escrow_detail_batch',
            ],
            [
                'name' => 'Income detail (order-level)',
                'status' => self::STATUS_AVAILABLE,
                'description' => 'Granular income consistent with Seller Center "Income Details", segmented by income status and payout stage.',
                'apis' => 'v2.payment.get_income_detail',
            ],
            [
                'name' => 'Payout detail',
                'status' => self::STATUS_PARTIAL,
                'description' => 'Payout amount, currency, FX rate, associated order income and adjustments. Cross-Border (CB) sellers only.',
                'apis' => 'v2.payment.get_payout_detail',
            ],
            [
                'name' => 'Returns & refunds',
                'status' => self::STATUS_AVAILABLE,
                'description' => 'Return/refund requests with refund amount, status flow, and whether raised before (RRBOC) or after (RRAOC) order completion.',
                'apis' => 'v2.returns.get_return_list, v2.returns.get_return_detail',
            ],
            [
                'name' => 'Product catalog & variants',
                'status' => self::STATUS_AVAILABLE,
                'description' => 'Item base info plus per-model price/stock when variants exist. Deleted items remain queryable for 90 days.',
                'apis' => 'v2.product.get_item_base_info, v2.product.get_model_list',
            ],
            [
                'name' => 'Sensitive buyer / recipient data',
                'status' => self::STATUS_PARTIAL,
                'description' => 'Recipient name, phone, full address, city are masked by default. Requires a Shopee sensitive-data access request (penetration-test report for ISV markets + IP whitelisting).',
                'apis' => 'v2.order.get_order_detail (recipient_address)',
            ],
            [
                'name' => 'Voucher / campaign attribution',
                'status' => self::STATUS_UNAVAILABLE,
                'description' => 'The exact voucher_code used on an order and per-item promotion breakdown are not exposed at the required granularity.',
                'apis' => '-',
            ],
            [
                'name' => 'Historical payout backfill',
                'status' => self::STATUS_PARTIAL,
                'description' => 'Released income is queryable by payout-released date window; other income statuses only return records currently in that status.',
                'apis' => 'v2.payment.get_income_detail (Released)',
            ],
        ];
    }

    private function endpoints(): array
    {
        return [
            [
                'endpoint' => 'v2.order.get_order_list',
                'method' => 'GET',
                'path' => '/api/v2/order/get_order_list',
                'purpose' => 'Search orders, optionally filtered by status within a bounded time window.',
                'pagination' => 'page_size (1-100), cursor / next_cursor',
                'keyParams' => 'order_status, time_range_field, time_from, time_to',
                'source' => 'https://open.shopee.com/documents/v2/v2.order.get_order_list',
            ],
            [
                'endpoint' => 'v2.order.get_order_detail',
                'method' => 'GET',
                'path' => '/api/v2/order/get_order_detail',
                'purpose' => 'Full order detail: items, payment timeline, shipping, recipient address.',
                'pagination' => 'order_sn(s)',
                'keyParams' => 'order_sn / order_sn_list',
                'source' => 'https://open.shopee.com/documents/v2/v2.order.get_order_detail',
            ],
            [
                'endpoint' => 'v2.payment.get_escrow_detail',
                'method' => 'GET',
                'path' => '/api/v2/payment/get_escrow_detail',
                'purpose' => 'Per-order accounting: escrow amount, buyer total, coins, vouchers, item prices and SKUs.',
                'pagination' => 'order_sn',
                'keyParams' => 'order_sn',
                'source' => 'https://open.shopee.com/documents/v2/v2.payment.get_escrow_detail',
            ],
            [
                'endpoint' => 'v2.payment.get_escrow_detail_batch',
                'method' => 'GET',
                'path' => '/api/v2/payment/get_escrow_detail_batch',
                'purpose' => 'Batch order accounting, recommended for 1-20 orders (max 50) per request.',
                'pagination' => 'order_sn_list',
                'keyParams' => 'order_sn_list',
                'source' => 'https://open.shopee.com/documents/v2/v2.payment.get_escrow_detail_batch',
            ],
            [
                'endpoint' => 'v2.payment.get_escrow_list',
                'method' => 'GET',
                'path' => '/api/v2/payment/get_escrow_list',
                'purpose' => 'List completed orders by escrow release time (feeds order completion date).',
                'pagination' => 'page_size, page_no',
                'keyParams' => 'release_time_from, release_time_to',
                'source' => 'https://open.shopee.com/documents/v2/v2.payment.get_escrow_list',
            ],
            [
                'endpoint' => 'v2.payment.get_income_detail',
                'method' => 'GET',
                'path' => '/api/v2/payment/get_income_detail',
                'purpose' => 'Order-level income detail aligned with Seller Center "Income Details"; fields adapt by shop type and income status.',
                'pagination' => 'page_size + pagination',
                'keyParams' => 'income status, date_from / date_to (Released only)',
                'source' => 'https://open.shopee.com/documents/v2/v2.payment.get_income_detail',
            ],
            [
                'endpoint' => 'v2.payment.get_payout_detail',
                'method' => 'GET',
                'path' => '/api/v2/payment/get_payout_detail',
                'purpose' => 'Cross-Border payout data: payout amount, currency, FX rate, associated order income and adjustments.',
                'pagination' => 'page_size, page_no',
                'keyParams' => 'payout_time_from, payout_time_to',
                'source' => 'https://open.shopee.com/documents/v2/v2.payment.get_payout_detail',
            ],
            [
                'endpoint' => 'v2.returns.get_return_list',
                'method' => 'GET',
                'path' => '/api/v2/returns/get_return_list',
                'purpose' => 'List return/refund applications for a shop; multiple return_sn can reference one order.',
                'pagination' => 'page_size, page_no, more flag',
                'keyParams' => 'status, create_time_from, create_time_to',
                'source' => 'https://open.shopee.com/documents/v2/v2.returns.get_return_list',
            ],
            [
                'endpoint' => 'v2.returns.get_return_detail',
                'method' => 'GET',
                'path' => '/api/v2/returns/get_return_detail',
                'purpose' => 'Return detail: refund amount, items, user info, negotiation status.',
                'pagination' => 'return_sn',
                'keyParams' => 'return_sn',
                'source' => 'https://open.shopee.com/documents/v2/v2.returns.get_return_detail',
            ],
            [
                'endpoint' => 'v2.product.get_item_base_info',
                'method' => 'GET',
                'path' => '/api/v2/product/get_item_base_info',
                'purpose' => 'Product base info (name, item_sku, create_time); price_info only when the item has no models.',
                'pagination' => 'item_id_list',
                'keyParams' => 'item_id_list',
                'source' => 'https://open.shopee.com/documents/v2/v2.product.get_item_base_info',
            ],
            [
                'endpoint' => 'v2.product.get_model_list',
                'method' => 'GET',
                'path' => '/api/v2/product/get_model_list',
                'purpose' => 'Variant (model) price and stock for items with variants.',
                'pagination' => 'item_id',
                'keyParams' => 'item_id',
                'source' => 'https://open.shopee.com/documents/v2/v2.product.get_model_list',
            ],
        ];
    }

    private function orderFieldCoverage(): array
    {
        return [
            ['field' => 'order_number', 'source' => 'get_order_list / get_order_detail .order_sn', 'status' => self::STATUS_AVAILABLE, 'note' => 'Shopee unique order identifier; universe to import from.'],
            ['field' => 'order_status', 'source' => 'get_order_detail .order_status', 'status' => self::STATUS_AVAILABLE, 'note' => 'UNPAID / READY_TO_SHIP / PROCESSED / SHIPPED / COMPLETED / IN_CANCEL / CANCELLED.'],
            ['field' => 'cancellation_reason', 'source' => 'get_order_detail .cancel_reason', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'return_status', 'source' => 'get_return_list .status (join by order_sn)', 'status' => self::STATUS_AVAILABLE, 'note' => 'Multiple return_sn per order possible; map latest or aggregate.'],
            ['field' => 'tracking_number', 'source' => 'get_order_detail .package_list[].package_number', 'status' => self::STATUS_PARTIAL, 'note' => 'Per package; multiple packages per order.'],
            ['field' => 'shipping_option', 'source' => 'get_order_detail .package_list[].shipping_carrier', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official Order Report mapping (Shopee FAQ 639).'],
            ['field' => 'order_type', 'source' => 'get_order_detail .order_type', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Field exists; exact value set needs live confirmation.'],
            ['field' => 'payment_method', 'source' => 'get_order_detail .payment_method', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official Order Report mapping (Shopee FAQ 639).'],
            ['field' => 'parent_sku', 'source' => 'get_escrow_detail .order_income.items[].item_sku', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Parent SKU Reference No." mapping (FAQ 639).'],
            ['field' => 'product_name', 'source' => 'get_escrow_detail .order_income.items[].item_name', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Product Name" mapping (FAQ 639).'],
            ['field' => 'sku_reference', 'source' => 'get_escrow_detail .order_income.items[].model_sku', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "SKU Reference No." mapping (FAQ 639).'],
            ['field' => 'variation_name', 'source' => 'get_escrow_detail .order_income.items[].model_name', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Variation Name" mapping (FAQ 639).'],
            ['field' => 'original_price', 'source' => 'get_escrow_detail .order_income.items[].original_price', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Original Price" mapping (FAQ 639).'],
            ['field' => 'discounted_price', 'source' => 'get_escrow_detail .order_income.items[].discounted_price', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Deal Price" mapping (FAQ 639).'],
            ['field' => 'quantity', 'source' => 'get_escrow_detail .order_income.items[].quantity_purchased', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Quantity" mapping (FAQ 639).'],
            ['field' => 'returned_quantity', 'source' => 'get_return_detail .item quantity (join)', 'status' => self::STATUS_PARTIAL, 'note' => 'Not in order/escrow payload; reconstruct from returns API.'],
            ['field' => 'order_subtotal', 'source' => 'derived: sum(discounted_price * (quantity - returned_quantity))', 'status' => self::STATUS_UNVERIFIED, 'note' => 'No single API field; compute exactly as the importer does.'],
            ['field' => 'total_payment', 'source' => 'get_escrow_detail .order_income.buyer_total_amount', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Total Amount" mapping (FAQ 639).'],
            ['field' => 'buyer_shipping_paid', 'source' => 'get_order_detail .estimated_shipping_fee / actual_shipping_fee', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'estimated_shipping_discount', 'source' => 'derived from income shipping components', 'status' => self::STATUS_UNVERIFIED, 'note' => ''],
            ['field' => 'estimated_shipping_cost', 'source' => 'get_order_detail .actual_shipping_fee (proxy)', 'status' => self::STATUS_PARTIAL, 'note' => ''],
            ['field' => 'product_count', 'source' => 'derived: count(items)', 'status' => self::STATUS_UNVERIFIED, 'note' => ''],
            ['field' => 'total_weight', 'source' => 'get_order_detail .package_list[].weight', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Not confirmed in doc excerpt; verify live.'],
            ['field' => 'buyer_username', 'source' => 'get_order_detail .buyer_username', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'recipient_name', 'source' => 'get_order_detail .recipient_address.name', 'status' => self::STATUS_PARTIAL, 'note' => 'Sensitive; masked by default until approved.'],
            ['field' => 'buyer_phone', 'source' => 'get_order_detail .recipient_address.phone', 'status' => self::STATUS_PARTIAL, 'note' => 'Sensitive; masked by default until approved.'],
            ['field' => 'shipping_address', 'source' => 'get_order_detail .recipient_address.full_address', 'status' => self::STATUS_PARTIAL, 'note' => 'Sensitive; masked by default until approved.'],
            ['field' => 'city', 'source' => 'get_order_detail .recipient_address.city', 'status' => self::STATUS_PARTIAL, 'note' => 'Sensitive; masked by default until approved.'],
            ['field' => 'province', 'source' => 'get_order_detail .recipient_address.state', 'status' => self::STATUS_PARTIAL, 'note' => 'Official mapping (FAQ 639); masked by default.'],
            ['field' => 'order_created_at', 'source' => 'get_order_list .create_time', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'payment_at', 'source' => 'get_order_detail .pay_time', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Order Paid Time" mapping (FAQ 639).'],
            ['field' => 'shipped_at', 'source' => 'get_order_detail .pickup_done_time', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Ship Time" mapping (FAQ 639).'],
            ['field' => 'completed_at', 'source' => 'get_escrow_list .escrow_release_time', 'status' => self::STATUS_AVAILABLE, 'note' => 'Official "Order Complete Time" mapping (FAQ 639).'],
        ];
    }

    private function incomeFieldCoverage(): array
    {
        return [
            ['field' => 'order_number', 'source' => 'get_income_detail .order_sn', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'row_type', 'source' => 'get_income_detail .description', 'status' => self::STATUS_AVAILABLE, 'note' => 'e.g. "Order Income", "Adjustment".'],
            ['field' => 'application_number', 'source' => 'get_return_list .return_sn (join)', 'status' => self::STATUS_AVAILABLE, 'note' => 'Income rows referencing a return/refund.'],
            ['field' => 'product_id', 'source' => 'not exposed at income granularity', 'status' => self::STATUS_UNAVAILABLE, 'note' => 'Income detail is order-level; product breakdown requires escrow join.'],
            ['field' => 'product_name', 'source' => 'get_escrow_detail .items[].item_name (join order_sn)', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'order_created_at', 'source' => 'get_escrow_detail .order_income.order_create_time', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Field name to confirm live.'],
            ['field' => 'fund_released_at', 'source' => 'get_income_detail (Released -> payout released date) / get_escrow_list .escrow_release_time', 'status' => self::STATUS_AVAILABLE, 'note' => 'Released records queryable by date window only.'],
            ['field' => 'release_method', 'source' => 'get_income_detail .payment_method / status', 'status' => self::STATUS_PARTIAL, 'note' => ''],
            ['field' => 'order_type', 'source' => 'get_income_detail', 'status' => self::STATUS_UNVERIFIED, 'note' => ''],
            ['field' => 'total_income', 'source' => 'get_income_detail .income_detail_list_item.amount', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'product_price', 'source' => 'get_escrow_detail .order_income.escrow_amount', 'status' => self::STATUS_PARTIAL, 'note' => 'Order-level aggregate, not per income row.'],
            ['field' => 'buyer_shipping_paid', 'source' => 'get_income_detail shipping component', 'status' => self::STATUS_PARTIAL, 'note' => ''],
            ['field' => 'platform_fee', 'source' => 'get_income_detail seller commission component', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'order_processing_fee', 'source' => 'get_income_detail transaction / processing fee component', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'shipping_fee', 'source' => 'get_income_detail shipping fee component', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'service_fee', 'source' => 'get_income_detail service fee component', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'promotion_fee', 'source' => 'get_income_detail coins / seller-voucher components', 'status' => self::STATUS_PARTIAL, 'note' => ''],
            ['field' => 'other_fee', 'source' => 'get_income_detail other components', 'status' => self::STATUS_PARTIAL, 'note' => ''],
            ['field' => 'refund_to_buyer', 'source' => 'get_return_list .refund_amount (join order_sn)', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'buyer_username', 'source' => 'get_order_detail .buyer_username (join)', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'buyer_paid_amount', 'source' => 'get_escrow_detail .order_income.buyer_total_amount', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'buyer_payment_method', 'source' => 'get_income_detail .payment_method', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'shipping_provider', 'source' => 'get_order_detail .package_list[].shipping_carrier (join)', 'status' => self::STATUS_AVAILABLE, 'note' => ''],
            ['field' => 'voucher_code', 'source' => 'not exposed', 'status' => self::STATUS_UNAVAILABLE, 'note' => 'No API field; keep Excel fallback if required by business.'],
        ];
    }

    private function importerReplacementAnalysis(): array
    {
        return [
            [
                'report' => 'Order Report',
                'rating' => 'High',
                'severity' => self::STATUS_AVAILABLE,
                'summary' => 'Feasible: get_order_list (cursor) -> get_order_detail / get_escrow_detail per order.',
                'caveats' => 'Fan-out of ~N detail calls per sync; delivery address/phone masked unless sensitive-data access approved; keep Excel import as fallback.',
            ],
            [
                'report' => 'Income Report',
                'rating' => 'High',
                'severity' => self::STATUS_AVAILABLE,
                'summary' => 'Feasible: get_income_detail mirrors Seller Center "Income Details"; historical Released by payout date window.',
                'caveats' => 'product_id and voucher_code not exposed; field naming must be confirmed live; initial backfill window depends on platform retention.',
            ],
            [
                'report' => 'Both reports (full replacement)',
                'rating' => 'Medium',
                'severity' => self::STATUS_PARTIAL,
                'summary' => 'Feasible once credential + sensitive-data prerequisites are met; normalized into the same marketplace_orders / marketplace_income schema.',
                'caveats' => 'Field parity gaps require mapping shims; sensitive-data approval adds compliance lead time; rate limits require throttling below a few QPS.',
            ],
        ];
    }

    private function readiness(): array
    {
        return [
            ['item' => 'API documentation reviewed and mapped to canonical schema', 'status' => self::STATUS_AVAILABLE, 'note' => 'Completed in this research (FAQ 639 + API docs).'],
            ['item' => 'Sandbox testing with shopee test-app credentials', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Credential required for live verification.'],
            ['item' => 'Partner app enrollment (partner_id / partner_key)', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Required before any API call.'],
            ['item' => 'OAuth authorization and 4-hour access_token refresh flow', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Shop-level authorization via redirect; store refresh_token encrypted.'],
            ['item' => 'IP whitelisting for server environments', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Required for all environments.'],
            ['item' => 'Sensitive-data access request (delivery address / phone)', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Penetration-test report required for ISV markets; else store masked values.'],
            ['item' => 'Rate-limit budgeting, retry and backoff', 'status' => self::STATUS_AVAILABLE, 'note' => 'No fixed public QPS numbers; throttle to ~1-2 QPS, exponential backoff on HTTP 429, keep success rate above 90%.'],
            ['item' => 'Historical backfill strategy for orders and income', 'status' => self::STATUS_UNVERIFIED, 'note' => 'Must be designed to avoid data loss before platform retention cut-off.'],
            ['item' => 'Excel import retained as fallback', 'status' => self::STATUS_AVAILABLE, 'note' => 'Recommended: API pipeline is additive, not replacing the importer.'],
        ];
    }

    private function architecture(): array
    {
        return [
            '1. Store Shopee credentials per user encrypted (partner_id, partner_key, refresh_token, shop_id). Never in logs or frontend props.',
            '2. Scheduled/orchestrated sync jobs pull raw payloads (orders, escrow, income, returns) into staging storage.',
            '3. Normalize raw payloads into the existing marketplace_orders / marketplace_income rows using the same row shapes as OrderReportImporter / IncomeReportImporter, preserving line_identity and Phase 4 contracts.',
            '4. Keep the existing reconciliation (Phase 3), income reconciliation, Master HPP mapping and order-cost allocation (Phase 4) pipeline untouched.',
            '5. Reconstruct returned_quantity, subtotal and completion dates from escrow/returns joins exactly as the Excel importer computes them.',
            '6. Mask sensitive fields by default; enable full delivery address only through an approved Shopee sensitive-data request.',
            '7. Validate against Excel-imported data during a shadow period before switching the primary ingestion path.',
        ];
    }
}
