<?php

namespace App\Services;

use App\Models\OrderCostAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderHppSyncService
{
    public function __construct(
        private readonly OrderCostAllocationService $allocation,
    ) {}

    /**
     * Re-runs HPP allocation for every existing order line of a user so the
     * reconciliation page reflects the current master mapping and HPP data.
     *
     * @return array{ok: bool, total: int, ok_count: int, mapping_missing: int, mapping_ambiguous: int, hpp_missing: int, mapping_unconfirmed: int, failed: int}
     */
    public function sync(int $userId): array
    {
        $counts = [
            'ok' => true,
            'total' => 0,
            'ok_count' => 0,
            'mapping_missing' => 0,
            'mapping_ambiguous' => 0,
            'hpp_missing' => 0,
            'quantity_unavailable' => 0,
            'mapping_unconfirmed' => 0,
            'failed' => 0,
        ];

        DB::table('marketplace_orders')
            ->where('user_id', $userId)
            ->select([
                'id',
                'line_identity',
                'parent_sku',
                'sku_reference',
                'product_name',
                'variation_name',
                'quantity',
                'returned_quantity',
                'fulfilled_quantity',
                'cancelled_quantity',
                'order_created_at',
                'payment_at',
                'shipped_at',
                'completed_at',
            ])
            ->chunkById(500, function (Collection $lines) use ($userId, &$counts): void {
                foreach ($lines as $line) {
                    $counts['total']++;

                    try {
                        $allocation = $this->allocation->allocateForOrderLine(
                            $userId,
                            (string) $line->line_identity,
                            $this->allocationDate($line),
                            [
                                'shopee_product_id' => $line->parent_sku,
                                'shopee_variant_id' => $line->sku_reference,
                                'shopee_product_name' => $line->product_name,
                                'shopee_variant_name' => $line->variation_name,
                            ],
                            $line->fulfilled_quantity === null ? null : (int) $line->fulfilled_quantity,
                        );

                        $counts[$this->statusKey($allocation)]++;
                    } catch (Throwable) {
                        $counts['failed']++;
                    }
                }
            }, 'id');

        return $counts;
    }

    private function allocationDate(object $line): CarbonImmutable
    {
        foreach (['order_created_at', 'payment_at', 'shipped_at', 'completed_at'] as $column) {
            if ($line->{$column} !== null) {
                return CarbonImmutable::parse($line->{$column});
            }
        }

        return CarbonImmutable::now();
    }

    private function statusKey(OrderCostAllocation $allocation): string
    {
        return match (true) {
            $allocation->cost_status === 'mapping_missing' => 'mapping_missing',
            $allocation->cost_status === 'mapping_ambiguous' => 'mapping_ambiguous',
            $allocation->cost_status === 'hpp_missing' => 'hpp_missing',
            $allocation->cost_status === 'quantity_unavailable' => 'quantity_unavailable',
            $allocation->cost_status === 'mapping_unconfirmed' => 'mapping_unconfirmed',
            $allocation->cost_status === 'ok' => 'ok_count',
            default => 'failed',
        };
    }
}
