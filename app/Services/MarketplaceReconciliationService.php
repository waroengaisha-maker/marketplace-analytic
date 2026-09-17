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
        $query = $this->lineScope($userId, $from, $to, $parameters);

        $sortFields = [
            'business_status' => DB::raw('rows.business_status'),
            'settlement_status' => DB::raw('rows.business_status'),
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

    /**
     * Builds the row-level reconciliation scope (one row per order line) with
     * search, status, HPP status, and column filters applied — without any
     * final ordering so callers can add their own column list and ordering.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function lineScope(int $userId, ?string $from, ?string $to, array $parameters): Builder
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
                foreach (['order_number', 'order_product_name', 'variation_name', 'tracking_number', 'buyer_username', 'sku_reference'] as $field) {
                    $query->orWhere("rows.{$field}", 'like', $like);
                }

                $query->orWhereRaw("{$statusExpression} LIKE ?", [$like]);
            });
        }

        if ($statuses !== []) {
            $query->whereIn(DB::raw($statusExpression), $statuses);
        }

        $hppStatuses = array_values(array_filter((array) ($parameters['hpp_statuses'] ?? [])));
        if ($hppStatuses !== []) {
            $query->where(function (Builder $query) use ($hppStatuses): void {
                foreach ($hppStatuses as $status) {
                    if ($status === 'no_allocation') {
                        $query->orWhereNull('rows.cost_status');
                    } else {
                        $query->orWhere('rows.cost_status', $status);
                    }
                }
            });
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

        return $query;
    }

    /**
     * Base line-level query for order summaries and totals, reusing the
     * reconciliation financial model (subtotal, fees, tax, penghasilan, HPP)
     * so totals match the Reconciliation page definitions.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function orderSummaryLines(int $userId, ?string $from, ?string $to, array $parameters): Builder
    {
        return DB::query()
            ->fromSub($this->lineScope($userId, $from, $to, $parameters), 'l')->selectRaw("
                l.order_number,
                l.order_created_at,
                l.buyer_username,
                l.business_status AS status,
                l.product_key,
                l.variation_key,
                l.order_product_name,
                l.variation_name,
                l.item_index,
                l.quantity,
                l.returned_quantity,
                (CASE WHEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) > 0 THEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) ELSE 0 END) AS net_quantity,
                (COALESCE(l.discounted_price, 0) * (CASE WHEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) > 0 THEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) ELSE 0 END)) AS subtotal,
                (-ABS(COALESCE(l.platform_fee, 0))) AS admin,
                (-ABS(COALESCE(l.free_shipping_xtra_fee, 0))) AS shipping,
                (-ABS(COALESCE(l.promo_xtra_service_fee, 0))) AS promo,
                (-ABS(COALESCE(l.order_processing_fee, 0))) AS processing,
                (-ABS(COALESCE(l.pph22, 0))) AS tax,
                (CASE WHEN l.cost_status = 'ok' THEN COALESCE(l.total_hpp, 0) ELSE 0 END) AS hpp
            ");
    }

    /**
     * Aggregated totals (subtotal, total_fee, tax, penghasilan, hpp, laba) across
     * all filtered orders, ignoring pagination. Used for summary cards that
     * must reflect the active date range / search.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, float>
     */
    public function orderSummariesTotals(int $userId, ?string $from, ?string $to, array $parameters): array
    {
        $totals = DB::query()
            ->fromSub($this->orderSummaryLines($userId, $from, $to, $parameters), 'g')
            ->selectRaw('
                COALESCE(SUM(g.subtotal), 0) AS subtotal,
                COALESCE(SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing), 0) AS total_fee,
                COALESCE(SUM(g.tax), 0) AS tax,
                COALESCE(SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax), 0) AS penghasilan,
                COALESCE(SUM(g.hpp), 0) AS hpp,
                COALESCE((SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) - SUM(g.hpp), 0) AS laba
            ')
            ->first();

        return [
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'total_fee' => (float) ($totals->total_fee ?? 0),
            'tax' => (float) ($totals->tax ?? 0),
            'penghasilan' => (float) ($totals->penghasilan ?? 0),
            'hpp' => (float) ($totals->hpp ?? 0),
            'laba' => (float) ($totals->laba ?? 0),
        ];
    }

    /**
     * Server-side paginated list grouped per order number, reusing the exact
     * reconciliation financial model (subtotal, fees, tax, penghasilan, HPP,
     * laba) so totals match the Reconciliation page definitions.
     *
     * @param  array<string, mixed>  $parameters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function orderSummariesPage(int $userId, ?string $from, ?string $to, array $parameters): LengthAwarePaginator
    {
        $lines = $this->orderSummaryLines($userId, $from, $to, $parameters);

        $query = DB::query()->fromSub($lines, 'g')
            ->selectRaw("
                g.order_number,
                MIN(g.order_created_at) AS order_created_at,
                MIN(g.buyer_username) AS buyer_username,
                COUNT(*) AS line_count,
                SUM(g.quantity) AS quantity,
                SUM(g.net_quantity) AS net_quantity,
                (CASE WHEN SUM(g.net_quantity) > 0 THEN SUM(g.subtotal) / SUM(g.net_quantity) ELSE 0 END) AS discounted_price,
                SUM(g.subtotal) AS subtotal,
                SUM(g.admin) AS admin,
                SUM(g.shipping) AS shipping,
                SUM(g.promo) AS promo,
                SUM(g.processing) AS processing,
                SUM(g.tax) AS tax,
                SUM(g.hpp) AS hpp,
                GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') AS statuses,
                (CASE
                    WHEN GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') LIKE '%Invalid%' THEN 0
                    WHEN GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') LIKE '%Cancelled%' THEN 1
                    WHEN GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') LIKE '%Returned%' THEN 2
                    WHEN GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') LIKE '%Refunded%' THEN 3
                    WHEN GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') LIKE '%Partially Refunded%' THEN 4
                    WHEN GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') LIKE '%Unmatched%' THEN 5
                    ELSE 6
                END) AS status_rank,
                (SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing)) AS total_fee,
                (SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) AS penghasilan,
                ((SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) - SUM(g.hpp)) AS laba
            ")
            ->groupBy('g.order_number');

        $allowlist = [
            'order_number' => 'g.order_number',
            'order_created_at' => 'order_created_at',
            'buyer_username' => 'buyer_username',
            'business_status' => 'status_rank',
            'line_count' => 'line_count',
            'net_quantity' => 'net_quantity',
            'discounted_price' => 'discounted_price',
            'subtotal' => 'subtotal',
            'admin' => 'admin',
            'shipping' => 'shipping',
            'promo' => 'promo',
            'processing' => 'processing',
            'total_fee' => 'total_fee',
            'tax' => 'tax',
            'hpp' => 'hpp',
            'penghasilan' => 'penghasilan',
            'laba' => 'laba',
        ];
        $sortField = (string) ($parameters['sort_field'] ?? 'order_created_at');
        $sortOrder = strtolower((string) ($parameters['sort_order'] ?? 'desc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($allowlist[$sortField] ?? 'order_created_at', $sortOrder);
        $query->orderBy('g.order_number');

        $perPage = min(max((int) ($parameters['per_page'] ?? 25), 10), 100);
        $page = $query->paginate($perPage)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (object $row): array => $this->orderSummary($row)));

        return $page;
    }

    /**
     * All filtered order summaries without pagination — used by the
     * Orders export (sheet 1) to include every row in the date range.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function orderSummariesAll(int $userId, ?string $from, ?string $to, array $parameters): array
    {
        $lines = $this->orderSummaryLines($userId, $from, $to, $parameters);

        $query = DB::query()->fromSub($lines, 'g')
            ->selectRaw("
                g.order_number,
                MIN(g.order_created_at) AS order_created_at,
                MIN(g.buyer_username) AS buyer_username,
                COUNT(*) AS line_count,
                SUM(g.quantity) AS quantity,
                SUM(g.net_quantity) AS net_quantity,
                (CASE WHEN SUM(g.net_quantity) > 0 THEN SUM(g.subtotal) / SUM(g.net_quantity) ELSE 0 END) AS discounted_price,
                SUM(g.subtotal) AS subtotal,
                SUM(g.admin) AS admin,
                SUM(g.shipping) AS shipping,
                SUM(g.promo) AS promo,
                SUM(g.processing) AS processing,
                SUM(g.tax) AS tax,
                SUM(g.hpp) AS hpp,
                GROUP_CONCAT(DISTINCT g.status SEPARATOR ',') AS statuses,
                (SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing)) AS total_fee,
                (SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) AS penghasilan,
                ((SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) - SUM(g.hpp)) AS laba
            ")
            ->groupBy('g.order_number')
            ->orderBy('order_created_at', 'desc')
            ->orderBy('g.order_number');

        return $query->get()->map(fn (object $row): array => $this->orderSummary($row))->all();
    }

    /**
     * Aggregated customer totals (order_count, subtotal, hpp, laba) across all
     * filtered orders, ignoring pagination. Used for the Customers summary
     * cards that must reflect the active date range / search.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, float|int>
     */
    public function customerSummariesTotals(int $userId, ?string $from, ?string $to, array $parameters): array
    {
        $lines = $this->orderSummaryLines($userId, $from, $to, $parameters);

        $totals = DB::query()
            ->fromSub($lines, 'g')
            ->selectRaw('
                COUNT(DISTINCT g.order_number) AS order_count,
                COALESCE(SUM(g.subtotal), 0) AS subtotal,
                COALESCE(SUM(g.hpp), 0) AS hpp,
                COALESCE((SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) - SUM(g.hpp), 0) AS laba
            ')
            ->first();

        return [
            'order_count' => (int) ($totals->order_count ?? 0),
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'hpp' => (float) ($totals->hpp ?? 0),
            'laba' => (float) ($totals->laba ?? 0),
        ];
    }

    /**
     * Server-side paginated list grouped per buyer username, reusing the exact
     * reconciliation financial model (subtotal, fees, tax, penghasilan, HPP,
     * laba). Rows without a username are grouped under a `(tanpa username)`
     * fallback so no purchase is silently dropped.
     *
     * @param  array<string, mixed>  $parameters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function customerSummariesPage(int $userId, ?string $from, ?string $to, array $parameters): LengthAwarePaginator
    {
        $buyerExpression = "COALESCE(NULLIF(TRIM(g.buyer_username), ''), '(tanpa username)')";
        $lines = $this->orderSummaryLines($userId, $from, $to, $parameters);

        $query = DB::query()->fromSub($lines, 'g')
            ->selectRaw("
                {$buyerExpression} AS buyer_username,
                COUNT(DISTINCT g.order_number) AS order_count,
                COUNT(*) AS line_count,
                SUM(g.net_quantity) AS net_quantity,
                SUM(g.subtotal) AS subtotal,
                (SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing)) AS total_fee,
                (SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) AS penghasilan,
                SUM(g.hpp) AS hpp,
                ((SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) - SUM(g.hpp)) AS laba
            ")
            ->groupBy(DB::raw($buyerExpression));

        $allowlist = [
            'buyer_username' => DB::raw($buyerExpression),
            'order_count' => 'order_count',
            'line_count' => 'line_count',
            'net_quantity' => 'net_quantity',
            'subtotal' => 'subtotal',
            'total_fee' => 'total_fee',
            'penghasilan' => 'penghasilan',
            'hpp' => 'hpp',
            'laba' => 'laba',
        ];
        $sortField = (string) ($parameters['sort_field'] ?? 'laba');
        $sortOrder = strtolower((string) ($parameters['sort_order'] ?? 'desc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($allowlist[$sortField] ?? DB::raw($buyerExpression), $sortOrder);
        $query->orderBy(DB::raw($buyerExpression));

        $perPage = min(max((int) ($parameters['per_page'] ?? 25), 10), 100);
        $page = $query->paginate($perPage)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (object $row): array => $this->customerSummary($row)));

        return $page;
    }

    /**
     * All filtered customer summaries without pagination — used by the
     * Customers export to include every customer in the date range.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function customerSummariesAll(int $userId, ?string $from, ?string $to, array $parameters): array
    {
        $buyerExpression = "COALESCE(NULLIF(TRIM(g.buyer_username), ''), '(tanpa username)')";
        $lines = $this->orderSummaryLines($userId, $from, $to, $parameters);

        $query = DB::query()->fromSub($lines, 'g')
            ->selectRaw("
                {$buyerExpression} AS buyer_username,
                COUNT(DISTINCT g.order_number) AS order_count,
                COUNT(*) AS line_count,
                SUM(g.net_quantity) AS net_quantity,
                SUM(g.subtotal) AS subtotal,
                (SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing)) AS total_fee,
                (SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) AS penghasilan,
                SUM(g.hpp) AS hpp,
                ((SUM(g.subtotal) + SUM(g.admin) + SUM(g.shipping) + SUM(g.promo) + SUM(g.processing) + SUM(g.tax)) - SUM(g.hpp)) AS laba
            ")
            ->groupBy(DB::raw($buyerExpression))
            ->orderBy(DB::raw($buyerExpression));

        return $query->get()->map(fn (object $row): array => $this->customerSummary($row))->all();
    }

    /**
     * Item-level purchase history for a single buyer within the date range —
     * the detail modal payload for the Customers page. Lists every line item
     * per transaction (complete with order number) sorted by order date,
     * without aggregating across orders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function customerHistory(int $userId, string $buyer, ?string $from, ?string $to): array
    {
        $lines = $this->orderSummaryLines($userId, $from, $to, []);

        $query = DB::query()->fromSub($lines, 'g')
            ->selectRaw('
                g.order_number,
                g.order_created_at,
                g.item_index,
                g.status,
                g.order_product_name AS product_name,
                g.variation_name,
                g.net_quantity,
                g.subtotal,
                (g.admin + g.shipping + g.promo + g.processing) AS total_fee,
                (g.subtotal + g.admin + g.shipping + g.promo + g.processing + g.tax) AS penghasilan,
                g.hpp,
                ((g.subtotal + g.admin + g.shipping + g.promo + g.processing + g.tax) - g.hpp) AS laba
            ');

        $query->where(function (Builder $query) use ($buyer): void {
            if ($buyer === '(tanpa username)') {
                $query->whereNull('g.buyer_username')->orWhereRaw("TRIM(g.buyer_username) = ''");
            } else {
                $query->whereRaw('TRIM(g.buyer_username) = ?', [$buyer]);
            }
        });

        return $query->orderBy('g.order_created_at', 'desc')
            ->orderBy('g.item_index')
            ->get()
            ->map(fn (object $row): array => [
                'order_number' => $row->order_number,
                'order_created_at' => $row->order_created_at,
                'business_status' => $row->status,
                'product_name' => $row->product_name,
                'variation_name' => $row->variation_name,
                'net_quantity' => (float) $row->net_quantity,
                'subtotal' => (float) $row->subtotal,
                'total_fee' => (float) $row->total_fee,
                'penghasilan' => (float) $row->penghasilan,
                'hpp' => (float) $row->hpp,
                'laba' => (float) $row->laba,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSummary(object $row): array
    {
        return [
            'buyer_username' => $row->buyer_username,
            'order_count' => (int) $row->order_count,
            'line_count' => (int) $row->line_count,
            'net_quantity' => (float) ($row->net_quantity ?? 0),
            'subtotal' => (float) $row->subtotal,
            'total_fee' => (float) $row->total_fee,
            'penghasilan' => (float) $row->penghasilan,
            'hpp' => (float) $row->hpp,
            'laba' => (float) $row->laba,
        ];
    }

    /**
     * Aggregates one order's line-level financials from the store object rows.
     *
     * @return array<string, mixed>
     */
    private function orderSummary(object $row): array
    {
        return [
            'order_number' => $row->order_number,
            'order_created_at' => $row->order_created_at,
            'buyer_username' => $row->buyer_username,
            'business_status' => $this->orderStatus(explode(',', (string) ($row->statuses ?? ''))),
            'line_count' => (int) $row->line_count,
            'quantity' => (float) ($row->quantity ?? 0),
            'net_quantity' => (float) $row->net_quantity,
            'discounted_price' => (float) round((float) ($row->discounted_price ?? 0), 2),
            'subtotal' => (float) $row->subtotal,
            'admin' => (float) $row->admin,
            'shipping' => (float) $row->shipping,
            'promo' => (float) $row->promo,
            'processing' => (float) $row->processing,
            'tax' => (float) $row->tax,
            'total_fee' => (float) $row->total_fee,
            'penghasilan' => (float) $row->penghasilan,
            'hpp' => (float) $row->hpp,
            'laba' => (float) $row->laba,
        ];
    }

    /**
     * Orders a status set by severity so a single status represents an order
     * that contains mixed line statuses (worst status wins).
     */
    private function orderStatus(array $statuses): string
    {
        $precedence = ['Invalid', 'Cancelled', 'Returned', 'Refunded', 'Partially Refunded', 'Unmatched', 'Settled'];

        foreach ($precedence as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return 'Unmatched';
    }

    /**
     * @return array<int, object>
     */
    public function orderLines(int $userId, string $orderNumber): array
    {
        return $this->joinedQuery($userId, true)
            ->where('orders.order_number', $orderNumber)
            ->orderBy('orders.item_index')
            ->get()
            ->map(function (object $row): object {
                $row = $this->calculateFinancials($row);
                $row->admin = -abs((float) ($row->platform_fee ?? 0));
                $row->shipping = -abs((float) ($row->free_shipping_xtra_fee ?? 0));
                $row->promo = -abs((float) ($row->promo_xtra_service_fee ?? 0));
                $row->processing = -abs((float) ($row->order_processing_fee ?? 0));

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * Per-item export rows for every filtered order (used by the Excel
     * "Detail Per Item" sheet). Reuses lineScope so dates, search, and
     * status filters match the list page exactly.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function orderExportLines(int $userId, ?string $from, ?string $to, array $parameters): array
    {
        $lines = DB::query()
            ->fromSub($this->lineScope($userId, $from, $to, $parameters), 'l')
            ->selectRaw('
                l.order_number,
                l.order_created_at,
                l.business_status,
                l.buyer_username,
                l.order_product_name,
                l.variation_name,
                l.item_index,
                l.quantity,
                l.returned_quantity,
                (CASE WHEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) > 0 THEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) ELSE 0 END) AS net_quantity,
                l.discounted_price,
                (COALESCE(l.discounted_price, 0) * (CASE WHEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) > 0 THEN COALESCE(l.quantity, 0) - COALESCE(l.returned_quantity, 0) ELSE 0 END)) AS subtotal,
                (-ABS(COALESCE(l.platform_fee, 0))) AS admin,
                (-ABS(COALESCE(l.free_shipping_xtra_fee, 0))) AS shipping,
                (-ABS(COALESCE(l.promo_xtra_service_fee, 0))) AS promo,
                (-ABS(COALESCE(l.order_processing_fee, 0))) AS processing,
                (-ABS(COALESCE(l.pph22, 0))) AS tax,
                l.cost_status,
                l.total_hpp
            ')
            ->orderBy('l.order_number')
            ->orderBy('l.item_index')
            ->get();

        return $lines->map(function (object $row): array {
            $costStatus = trim((string) ($row->cost_status ?? ''));
            $hppStatus = $costStatus !== '' ? $costStatus : 'no_allocation';
            $totalFee = (float) $row->admin + (float) $row->shipping + (float) $row->promo + (float) $row->processing;
            $penghasilan = (float) $row->subtotal + $totalFee + (float) $row->tax;
            $hpp = $hppStatus === 'ok' ? (float) ($row->total_hpp ?? 0) : 0.0;

            return [
                'order_number' => (string) $row->order_number,
                'order_created_at' => $row->order_created_at,
                'business_status' => (string) ($row->business_status ?? ''),
                'buyer_username' => $row->buyer_username,
                'order_product_name' => $row->order_product_name,
                'variation_name' => $row->variation_name,
                'net_quantity' => (float) $row->net_quantity,
                'discounted_price' => (float) round($row->discounted_price ?? 0, 2),
                'order_subtotal' => (float) $row->subtotal,
                'admin' => (float) $row->admin,
                'shipping' => (float) $row->shipping,
                'promo' => (float) $row->promo,
                'processing' => (float) $row->processing,
                'tax' => (float) $row->tax,
                'total_fee' => $totalFee,
                'penghasilan' => $penghasilan,
                'hpp' => $hpp,
                'hpp_status' => $hppStatus,
                'laba' => $penghasilan - $hpp,
            ];
        })->all();
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
        $netSales = $this->netSalesExpression('rows');
        $profit = "COALESCE(rows.total_income, {$sales}) + COALESCE(rows.platform_fee, 0) + COALESCE(rows.order_processing_fee, 0) + COALESCE(rows.free_shipping_xtra_fee, 0) + COALESCE(rows.promo_xtra_service_fee, 0) + COALESCE(rows.pph22, 0)";
        $aggregate = DB::query()
            ->fromSub($orders, 'rows')
            ->selectRaw("
                COALESCE(SUM({$sales}), 0) AS gross_sales,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN {$netSales} ELSE 0 END), 0) AS net_sales,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN COALESCE(rows.platform_fee, 0) + COALESCE(rows.free_shipping_xtra_fee, 0) + COALESCE(rows.promo_xtra_service_fee, 0) + COALESCE(rows.order_processing_fee, 0) ELSE 0 END), 0) AS total_fee,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} THEN COALESCE(rows.pph22, 0) ELSE 0 END), 0) AS total_tax,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} AND rows.cost_status = 'ok' THEN rows.total_hpp ELSE 0 END), 0) AS total_hpp,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} AND rows.cost_status = 'ok' THEN 1 ELSE 0 END), 0) AS hpp_ok_count,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} AND rows.cost_status = 'mapping_missing' THEN 1 ELSE 0 END), 0) AS hpp_mapping_missing_count,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} AND rows.cost_status = 'mapping_ambiguous' THEN 1 ELSE 0 END), 0) AS hpp_mapping_ambiguous_count,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} AND rows.cost_status = 'hpp_missing' THEN 1 ELSE 0 END), 0) AS hpp_hpp_missing_count,
                COALESCE(SUM(CASE WHEN NOT {$isCancelled} AND {$hasTracking} AND rows.cost_status IS NULL THEN 1 ELSE 0 END), 0) AS hpp_no_allocation_count,
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
        $totalHpp = (float) $aggregate->total_hpp;
        $grossProfit = $netSales - $totalFee - $totalTax;
        $netProfit = $grossProfit - $totalHpp;
        $netMargin = $netSales == 0.0 ? 0.0 : $netProfit / $netSales * 100;

        return [
            'gross_sales' => (float) $aggregate->gross_sales,
            'net_sales' => (float) $aggregate->net_sales,
            'total_fee' => $totalFee,
            'total_tax' => $totalTax,
            'total_hpp' => $totalHpp,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'net_margin' => $netMargin,
            'hpp_ok_count' => (int) $aggregate->hpp_ok_count,
            'hpp_mapping_missing_count' => (int) $aggregate->hpp_mapping_missing_count,
            'hpp_mapping_ambiguous_count' => (int) $aggregate->hpp_mapping_ambiguous_count,
            'hpp_hpp_missing_count' => (int) $aggregate->hpp_hpp_missing_count,
            'hpp_no_allocation_count' => (int) $aggregate->hpp_no_allocation_count,
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

    private function netSalesExpression(string $tableAlias): string
    {
        return "COALESCE({$tableAlias}.discounted_price, 0) * CASE WHEN COALESCE({$tableAlias}.quantity, 0) - COALESCE({$tableAlias}.returned_quantity, 0) > 0 THEN COALESCE({$tableAlias}.quantity, 0) - COALESCE({$tableAlias}.returned_quantity, 0) ELSE 0 END";
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
                line_identity,
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
                'line_identity',
                'unit_price',
                'product_price',
                'quantity'
            );

        $incomeFallback = DB::table('marketplace_income')
            ->whereNotNull('total_income')
            ->where('total_income', '<>', 0)
            ->whereNull('variation_key')
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
                SUM(quantity) AS income_quantity,
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
                SUM(discounted_price * '.$netQuantitySql.') AS order_amount,
                SUM(quantity) AS order_quantity,
                MIN(discounted_price * '.$netQuantitySql.') AS min_net_price,
                MAX(discounted_price * '.$netQuantitySql.') AS max_net_price
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
            'orders.buyer_username',
        ];
        $refundAmount = 'COALESCE(NULLIF(income_exact.refund_to_buyer, 0), income_refund.refund_to_buyer)';
        $refundEvidence = "({$refundAmount} < 0)";
        $exactMatch = 'income_exact.candidate_count = 1';
        $groupedFeeCondition = 'income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1))';
        $groupedMatch = "({$groupedFeeCondition})";
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
                    ->on('income_exact.line_identity', '=', 'orders.line_identity')
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
            ->leftJoin('order_cost_allocations as cost_alloc', function ($join): void {
                $join->on('cost_alloc.user_id', '=', 'orders.user_id')
                    ->on('cost_alloc.order_line_identity', '=', 'orders.line_identity');
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
                DB::raw("CASE WHEN {$refundEvidence} THEN COALESCE(income_exact.total_income, income_refund.total_income, 0) WHEN {$exactMatch} THEN income_exact.total_income WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.total_income_sum / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.total_income_sum END AS total_income"),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.order_processing_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.processing_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.processing_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN '.(string) $defaultOrderProcessingFee.' ELSE 0 END AS order_processing_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.platform_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.platform_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.platform_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN (COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) * COALESCE(settled_sku_fee.avg_platform_fee_rate, 0) ELSE 0 END AS platform_fee'),
                DB::raw("CASE WHEN {$refundEvidence} THEN {$refundAmount} WHEN {$exactMatch} THEN income_exact.refund_to_buyer WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.refund_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.refund_total END AS refund_to_buyer"),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.free_shipping_xtra_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.shipping_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.shipping_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN (COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) * COALESCE(settled_sku_fee.avg_free_shipping_xtra_fee_rate, 0) ELSE 0 END AS free_shipping_xtra_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.promo_xtra_service_fee WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.promo_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.promo_total WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NULL AND settled_sku_fee.user_id IS NOT NULL THEN (COALESCE(orders.discounted_price, 0) * CASE WHEN orders.quantity - COALESCE(orders.returned_quantity, 0) > 0 THEN orders.quantity - COALESCE(orders.returned_quantity, 0) ELSE 0 END) * COALESCE(settled_sku_fee.avg_promo_xtra_service_fee_rate, 0) ELSE 0 END AS promo_xtra_service_fee'),
                DB::raw('CASE WHEN income_exact.candidate_count = 1 THEN income_exact.pph22 WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count IS NOT NULL AND ((income_fallback.candidate_count = order_group.order_line_count AND income_fallback.income_amount = order_group.order_amount) OR (income_fallback.candidate_count = 1 AND income_fallback.income_amount = order_group.min_net_price AND order_group.min_net_price = order_group.max_net_price AND order_group.order_line_count > 1)) THEN 1.0 * income_fallback.tax_total / order_group.order_line_count WHEN income_exact.candidate_count IS NULL AND income_fallback.candidate_count = 1 THEN income_fallback.tax_total END AS pph22'),
                DB::raw("{$businessStatus} AS business_status"),
                DB::raw("{$matchMethod} AS match_method"),
                DB::raw("{$matchConfidence} AS match_confidence"),
                DB::raw("{$refundAmount} AS refund_amount"),
                DB::raw("CASE WHEN {$refundEvidence} THEN CASE WHEN ABS({$refundAmount}) >= COALESCE(orders.discounted_price, 0) * COALESCE(orders.quantity, 0) THEN 'Full' ELSE 'Partial' END END AS refund_type"),
                DB::raw("{$settlementStatus} AS settlement_status"),
                'cost_alloc.total_hpp',
                'cost_alloc.cost_status',
                'cost_alloc.effective_hpp_record_id',
                'cost_alloc.hpp_per_base_unit AS cost_hpp_per_base_unit',
                'cost_alloc.quantity_base_unit AS cost_quantity_base_unit',
                'cost_alloc.master_product_id AS cost_master_product_id',
                'cost_alloc.master_unit_id AS cost_master_unit_id',
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
        $costStatus = trim((string) ($row->cost_status ?? ''));
        $hppStatus = $costStatus !== '' ? $costStatus : 'no_allocation';
        $hpp = $hppStatus === 'ok' ? (float) ($row->total_hpp ?? 0) : 0.0;

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
        $row->hpp_status = $hppStatus;
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
