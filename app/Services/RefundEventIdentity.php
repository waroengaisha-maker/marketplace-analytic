<?php

namespace App\Services;

final class RefundEventIdentity
{
    public static function make(string $orderNumber, ?string $applicationNumber): ?string
    {
        $applicationNumber = trim((string) $applicationNumber);
        if ($applicationNumber === '') {
            return null;
        }

        return hash('sha256', implode('|', [
            self::normalize($orderNumber),
            self::normalize($applicationNumber),
        ]));
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
