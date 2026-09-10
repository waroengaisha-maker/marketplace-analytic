<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class IncomeReconciliationService
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function page(int $userId, ?string $from, ?string $to, array $parameters): LengthAwarePaginator
    {
        $query = $this->query($userId, $from, $to, $parameters);
        $perPage = min(max((int) ($parameters['per_page'] ?? 100), 25), 500);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function query(int $userId, ?string $from, ?string $to, array $parameters): Builder
    {
        $incomePrice = 'COALESCE(i.product_price, i.unit_price)';
        $orderNetQuantity = 'CASE WHEN o.quantity - COALESCE(o.returned_quantity, 0) > 0 THEN o.quantity - COALESCE(o.returned_quantity, 0) ELSE 0 END';
        $sameProduct = 'o.user_id = i.user_id AND o.order_number = i.order_number AND o.product_key = i.product_key';
        $sameVariation = "COALESCE(o.variation_key, '') = COALESCE(i.variation_key, '')";
        $sameName = "LOWER(COALESCE(o.product_name, '')) = LOWER(COALESCE(i.product_name, ''))";
        $samePrice = "(COALESCE(o.unit_price, o.discounted_price) = COALESCE(i.unit_price, i.product_price) OR o.discounted_price * {$orderNetQuantity} = {$incomePrice})";
        $exactCount = "(SELECT COUNT(*) FROM marketplace_orders o WHERE {$sameProduct} AND o.quantity = i.quantity AND {$sameVariation} AND {$sameName} AND {$samePrice})";
        $refundExactCount = "(SELECT COUNT(*) FROM marketplace_orders o WHERE {$sameProduct} AND o.quantity = i.quantity AND o.item_index = i.item_index AND {$sameName} AND {$samePrice})";
        $groupedCount = "(SELECT COUNT(*) FROM marketplace_orders o WHERE {$sameProduct} AND o.item_index = i.item_index)";
        $groupedAmount = "(SELECT COALESCE(SUM(o.discounted_price * {$orderNetQuantity}), 0) FROM marketplace_orders o WHERE {$sameProduct} AND o.item_index = i.item_index)";
        $productCount = "(SELECT COUNT(*) FROM marketplace_orders o WHERE {$sameProduct})";
        $groupedMatch = "COALESCE(i.refund_to_buyer, 0) >= 0 AND {$groupedCount} = 1 AND {$groupedAmount} = {$incomePrice}";
        $exactMatch = "({$exactCount} = 1 OR (i.refund_to_buyer < 0 AND {$refundExactCount} = 1))";
        $matchStatus = "CASE WHEN {$exactMatch} THEN 'Matched' WHEN {$groupedMatch} THEN 'Matched' WHEN {$productCount} > 0 THEN 'Ambiguous' ELSE 'Orphan' END";
        $matchMethod = "CASE WHEN {$exactMatch} THEN 'Exact' WHEN {$groupedMatch} THEN 'Grouped' ELSE 'None' END";
        $matchConfidence = "CASE WHEN {$exactMatch} THEN 'Exact' WHEN {$groupedMatch} THEN 'Grouped' WHEN {$productCount} > 0 THEN 'Ambiguous' ELSE 'None' END";
        $refundType = "CASE WHEN i.refund_to_buyer < 0 AND ABS(i.refund_to_buyer) >= {$incomePrice} THEN 'Full' WHEN i.refund_to_buyer < 0 THEN 'Partial' END";

        $query = DB::table('marketplace_income as i')
            ->where('i.user_id', $userId)
            ->when($from, fn (Builder $query) => $query->where('i.order_created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('i.order_created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()))
            ->select([
                'i.id',
                'i.order_number',
                'i.product_name',
                'i.product_key',
                'i.variation_key',
                'i.item_index',
                'i.product_price',
                'i.unit_price',
                'i.quantity',
                'i.total_income',
                'i.refund_to_buyer',
                DB::raw("{$matchStatus} AS income_match_status"),
                DB::raw("{$matchMethod} AS match_method"),
                DB::raw("{$matchConfidence} AS match_confidence"),
                DB::raw('i.refund_to_buyer AS refund_amount'),
                DB::raw("{$refundType} AS refund_type"),
            ]);

        $statuses = array_values(array_filter((array) ($parameters['statuses'] ?? [])));
        if ($statuses !== []) {
            $query->whereIn(DB::raw($matchStatus), $statuses);
        }

        if (($refundTypeFilter = $parameters['refund_type'] ?? null) !== null) {
            $query->whereRaw(match ($refundTypeFilter) {
                'Full' => "{$refundType} = 'Full'",
                'Partial' => "{$refundType} = 'Partial'",
                'None' => 'i.refund_to_buyer IS NULL OR i.refund_to_buyer >= 0',
                default => '1 = 0',
            });
        }

        $search = trim((string) ($parameters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $query) use ($like): void {
                $query->where('i.order_number', 'like', $like)
                    ->orWhere('i.product_name', 'like', $like)
                    ->orWhere('i.product_key', 'like', $like);
            });
        }

        $sortField = (string) ($parameters['sort_field'] ?? 'order_number');
        $sortOrder = strtolower((string) ($parameters['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortFields = [
            'order_number' => 'i.order_number',
            'product_name' => 'i.product_name',
            'total_income' => 'i.total_income',
            'refund_amount' => 'i.refund_to_buyer',
            'income_match_status' => DB::raw($matchStatus),
        ];

        return $query->orderBy($sortFields[$sortField] ?? $sortFields['order_number'], $sortOrder)
            ->orderBy('i.id');
    }
}
