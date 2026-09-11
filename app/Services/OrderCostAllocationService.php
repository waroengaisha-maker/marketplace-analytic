<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\MasterProductHpp;
use App\Models\MasterProductUnit;
use App\Models\OrderCostAllocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderCostAllocationService
{
    public function allocate(int $userId, string $orderLineIdentity, int $productId, int $unitId, int $effectiveHppRecordId, int $quantity, int $returnedQuantity = 0): OrderCostAllocation
    {
        $product = MasterProduct::query()->forUser($userId)->findOrFail($productId);
        $unit = $product->units()->findOrFail($unitId);
        $hppRecord = MasterProductHpp::query()->find($effectiveHppRecordId);

        if ($hppRecord === null) {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, $product, $unit, null);
        }

        if ($hppRecord->master_product_id !== $product->id || $hppRecord->master_unit_id !== $unit->id) {
            throw new InvalidArgumentException('HPP record does not belong to the selected product and unit.');
        }

        if ($unit->conversion_to_base <= 0) {
            throw new InvalidArgumentException('Unit conversion must be greater than zero.');
        }

        $quantityBaseUnit = (max(0, $quantity - $returnedQuantity)) * $unit->conversion_to_base;
        $totalHpp = $quantityBaseUnit * $hppRecord->hpp_per_base_unit;

        return DB::transaction(function () use ($userId, $orderLineIdentity, $product, $unit, $hppRecord, $quantityBaseUnit, $totalHpp): OrderCostAllocation {
            return OrderCostAllocation::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'order_line_identity' => $orderLineIdentity,
                ],
                [
                    'master_product_id' => $product->id,
                    'master_unit_id' => $unit->id,
                    'effective_hpp_record_id' => $hppRecord->id,
                    'hpp_per_base_unit' => $hppRecord->hpp_per_base_unit,
                    'quantity_base_unit' => $quantityBaseUnit,
                    'total_hpp' => $totalHpp,
                    'cost_status' => 'ok',
                ]
            );
        });
    }

    protected function persistMissingCostAllocation(int $userId, string $orderLineIdentity, MasterProduct $product, MasterProductUnit $unit, ?MasterProductHpp $hppRecord): OrderCostAllocation
    {
        return DB::transaction(function () use ($userId, $orderLineIdentity, $product, $unit, $hppRecord): OrderCostAllocation {
            return OrderCostAllocation::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'order_line_identity' => $orderLineIdentity,
                ],
                [
                    'master_product_id' => $product->id,
                    'master_unit_id' => $unit->id,
                    'effective_hpp_record_id' => $hppRecord?->id,
                    'hpp_per_base_unit' => 0,
                    'quantity_base_unit' => 0,
                    'total_hpp' => 0,
                    'cost_status' => 'hpp_missing',
                ]
            );
        });
    }

    public function ensureConsistentProductUnit(MasterProduct $product, MasterProductUnit $unit, MasterProductHpp $hpp): void
    {
        if ($hpp->master_product_id !== $product->id || $hpp->master_unit_id !== $unit->id) {
            throw new InvalidArgumentException('Master product, unit, and HPP record are inconsistent.');
        }
    }
}
