<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopee Open Platform API (Integration Lab)
    |--------------------------------------------------------------------------
    |
    | Read-only POC configuration. Values come from the environment and are
    | intentionally empty by default: the Integration Lab reports which keys
    | are missing and never exposes the raw credential values to the UI.
    |
    | Hosts:
    |   global    https://partner.shopeemobile.com
    |   sandbox   https://openplatform.sandbox.test-stable.shopee.sg
    |   cn        https://openplatform.shopee.cn
    |   brazil    https://openplatform.shopee.com.br
    */

    'environment' => env('SHOPEE_API_ENVIRONMENT', 'production'),

    'region' => env('SHOPEE_API_REGION', 'global'),

    'host' => env('SHOPEE_API_HOST', ''),

    'partner_id' => env('SHOPEE_PARTNER_ID', ''),

    'partner_key' => env('SHOPEE_PARTNER_KEY', ''),

    'shop_id' => env('SHOPEE_SHOP_ID', ''),

    'access_token' => env('SHOPEE_ACCESS_TOKEN', ''),

    'timeout' => (int) env('SHOPEE_API_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Staging staleness threshold
    |--------------------------------------------------------------------------
    |
    | Minutes after which staged sync data is considered stale and blocks
    | promotion until a fresh sync runs. Defaults to 24 hours.
    */

    'staging_stale_after_minutes' => (int) env('SHOPEE_API_STAGING_STALE_AFTER_MINUTES', 1440),

    /*
    |--------------------------------------------------------------------------
    | Synchronization sample caps
    |--------------------------------------------------------------------------
    |
    | The Integration Lab intentionally stages samples, never full exports.
    | These caps bound each sync call so billing/rate limits are not exceeded:
    |
    |   order_page_size_max  - orders per page (pagination resumes from cursor)
    |   max_pages            - max order pages walked per sync call
    |   income_page_size_max - income rows per sync call (single page)
    |   escrow_limit_max     - escrow orders fetched per sync call
    |
    | Capping NEVER silently drops staged data: previously staged rows are merged
    | (never overwritten), the order cursor is persisted for resume, and the
    | response flags `capped` when more data remains beyond the sample.
    */

    'sync' => [
        'order_page_size_max' => (int) env('SHOPEE_SYNC_ORDER_PAGE_SIZE_MAX', 5),
        'max_pages' => (int) env('SHOPEE_SYNC_MAX_PAGES', 10),
        'income_page_size_max' => (int) env('SHOPEE_SYNC_INCOME_PAGE_SIZE_MAX', 20),
        'escrow_limit_max' => (int) env('SHOPEE_SYNC_ESCROW_LIMIT_MAX', 20),
    ],

];
