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
                ->when($productIdValue !== null, fn ($query) => $query->where('master_product_id', $productIdValue))
                ->first();

            if ($mapping !== null) {
                return [
                    'status' => 'matched',
                    'match_method' => 'exact',
                    'mapping' => $mapping,
                ];
            }
        }

        $productKey = $this->normalizeName((string) ($productName ?? ''));
        $variantKey = $this->normalizeName((string) ($variantName ?? ''));
        $identityKey = $variantKey === '' ? $productKey : $productKey.'|'.$variantKey;

        if ($identityKey !== '') {
            $candidates = $this->applyIdentityMatch((clone $query), $productName, $variantName)
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
                    'candidates' => $candidates->all(),
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

        if ($shopeeProductId !== null && $shopeeVariantId !== null) {
            $manualByIdentifier = (clone $manualQuery)
                ->where('shopee_product_id', $shopeeProductId)
                ->where('shopee_variant_id', $shopeeVariantId)
                ->when($productId !== null, fn ($query) => $query->where('master_product_id', $productId));

            $manual = $manualByIdentifier->get()->all();
            if ($manual !== []) {
                return $manual;
            }
        }

        $productKey = $this->normalizeName((string) ($productName ?? ''));
        if ($productKey === '') {
            return [];
        }

        return $this->applyIdentityMatch((clone $manualQuery), $productName, $variantName)
            ->when($productId !== null, fn ($query) => $query->where('master_product_id', $productId))
            ->get()
            ->all();
    }

    protected function applyIdentityMatch($query, ?string $productName, ?string $variantName)
    {
        $productKey = $this->normalizeName((string) ($productName ?? ''));
        $variantKey = $this->normalizeName((string) ($variantName ?? ''));

        if ($productKey === '') {
            return $query->whereRaw('1 = 0');
        }

        if ($variantKey === '') {
            return $query->where(function ($identityQuery) use ($productKey): void {
                $identityQuery
                    ->where('normalized_shopee_name', $productKey)
                    ->orWhere('normalized_shopee_name', 'like', $productKey.'|%');
            });
        }

        return $query->where('normalized_shopee_name', $productKey.'|'.$variantKey);
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

        if ($unitId === null || ! $product->units()->whereKey($unitId)->exists()) {
            throw new InvalidArgumentException('Selected unit does not belong to the chosen Master Product.');
        }

        $identityKey = $this->identityKey($shopeeProductName, $shopeeVariantName);

        return DB::transaction(function () use ($userId, $product, $unitId, $shopeeProductId, $shopeeVariantId, $shopeeProductName, $shopeeVariantName, $manualOverrideNote, $identityKey, $payload): ShopeeProductMapping {
            $mapping = $this->findMappingForOverride($userId, $shopeeProductId, $shopeeVariantId, $shopeeProductName, $shopeeVariantName);

            if ($mapping === null) {
                $mapping = new ShopeeProductMapping;
            }

            $mapping->fill([
                'user_id' => $userId,
                'master_product_id' => $product->id,
                'master_unit_id' => $unitId,
                'shopee_product_id' => $shopeeProductId,
                'shopee_variant_id' => $shopeeVariantId,
                'shopee_product_name' => $shopeeProductName,
                'shopee_variant_name' => $shopeeVariantName,
                'normalized_shopee_name' => $identityKey,
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

    protected function findMappingForOverride(int $userId, ?string $shopeeProductId, ?string $shopeeVariantId, ?string $shopeeProductName, ?string $shopeeVariantName): ?ShopeeProductMapping
    {
        $query = ShopeeProductMapping::query()->forUser($userId);

        if ($shopeeProductId !== null && $shopeeVariantId !== null) {
            $byIdentifier = (clone $query)
                ->where('shopee_product_id', $shopeeProductId)
                ->where('shopee_variant_id', $shopeeVariantId)
                ->first();

            if ($byIdentifier !== null) {
                return $byIdentifier;
            }
        }

        $productKey = $this->normalizeName((string) ($shopeeProductName ?? ''));
        if ($productKey === '') {
            return null;
        }

        return $this->applyIdentityMatch((clone $query), $shopeeProductName, $shopeeVariantName)->first();
    }

    protected function identityKey(?string $productName, ?string $variantName): string
    {
        $productKey = $this->normalizeName((string) ($productName ?? ''));
        $variantKey = $this->normalizeName((string) ($variantName ?? ''));

        $key = $variantKey === '' ? $productKey : $productKey.'|'.$variantKey;

        return mb_substr($key, 0, 255);
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
