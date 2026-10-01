<?php

namespace App\Services;

final class CanonicalFinancialProjectionService
{
    /**
     * Single financial projection boundary used by migrated consumers.
     * Legacy response fields remain adapters until semantic validation is complete.
     */
    public function projectLine(object|array $sourceFields, object|array|null $allocation = null): CanonicalFinancialProjection
    {
        $source = is_object($sourceFields) ? get_object_vars($sourceFields) : $sourceFields;
        $allocationData = is_object($allocation) ? get_object_vars($allocation) : ($allocation ?? []);
        $quantity = $this->number($source['quantity'] ?? null);
        $returned = $this->number($source['returned_quantity'] ?? null);
        $price = $this->number($source['discounted_price'] ?? null);
        $platformFee = $this->number($source['platform_fee'] ?? null);
        $shippingFee = $this->number($source['free_shipping_xtra_fee'] ?? null);
        $promoFee = $this->number($source['promo_xtra_service_fee'] ?? null);
        $processingFee = $this->number($source['order_processing_fee'] ?? null);
        $tax = $this->number($source['pph22'] ?? null);
        $refund = ($source['refund_amount'] ?? null) === null ? 0.0 : $this->number($source['refund_amount']);
        $subtotal = ($source['order_subtotal'] ?? null) !== null ? $this->number($source['order_subtotal']) : ($price !== null && $quantity !== null ? $price * $quantity : null);
        $feeSubtotal = $platformFee === null || $shippingFee === null || $promoFee === null ? null : $platformFee + $shippingFee + $promoFee;
        $totalFee = $feeSubtotal === null || $processingFee === null ? null : $feeSubtotal + $processingFee;
        $penghasilan = $subtotal === null || $totalFee === null || $tax === null ? null : $subtotal + $refund + $totalFee + $tax;
        $costStatus = trim((string) ($allocationData['cost_status'] ?? $source['cost_status'] ?? ''));
        $feeProvenance = $this->feeProvenance($source, $feeSubtotal, $totalFee);
        $hppStatus = $costStatus !== '' ? $costStatus : 'no_allocation';
        $hppValue = $allocationData['total_hpp'] ?? $source['total_hpp'] ?? null;
        $hpp = $hppStatus === 'ok' && $hppValue !== null ? $this->number($hppValue) : null;
        $laba = $penghasilan === null || $hpp === null ? null : $penghasilan - $hpp;
        $legacyNet = $quantity === null ? null : max($quantity - ($returned ?? 0), 0);
        $status = $this->status($subtotal, $totalFee, $tax, $hpp, $hppStatus, $feeProvenance, $this->taxProvenance($source, $tax));
        return new CanonicalFinancialProjection(
            $source, $allocationData, $subtotal, $platformFee, $shippingFee, $promoFee,
            $feeSubtotal, $processingFee, $totalFee, $tax, $refund, $penghasilan, $hpp,
            $hppStatus, $laba, $legacyNet, $source['fulfilled_quantity'] ?? null,
            $source['cancelled_quantity'] ?? null, $status, [
                'revenue' => $subtotal === null ? 'unavailable' : 'source_order',
                'fees' => $feeProvenance,
                'tax' => $this->taxProvenance($source, $tax),
                'refund' => ($source['refund_amount'] ?? null) === null ? 'default_zero_when_no_refund_evidence' : 'source_income',
                'hpp' => $hpp === null ? $hppStatus : 'confirmed_allocation',
                'fulfillment' => ($source['fulfilled_quantity'] ?? null) === null ? 'unknown' : 'source_order',
                'cancellation' => ($source['cancelled_quantity'] ?? null) === null ? 'unknown' : 'source_order',
            ]
        );
    }

    public function aggregateProjection(iterable $lines, ?string $grouping = null): array
    {
        $lines = is_array($lines) ? $lines : iterator_to_array($lines, false);
        if ($lines === []) return ['status' => 'unavailable'];
        $sum = function (string $field) use ($lines): ?float {
            $total = 0.0;
            foreach ($lines as $line) {
                if ($line->{$field} === null) return null;
                $total += $line->{$field};
            }
            return $total;
        };
        $values = ['subtotal' => $sum('orderSubtotal'), 'total_fee' => $sum('totalFee'), 'tax' => $sum('tax'), 'refund_amount' => $sum('refundAmount'), 'penghasilan' => $sum('penghasilan'), 'hpp' => $sum('hpp'), 'laba' => $sum('laba')];
        $status = 'confirmed';
        foreach ($lines as $line) if ($line->status === 'unavailable') $status = 'unavailable';
        if ($status !== 'unavailable') foreach ($lines as $line) if ($line->status === 'provisional') $status = 'provisional';
        return ['grouping' => $grouping, 'status' => $status, ...$values];
    }

    public function compareSettlement(CanonicalFinancialProjection|array $projection, ?float $totalIncome): array
    {
        $value = $projection instanceof CanonicalFinancialProjection ? $projection->penghasilan : ($projection['penghasilan'] ?? null);
        return ['status' => $value === null || $totalIncome === null ? 'unavailable' : 'comparable', 'projection_income' => $value, 'settlement_income' => $totalIncome, 'difference' => $value === null || $totalIncome === null ? null : $value - $totalIncome];
    }

    private function feeProvenance(array $source, ?float $feeSubtotal, ?float $totalFee): string
    {
        if ($feeSubtotal === null || $totalFee === null) {
            return 'unavailable';
        }

        $matchMethod = trim((string) ($source['match_method'] ?? ''));
        $settlementStatus = trim((string) ($source['settlement_status'] ?? ''));

        if ($matchMethod === 'Estimated' || $settlementStatus === 'Estimated') {
            return 'estimated';
        }

        return 'source_income';
    }

    private function taxProvenance(array $source, ?float $tax): string
    {
        if ($tax === null) {
            return 'unavailable';
        }

        $matchMethod = trim((string) ($source['match_method'] ?? ''));
        $settlementStatus = trim((string) ($source['settlement_status'] ?? ''));

        return $matchMethod === 'Estimated' || $settlementStatus === 'Estimated'
            ? 'estimated'
            : 'source_income';
    }

    private function number(mixed $value): ?float { return $value === null ? null : (float) $value; }

    private function status(?float $subtotal, ?float $fee, ?float $tax, ?float $hpp, string $hppStatus, string $feeProvenance, string $taxProvenance): string
    {
        if ($subtotal === null || $fee === null || $tax === null) return 'unavailable';
        if ($feeProvenance === 'estimated' || $taxProvenance === 'estimated') return 'provisional';
        if ($hpp !== null && $hppStatus === 'ok') return 'confirmed';
        return 'provisional';
    }
}
