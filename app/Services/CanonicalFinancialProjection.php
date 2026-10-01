<?php

namespace App\Services;

final readonly class CanonicalFinancialProjection
{
    public function __construct(
        public array $source,
        public array $allocation,
        public ?float $orderSubtotal,
        public ?float $platformFee,
        public ?float $freeShippingFee,
        public ?float $promoFee,
        public ?float $feeSubtotal,
        public ?float $processingFee,
        public ?float $totalFee,
        public ?float $tax,
        public ?float $refundAmount,
        public ?float $penghasilan,
        public ?float $hpp,
        public string $hppStatus,
        public ?float $laba,
        public ?float $legacyNetQuantity,
        public mixed $fulfilledQuantity,
        public mixed $cancelledQuantity,
        public string $status,
        public array $provenance,
    ) {}
}
