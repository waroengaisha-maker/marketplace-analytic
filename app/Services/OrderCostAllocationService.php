<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\MasterProductHpp;
use App\Models\MasterProductUnit;
use App\Models\OrderCostAllocation;
use App\Models\ShopeeProductMapping;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderCostAllocationService
{
    public function __construct(
        protected ShopeeProductMappingService $mappingService,
        protected HppEffectiveDateResolver $effectiveDateResolver,
    ) {}

    /** @return array{status: string, match_method: string, mapping: ?ShopeeProductMapping, candidates?: array} */
    public function allocateForOrderLine(int $userId, string $orderLineIdentity, CarbonInterface $transactionAt, array $shopeeIdentity, ?int $fulfilledQuantity): OrderCostAllocation
    {
        $resolved = $this->mappingService->resolve($userId, $shopeeIdentity);

        if ($resolved['status'] === 'ambiguous') {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, null, null, null, 'mapping_ambiguous');
        }

        if ($resolved['status'] !== 'matched' || ($resolved['mapping'] ?? null) === null) {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, null, null, null, 'mapping_missing');
        }

        $mapping = $resolved['mapping'];

        $product = MasterProduct::query()->forUser($userId)->find($mapping->master_product_id);
        if ($product === null) {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, null, null, null, 'mapping_missing');
        }

        $unit = $mapping->master_unit_id !== null
            ? $product->units()->find($mapping->master_unit_id)
            : $product->baseUnit;

        if ($unit === null) {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, null, null, null, 'mapping_missing');
        }

        $hppRecord = $this->effectiveDateResolver->resolve($userId, $product->id, $unit->id, $transactionAt);
        if ($hppRecord === null) {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, $product, $unit, null, 'hpp_missing');
        }

        // Automatic matches are suggestions only. Preserve the resolved master/HPP
        // provenance, but never turn a suggestion into a confirmed allocation.
        if ($mapping->match_method !== 'manual') {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, $product, $unit, $hppRecord, 'mapping_unconfirmed', null, null);
        }

        return $this->allocate($userId, $orderLineIdentity, $product->id, $unit->id, $hppRecord->id, $fulfilledQuantity);
    }

    public function allocate(int $userId, string $orderLineIdentity, int $productId, int $unitId, int $effectiveHppRecordId, ?int $fulfilledQuantity): OrderCostAllocation
    {
        $product = MasterProduct::query()->forUser($userId)->findOrFail($productId);
        $unit = $product->units()->findOrFail($unitId);
        $hppRecord = MasterProductHpp::query()->find($effectiveHppRecordId);

        if ($hppRecord === null) {
            return $this->persistMissingCostAllocation($userId, $orderLineIdentity, $product, $unit, null, 'hpp_missing');
        }

        if ($hppRecord->master_product_id !== $product->id || $hppRecord->master_unit_id !== $unit->id) {
            throw new InvalidArgumentException('HPP record does not belong to the selected product and unit.');
        }

        if ($unit->conversion_to_base <= 0) {
            throw new InvalidArgumentException('Unit conversion must be greater than zero.');
        }

        if ($fulfilledQuantity === null) {
            return $this->persistMissingCostAllocation(
                $userId,
                $orderLineIdentity,
                $product,
                $unit,
                $hppRecord,
                'quantity_unavailable',
                null,
                null,
            );
        }

        if ($fulfilledQuantity < 0) {
            throw new InvalidArgumentException('Fulfilled quantity cannot be negative.');
        }

        $quantityBaseUnit = $fulfilledQuantity * $unit->conversion_to_base;
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

    protected function persistMissingCostAllocation(int $userId, string $orderLineIdentity, ?MasterProduct $product, ?MasterProductUnit $unit, ?MasterProductHpp $hppRecord, string $costStatus, ?float $quantityBaseUnit = 0, ?float $totalHpp = 0): OrderCostAllocation
    {
        return DB::transaction(function () use ($userId, $orderLineIdentity, $product, $unit, $hppRecord, $costStatus, $quantityBaseUnit, $totalHpp): OrderCostAllocation {
            return OrderCostAllocation::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'order_line_identity' => $orderLineIdentity,
                ],
                [
                    'master_product_id' => $product?->id,
                    'master_unit_id' => $unit?->id,
                    'effective_hpp_record_id' => $hppRecord?->id,
                    'hpp_per_base_unit' => 0,
                    'quantity_base_unit' => $quantityBaseUnit,
                    'total_hpp' => $totalHpp,
                    'cost_status' => $costStatus,
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
