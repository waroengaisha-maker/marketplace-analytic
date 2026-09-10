<?php

namespace App\Services;

final class ReportLineIdentity
{
    public static function make(
        string $orderNumber,
        ?string $productKey,
        ?string $variationKey,
        ?float $unitPrice,
        ?int $quantity,
    ): string {
        return hash('sha256', implode('|', [
            self::normalize($orderNumber),
            self::normalize($productKey),
            self::normalize($variationKey),
            $unitPrice === null ? '' : number_format($unitPrice, 2, '.', ''),
            $quantity === null ? '' : (string) $quantity,
        ]));
    }

    private static function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
