<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\MasterProductHpp;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class HppEffectiveDateResolver
{
    public function resolve(int $userId, int $productId, int $unitId, CarbonInterface $at): ?MasterProductHpp
    {
        $product = MasterProduct::query()->forUser($userId)->find($productId);
        if ($product === null) {
            throw new InvalidArgumentException('Product not found for the current tenant.');
        }

        $unit = $product->units()->whereKey($unitId)->first();
        if ($unit === null) {
            throw new InvalidArgumentException('Unit not found for the selected product.');
        }

        return $product->hppRecords()
            ->where('master_unit_id', $unit->id)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }
}
