<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\MasterProductHpp;
use App\Models\MasterProductUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MasterProductCatalogService
{
    public function registerTemplateItem(int $userId, array $payload): MasterProduct
    {
        $templateItemCode = $this->normalizeCode($payload['template_item_code'] ?? $payload['KodeItem'] ?? null);
        if ($templateItemCode === '') {
            throw new InvalidArgumentException('Template item code is required.');
        }

        if (MasterProduct::query()->where('user_id', $userId)->where('template_item_code', $templateItemCode)->exists()) {
            throw new InvalidArgumentException('Duplicate template item code for the same user.');
        }

        $templateName = $this->normalizeText($payload['template_name'] ?? $payload['NamaItem'] ?? null);
        $normalizedName = $this->normalizeSearchText($templateName);

        return DB::transaction(function () use ($userId, $templateItemCode, $templateName, $normalizedName, $payload): MasterProduct {
            $product = MasterProduct::query()->create([
                'user_id' => $userId,
                'template_item_code' => $templateItemCode,
                'template_name' => $templateName,
                'normalized_name' => $normalizedName,
                'status' => 'active',
            ]);

            $unitsPayload = is_array($payload['units'] ?? null) ? $payload['units'] : [];
            if ($unitsPayload === []) {
                throw new InvalidArgumentException('At least one unit is required for a template item.');
            }

            $createdUnits = [];
            foreach ($unitsPayload as $unitPayload) {
                $createdUnits[] = $this->upsertUnit($product, $unitPayload);
            }

            $baseUnit = $this->selectBaseUnit($createdUnits);
            $product->update(['base_unit_id' => $baseUnit->id]);

            return $product->fresh(['units', 'baseUnit']);
        });
    }

    public function upsertUnit(MasterProduct $product, array $payload): MasterProductUnit
    {
        $unitCode = $this->normalizeCode($payload['unit_code'] ?? $payload['Satuan'] ?? null);
        if ($unitCode === '') {
            throw new InvalidArgumentException('Unit code is required.');
        }

        $conversionToBase = $this->decimal($payload['conversion_to_base'] ?? $payload['Konversi'] ?? 1.0);
        if ($conversionToBase <= 0) {
            throw new InvalidArgumentException('Conversion to base unit must be greater than zero.');
        }

        $unit = $product->units()->where('unit_code', $unitCode)->first();
        if ($unit === null) {
            $unit = $product->units()->create([
                'unit_code' => $unitCode,
                'unit_name' => $this->normalizeText($payload['unit_name'] ?? $payload['Satuan'] ?? null),
                'conversion_to_base' => $conversionToBase,
                'is_active' => true,
            ]);
        }

        $hppAmount = $this->decimal($payload['hpp_amount'] ?? $payload['HargaPokok'] ?? null);
        if ($hppAmount !== null && $hppAmount <= 0) {
            throw new InvalidArgumentException('HPP amount must be greater than zero.');
        }

        if ($hppAmount !== null) {
            $this->persistHppVersion($product, $unit, [
                'hpp_amount' => $hppAmount,
                'effective_from' => $payload['effective_from'] ?? now()->startOfDay(),
                'source_type' => $payload['source_type'] ?? 'template',
                'source_reference' => $payload['source_reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $payload['created_by'] ?? null,
            ]);
        }

        return $unit->fresh();
    }

    public function persistHppVersion(MasterProduct $product, MasterProductUnit $unit, array $payload): MasterProductHpp
    {
        $hppAmount = $this->decimal($payload['hpp_amount'] ?? null);
        if ($hppAmount === null || $hppAmount <= 0) {
            throw new InvalidArgumentException('HPP amount is required and must be greater than zero.');
        }

        $effectiveFrom = $payload['effective_from'] ?? now();
        $effectiveFromValue = is_string($effectiveFrom)
            ? new \DateTimeImmutable($effectiveFrom)
            : $effectiveFrom;

        $effectiveToValue = $payload['effective_to'] ?? null;
        if ($effectiveToValue !== null && $effectiveToValue !== '') {
            $effectiveToValue = is_string($effectiveToValue) ? new \DateTimeImmutable($effectiveToValue) : $effectiveToValue;
            if ($effectiveToValue <= $effectiveFromValue) {
                throw new InvalidArgumentException('effective_to must be greater than effective_from when provided.');
            }
        }

        $this->closeLeadingEffectivePeriod($product, $unit, $effectiveFromValue);

        $this->assertNoEffectiveOverlap($product, $unit, $effectiveFromValue, $effectiveToValue);

        $hppPerBaseUnit = $hppAmount / $unit->conversion_to_base;

        $existing = $product->hppRecords()
            ->where('master_unit_id', $unit->id)
            ->where('effective_from', $effectiveFromValue->format('Y-m-d H:i:s'))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $product->hppRecords()->create([
            'master_unit_id' => $unit->id,
            'hpp_amount' => $hppAmount,
            'hpp_per_base_unit' => $hppPerBaseUnit,
            'effective_from' => $effectiveFromValue,
            'effective_to' => $effectiveToValue,
            'source_type' => $payload['source_type'] ?? 'template',
            'source_reference' => $payload['source_reference'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $payload['created_by'] ?? null,
        ]);
    }

    public function resolveEffectiveHpp(int $userId, int $productId, int $unitId, CarbonInterface $at): ?MasterProductHpp
    {
        $product = MasterProduct::query()->forUser($userId)->findOrFail($productId);
        $unit = $product->units()->findOrFail($unitId);

        return $product->hppRecords()
            ->where('master_unit_id', $unit->id)
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    public function selectBaseUnit(array $units): MasterProductUnit
    {
        if ($units === []) {
            throw new InvalidArgumentException('A product must have at least one unit.');
        }

        usort($units, static fn (MasterProductUnit $left, MasterProductUnit $right): int =>
            $left->conversion_to_base <=> $right->conversion_to_base
        );

        $preferred = collect($units)->first(fn (MasterProductUnit $unit): bool => strtolower((string) $unit->unit_code) === 'pcs');

        return $preferred ?? $units[0];
    }

    protected function closeLeadingEffectivePeriod(MasterProduct $product, MasterProductUnit $unit, $effectiveFrom): void
    {
        $leading = $product->hppRecords()
            ->where('master_unit_id', $unit->id)
            ->where('effective_from', '<', $effectiveFrom)
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom);
            })
            ->orderBy('effective_from', 'desc')
            ->first();

        if ($leading !== null && $leading->effective_to === null) {
            $leading->effective_to = $effectiveFrom;
            $leading->save();
        }
    }

    protected function assertNoEffectiveOverlap(MasterProduct $product, MasterProductUnit $unit, $effectiveFrom, $effectiveTo): void
    {
        $query = $product->hppRecords()->where('master_unit_id', $unit->id);

        if ($effectiveTo !== null) {
            $query->where('effective_from', '<', $effectiveTo)
                ->where(function ($subQuery) use ($effectiveFrom): void {
                    $subQuery->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom);
                });
        } else {
            $query->where('effective_from', '<=', $effectiveFrom)
                ->where(function ($subQuery) use ($effectiveFrom): void {
                    $subQuery->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom);
                });
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('Overlapping HPP periods are not allowed for the same product and unit.');
        }
    }

    protected function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        return (float) $value;
    }

    protected function normalizeCode(mixed $value): string
    {
        $text = trim((string) $value);

        return strtoupper($text);
    }

    protected function normalizeText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function normalizeSearchText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($value);
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
