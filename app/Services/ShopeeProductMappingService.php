<?php

namespace App\Services;

use App\Models\MasterProduct;
use App\Models\ShopeeProductMapping;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShopeeProductMappingService
{
    public function resolve(int $userId, array $payload): array
    {
        $productId = $payload['master_product_id'] ?? null;
        if ($productId !== null) {
            $product = MasterProduct::query()->forUser($userId)->find($productId);
            if ($product === null) {
                throw new InvalidArgumentException('Selected product does not belong to the current tenant.');
            }
        }

        $productIdValue = $productId ?? null;
        $shopeeProductId = $this->normalizeNullableString($payload['shopee_product_id'] ?? null);
        $shopeeVariantId = $this->normalizeNullableString($payload['shopee_variant_id'] ?? null);
        $productName = $this->normalizeNullableString($payload['shopee_product_name'] ?? null);
        $variantName = $this->normalizeNullableString($payload['shopee_variant_name'] ?? null);

        $query = ShopeeProductMapping::query()->forUser($userId)->active();
        $manualCandidates = $this->manualCandidates($query, $shopeeProductId, $shopeeVariantId, $productName, $variantName, $productIdValue);
        if ($manualCandidates !== []) {
            if (count($manualCandidates) === 1) {
                return [
                    'status' => 'matched',
                    'match_method' => 'manual',
                    'mapping' => $manualCandidates[0],
                ];
            }

            return [
                'status' => 'ambiguous',
                'match_method' => 'manual',
                'mapping' => null,
                'candidates' => $manualCandidates,
            ];
        }

        if ($shopeeProductId !== null && $shopeeVariantId !== null) {
            $mapping = (clone $query)
                ->where('shopee_product_id', $shopeeProductId)
                ->where('shopee_variant_id', $shopeeVariantId)
                ->first();

            if ($mapping !== null) {
                return [
                    'status' => 'matched',
                    'match_method' => 'exact',
                    'mapping' => $mapping,
                ];
            }
        }

        $normalizedName = $this->normalizeName($productName ?? $variantName ?? '');
        if ($normalizedName !== '') {
            $candidates = (clone $query)
                ->where('normalized_shopee_name', $normalizedName)
                ->when($productIdValue !== null, fn ($query) => $query->where('master_product_id', $productIdValue))
                ->get();

            if ($candidates->count() === 1) {
                return [
                    'status' => 'matched',
                    'match_method' => 'normalized',
                    'mapping' => $candidates->first(),
                ];
            }

            if ($candidates->count() > 1) {
                return [
                    'status' => 'ambiguous',
                    'match_method' => 'normalized',
                    'mapping' => null,
                    'candidates' => $candidates,
                ];
            }
        }

        return [
            'status' => 'mapping_missing',
            'match_method' => 'missing',
            'mapping' => null,
        ];
    }

    protected function manualCandidates($query, ?string $shopeeProductId, ?string $shopeeVariantId, ?string $productName, ?string $variantName, ?int $productId): array
    {
        $manualQuery = (clone $query)->where('match_method', 'manual');
        $manualByIdentifier = (clone $manualQuery);

        if ($shopeeProductId !== null && $shopeeVariantId !== null) {
            $manualByIdentifier = $manualByIdentifier
                ->where('shopee_product_id', $shopeeProductId)
                ->where('shopee_variant_id', $shopeeVariantId);

            $manual = $manualByIdentifier->get()->all();
            if ($manual !== []) {
                return $manual;
            }
        }

        $normalizedName = $this->normalizeName($productName ?? $variantName ?? '');
        if ($normalizedName === '') {
            return [];
        }

        $manualByName = (clone $manualQuery)
            ->where('normalized_shopee_name', $normalizedName)
            ->when($productId !== null, fn ($query) => $query->where('master_product_id', $productId));

        return $manualByName->get()->all();
    }

    public function createManualMapping(int $userId, MasterProduct $product, ?int $unitId, array $payload): ShopeeProductMapping
    {
        $shopeeProductId = $this->normalizeNullableString($payload['shopee_product_id'] ?? null);
        $shopeeVariantId = $this->normalizeNullableString($payload['shopee_variant_id'] ?? null);
        $shopeeProductName = $this->normalizeNullableString($payload['shopee_product_name'] ?? null);
        $shopeeVariantName = $this->normalizeNullableString($payload['shopee_variant_name'] ?? null);
        $manualOverrideNote = $this->normalizeNullableString($payload['manual_override_note'] ?? null);

        if ($shopeeProductId === null && $shopeeVariantId === null && $shopeeProductName === null && $shopeeVariantName === null) {
            throw new InvalidArgumentException('Manual mapping requires at least one Shopee identifier or name.');
        }

        return DB::transaction(function () use ($userId, $product, $unitId, $shopeeProductId, $shopeeVariantId, $shopeeProductName, $shopeeVariantName, $manualOverrideNote, $payload): ShopeeProductMapping {
            $mapping = ShopeeProductMapping::query()->firstOrNew([
                'user_id' => $userId,
                'master_product_id' => $product->id,
                'master_unit_id' => $unitId,
                'shopee_product_id' => $shopeeProductId,
                'shopee_variant_id' => $shopeeVariantId,
            ]);

            $mapping->fill([
                'user_id' => $userId,
                'master_product_id' => $product->id,
                'master_unit_id' => $unitId,
                'shopee_product_id' => $shopeeProductId,
                'shopee_variant_id' => $shopeeVariantId,
                'shopee_product_name' => $shopeeProductName,
                'shopee_variant_name' => $shopeeVariantName,
                'normalized_shopee_name' => $this->normalizeName($shopeeProductName ?? $shopeeVariantName ?? ''),
                'match_method' => 'manual',
                'match_confidence' => $payload['match_confidence'] ?? 1.00,
                'is_active' => true,
                'ambiguous' => false,
                'manual_override_by' => $payload['manual_override_by'] ?? null,
                'manual_override_note' => $manualOverrideNote,
            ]);

            $mapping->save();

            return $mapping->fresh();
        });
    }

    protected function normalizeName(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
