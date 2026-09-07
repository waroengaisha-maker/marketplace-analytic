<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class OrderReportImporter
{
    public function import(string $path, int $userId): int
    {
        $rows = IOFactory::load($path)->getSheetByName('orders')->toArray(null, true, true, false);
        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_shift($rows));
        $payload = [];

        foreach ($rows as $row) {
            $data = $this->row($headers, $row);
            $orderNumber = $this->text($data['No. Pesanan'] ?? null);
            if ($orderNumber === null) {
                continue;
            }

            $variationName = $this->text($data['Nama Variasi'] ?? null);
            $quantity = $this->integer($data['Jumlah'] ?? null);
            $discountedPrice = $this->number($data['Harga Setelah Diskon'] ?? null);
            $unitPrice = $discountedPrice;
            $originalPrice = $this->number($data['Harga Awal'] ?? null);
            $returnedQuantity = $this->integer($data['Returned quantity'] ?? null) ?? 0;
            $itemKey = $this->lineKey(
                $orderNumber,
                $data['Nama Produk'] ?? null,
                $discountedPrice === null || $quantity === null ? null : $discountedPrice * max(0, $quantity - $returnedQuantity)
            );

            $payload[] = [
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'item_index' => $this->itemIndex($itemKey),
                'order_status' => $this->text($data['Status Pesanan'] ?? null),
                'cancellation_reason' => $this->text($data['Alasan Pembatalan'] ?? null),
                'return_status' => $this->text($data['Status Pembatalan/ Pengembalian'] ?? null),
                'tracking_number' => $this->text($data['No. Resi'] ?? null),
                'shipping_option' => $this->text($data['Opsi Pengiriman'] ?? null),
                'order_type' => $this->text($data['Tipe Pesanan'] ?? null),
                'payment_method' => $this->text($data['Metode Pembayaran'] ?? null),
                'parent_sku' => $this->text($data['SKU Induk'] ?? null),
                'product_name' => $this->text($data['Nama Produk'] ?? null),
                'product_key' => hash('sha256', mb_strtolower(trim((string) ($data['Nama Produk'] ?? '')))),
                'sku_reference' => $this->text($data['Nomor Referensi SKU'] ?? null),
                'variation_name' => $variationName,
                'variation_key' => $this->key($variationName),
                'original_price' => $originalPrice,
                'discounted_price' => $discountedPrice,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'returned_quantity' => $returnedQuantity,
                'order_subtotal' => $this->number($data['Subtotal Pesanan'] ?? null),
                'total_payment' => $this->number($data['Total Pembayaran'] ?? null),
                'buyer_shipping_paid' => $this->number($data['Ongkos Kirim Dibayar oleh Pembeli'] ?? null),
                'estimated_shipping_discount' => $this->number($data['Estimasi Potongan Biaya Pengiriman'] ?? null),
                'estimated_shipping_cost' => $this->number($data['Perkiraan Ongkos Kirim'] ?? null),
                'product_count' => $this->integer($data['Jumlah Produk di Pesan'] ?? null),
                'total_weight' => $this->number($data['Total Berat'] ?? null),
                'buyer_username' => $this->text($data['Username (Pembeli)'] ?? null),
                'recipient_name' => $this->text($data['Nama Penerima'] ?? null),
                'buyer_phone' => $this->text($data['No. Telepon'] ?? null),
                'shipping_address' => $this->text($data['Alamat Pengiriman'] ?? null),
                'city' => $this->text($data['Kota/Kabupaten'] ?? null),
                'province' => $this->text($data['Provinsi'] ?? null),
                'order_created_at' => $this->date($data['Waktu Pesanan Dibuat'] ?? null),
                'payment_at' => $this->date($data['Waktu Pembayaran Dilakukan'] ?? null),
                'shipped_at' => $this->date($data['Waktu Pengiriman Diatur'] ?? null),
                'completed_at' => $this->date($data['Waktu Pesanan Selesai'] ?? null),
                'raw_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $orderNumbers = array_values(array_unique(array_column($payload, 'order_number')));
        DB::table('marketplace_orders')->where('user_id', $userId)->whereIn('order_number', $orderNumbers)->delete();

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table('marketplace_orders')->upsert($chunk, ['user_id', 'order_number', 'product_key', 'variation_key', 'unit_price', 'quantity'], array_keys($chunk[0] ?? []));
        }

        return count($payload);
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
            if ($fractionDigits > 2) {
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
