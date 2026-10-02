<?php

namespace App\Services;

use App\Services\RefundEventIdentity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class IncomeReportImporter
{
    public function import(string $path, int $userId): int
    {
        $sheet = IOFactory::load($path)->getSheetByName('Penghasilan');
        $rows = $sheet->toArray(null, true, true, false);
        array_shift($rows);
        array_shift($rows);

        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_shift($rows));
        $payload = [];

        foreach ($rows as $row) {
            $data = $this->row($headers, $row);
            $orderNumber = $this->text($data['No. Pesanan'] ?? null);
            if ($orderNumber === null || strcasecmp(trim((string) ($data['Lihat berdasarkan'] ?? '')), 'Sku') !== 0) {
                continue;
            }

            $variationName = $this->text($data['Nama Variasi'] ?? null);
            $quantity = $this->integer($data['Jumlah'] ?? $data['Quantity'] ?? null) ?? 1;
            $productPrice = $this->number($data['Harga Produk'] ?? null);
            $unitPrice = $this->number($data['Harga Satuan'] ?? $data['Harga Produk'] ?? $productPrice);
            $productKey = hash('sha256', mb_strtolower(trim((string) ($data['Nama Produk'] ?? ''))));
            $variationKey = $this->key($variationName);
            $itemKey = $this->lineKey($orderNumber, $data['Nama Produk'] ?? null, $productPrice);

            $payload[] = [
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'item_index' => $this->itemIndex($itemKey),
                'line_identity' => ReportLineIdentity::make($orderNumber, $productKey, $variationKey, $unitPrice, $quantity),
                'refund_event_identity' => RefundEventIdentity::make($orderNumber, $this->text($data['No. Pengajuan'] ?? null)),
                'row_type' => $this->text($data['Lihat berdasarkan'] ?? null),
                'source_row' => $this->integer($data['No.'] ?? null),
                'application_number' => $this->text($data['No. Pengajuan'] ?? null),
                'product_id' => $this->text($data['ID Produk'] ?? null),
                'product_name' => $this->text($data['Nama Produk'] ?? null),
                'product_key' => $productKey,
                'variation_key' => $variationKey,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'order_created_at' => $this->date($data['Waktu Pesanan Dibuat'] ?? null),
                'fund_released_at' => $this->date($data['Tanggal Dana Dilepaskan'] ?? null),
                'release_method' => $this->text($data['Metode Pelepasan Dana'] ?? null),
                'order_type' => $this->text($data['Tipe Pesanan'] ?? null),
                // Keep settlement income and revenue as independent source fields.
                'total_income' => $this->number($data['Total Penghasilan'] ?? null),
                'total_revenue' => $this->number($data['Total Pendapatan'] ?? null),
                'product_price' => $productPrice,
                'buyer_shipping_paid' => $this->number($data['Ongkir Dibayar Pembeli'] ?? null),
                'platform_fee' => $this->number($data['Biaya Administrasi'] ?? null),
                'order_processing_fee' => $this->number($data['Biaya Proses Pesanan'] ?? null),
                'free_shipping_xtra_fee' => $this->sumNumbers($data, ['Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori D)', 'Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori E)', 'Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori G)', 'Biaya Gratis Ongkir XTRA - Ukuran Khusus (Kategori E)']),
                'shipping_fee' => $this->number($data['Subtotal Ongkos Kirim'] ?? null),
                'service_fee' => $this->number($data['Biaya Layanan'] ?? null),
                'promo_xtra_service_fee' => $this->number($data['Biaya Layanan Promo XTRA'] ?? null),
                'promotion_fee' => $this->number($data['Biaya Promosi'] ?? null),
                'pph22' => $this->number($data['PPh 22'] ?? null),
                'other_fee' => $this->number($data['Biaya Lainnya'] ?? null),
                'refund_to_buyer' => $this->number($data['Jumlah Pengembalian Dana ke Pembeli'] ?? null),
                'buyer_username' => $this->text($data['Username (Pembeli)'] ?? null),
                'buyer_paid_amount' => $this->number($data['Jumlah Dibayar Pembeli'] ?? null),
                'buyer_payment_method' => $this->text($data['Metode pembayaran pembeli'] ?? null),
                'shipping_provider' => $this->text($data['Nama Kurir'] ?? null),
                'voucher_code' => $this->text($data['Kode Voucher'] ?? null),
                'raw_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $payload = $this->uniqueRows($payload);

        return $this->persist($payload, $userId);
    }

    /**
     * Persists pre-built income rows with the same replace-per-order-number
     * semantics the Excel import uses. Reused by the Shopee API promotion path.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function persist(array $rows, int $userId): int
    {
        $rows = $this->uniqueRows($rows);
        return $this->persistRows($rows, $userId);
    }

    /**
     * API promotion is stricter than an Excel re-import: duplicate identities
     * in a staged API snapshot are ambiguous data and must abort the whole
     * promotion instead of being silently collapsed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function persistForPromotion(array $rows, int $userId): int
    {
        $this->assertNoDuplicateIdentities($rows, $userId);

        return $this->persistRows($rows, $userId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function persistRows(array $rows, int $userId): int
    {
        return DB::transaction(function () use ($rows, $userId): int {
            $orderNumbers = array_values(array_unique(array_column($rows, 'order_number')));

            DB::table('marketplace_income')
                ->where('user_id', $userId)
                ->whereIn('order_number', $orderNumbers)
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('marketplace_income')->insert($chunk);
            }

            return count($rows);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function assertNoDuplicateIdentities(array $rows, int $userId): void
    {
        $seen = [];

        foreach ($rows as $row) {
            $identity = (string) ($row['line_identity'] ?? '');
            if ($identity === '') {
                continue;
            }

            $eventIdentity = $row['refund_event_identity'] ?? null;
            if ($eventIdentity === null) {
                continue;
            }

            $key = $userId.'|'.$identity.'|'.$eventIdentity;
            if (isset($seen[$key])) {
                throw new \RuntimeException('Duplicate Shopee income identity detected during promotion.');
            }

            $seen[$key] = true;
        }
    }

    /**
     * Removes duplicate rows that share the same user + line identity, keeping
     * the first occurrence. Guards against re-uploads colliding with the
     * `income_user_line_identity_unique` key when a file lists a line twice.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function uniqueRows(array $rows): array
    {
        $seen = [];

        return array_values(array_filter($rows, static function (array $row) use (&$seen): bool {
            $eventIdentity = $row['refund_event_identity'] ?? null;
            if ($eventIdentity !== null) {
                $key = $row['user_id'].'|'.$row['line_identity'].'|'.$eventIdentity;
            } else {
                // Rows without a source event/application number must remain
                // distinct. Only collapse an actual duplicate source row.
                $sourceRow = $row['source_row'] ?? null;
                $key = $row['user_id'].'|'.$row['line_identity'].'|source:'
                    .($sourceRow === null ? 'raw:'.hash('sha256', (string) ($row['raw_data'] ?? '')) : 'row:'.$sourceRow);
            }

            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));
    }

    private function row(array $headers, array $values): array
    {
        return array_combine($headers, array_pad($values, count($headers), null)) ?: [];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function key(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', mb_strtolower(trim($value)));
    }

    private function lineKey(string $orderNumber, mixed $productName, ?float $lineAmount): string
    {
        return $orderNumber.'|'.mb_strtolower(trim((string) $productName)).'|'.($lineAmount === null ? '' : number_format($lineAmount, 2, '.', ''));
    }

    private function itemIndex(string $lineKey): int
    {
        return (int) sprintf('%u', crc32($lineKey));
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        $negative = false;
        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $negative = true;
            $value = substr($value, 1, -1);
        }

        $value = preg_replace('/[^\d,\.\-+]/u', '', $value);
        if ($value === null || $value === '' || $value === '-' || $value === '+' || $value === '.' || $value === ',') {
            return null;
        }

        $value = str_replace([' ', "\u{00A0}"], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $decimalSeparator = strrpos($value, ',') > strrpos($value, '.') ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif (str_contains($value, ',')) {
            $lastComma = strrpos($value, ',');
            $fractionDigits = $lastComma === false ? 0 : strlen(substr($value, $lastComma + 1));
            if ($fractionDigits <= 2) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, '.')) {
            $lastDot = strrpos($value, '.');
            $fractionDigits = $lastDot === false ? 0 : strlen(substr($value, $lastDot + 1));

            // PhpSpreadsheet may expose an Excel decimal as a long binary
            // floating-point artifact (for example 678.89999999999998).
            // Treat long fractional tails as decimal values instead of
            // misclassifying the dot as a thousands separator.
            if ($fractionDigits > 6) {
                $value = (string) round((float) $value, 2);
            } elseif ($fractionDigits > 2) {
                $value = str_replace('.', '', $value);
            }
        }

        $number = (float) $value;

        return $negative ? -$number : $number;
    }

    private function integer(mixed $value): ?int
    {
        $value = $this->number($value);

        return $value === null ? null : (int) $value;
    }

    private function sumNumbers(array $data, array $keys): ?float
    {
        $values = array_map(fn (string $key): ?float => $this->number($data[$key] ?? null), $keys);
        $values = array_filter($values, fn (?float $value): bool => $value !== null);

        return $values === [] ? null : array_sum($values);
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
