<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ReportImportService
{
    /** @return array{orders: int, income: int} */
    public function storeAndImport(Request $request): array
    {
        $paths = [];

        try {
            $paths['orders'] = $request->file('order_report')->store('reports/orders');
            $paths['income'] = $request->file('income_report')->store('reports/income');

            $result = DB::transaction(function () use ($request): array {
                return [
                    'orders' => $this->importOrders($request->file('order_report')->getRealPath()),
                    'income' => $this->importIncome($request->file('income_report')->getRealPath()),
                ];
            });

            Storage::delete(array_values($paths));

            return $result;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    public function importOrders(string $path, int $userId): int
    {
        $rows = IOFactory::load($path)->getSheetByName('orders')->toArray(null, true, true, false);
        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_shift($rows));
        $payload = [];
        $itemIndexes = [];

        foreach ($rows as $row) {
            $data = $this->row($headers, $row);
            $orderNumber = $this->text($data['No. Pesanan'] ?? null);
            if ($orderNumber === null) continue;

            $itemKey = $orderNumber.'|'.mb_strtolower(trim((string) ($data['Nama Produk'] ?? '')));
            $itemIndexes[$itemKey] = ($itemIndexes[$itemKey] ?? 0) + 1;
            $payload[] = [
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'item_index' => $itemIndexes[$itemKey],
                'order_status' => $this->text($data['Status Pesanan'] ?? null),
                'cancellation_reason' => $this->text($data['Alasan Pembatalan'] ?? null),
                'return_status' => $this->text($data['Status Pembatalan/ Pengembalian'] ?? null),
                'tracking_number' => $this->text($data['No. Resi'] ?? null),
                'shipping_option' => $this->text($data['Opsi Pengiriman'] ?? null),
                'order_type' => $this->text($data['Tipe Pesanan'] ?? null),
                'payment_method' => $this->text($data['Metode Pembayaran'] ?? null),
                'parent_sku' => $this->text($data['SKU Induk'] ?? null),
                'product_name' => $this->text($data['Nama Produk'] ?? null),
                'sku_reference' => $this->text($data['Nomor Referensi SKU'] ?? null),
                'variation_name' => $this->text($data['Nama Variasi'] ?? null),
                'original_price' => $this->number($data['Harga Awal'] ?? null),
                'discounted_price' => $this->number($data['Harga Setelah Diskon'] ?? null),
                'quantity' => $this->integer($data['Jumlah'] ?? null),
                'returned_quantity' => $this->integer($data['Returned quantity'] ?? null),
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
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table('marketplace_orders')->upsert($chunk, ['user_id', 'order_number', 'item_index'], array_keys($chunk[0] ?? []));
        }
        return count($payload);
    }

    public function importIncome(string $path, int $userId): int
    {
        $sheet = IOFactory::load($path)->getSheetByName('Penghasilan');
        $rows = $sheet->toArray(null, true, true, false);
        array_shift($rows); array_shift($rows);
        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_shift($rows));
        $payload = [];
        $itemIndexes = [];

        foreach ($rows as $row) {
            $data = $this->row($headers, $row);
            $orderNumber = $this->text($data['No. Pesanan'] ?? null);
            if ($orderNumber === null || strcasecmp(trim((string) ($data['Lihat berdasarkan'] ?? '')), 'Sku') !== 0) continue;
            $itemKey = $orderNumber.'|'.mb_strtolower(trim((string) ($data['Nama Produk'] ?? '')));
            $itemIndexes[$itemKey] = ($itemIndexes[$itemKey] ?? 0) + 1;
            $payload[] = [
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'item_index' => $itemIndexes[$itemKey],
                'row_type' => $this->text($data['Lihat berdasarkan'] ?? null),
                'source_row' => $this->integer($data['No.'] ?? null),
                'application_number' => $this->text($data['No. Pengajuan'] ?? null),
                'product_id' => $this->text($data['ID Produk'] ?? null),
                'product_name' => $this->text($data['Nama Produk'] ?? null),
                'order_created_at' => $this->date($data['Waktu Pesanan Dibuat'] ?? null),
                'fund_released_at' => $this->date($data['Tanggal Dana Dilepaskan'] ?? null),
                'release_method' => $this->text($data['Metode Pelepasan Dana'] ?? null),
                'order_type' => $this->text($data['Tipe Pesanan'] ?? null),
                'total_income' => $this->number($data['Total Penghasilan'] ?? null),
                'product_price' => $this->number($data['Harga Produk'] ?? null),
                'buyer_shipping_paid' => $this->number($data['Ongkir Dibayar Pembeli'] ?? null),
                'platform_fee' => $this->number($data['Biaya Administrasi'] ?? null),
                'order_processing_fee' => $this->number($data['Biaya Proses Pesanan'] ?? null),
                'free_shipping_xtra_fee' => $this->sumNumbers($data, ['Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori D)', 'Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori E)', 'Biaya Gratis Ongkir XTRA - Ukuran Biasa (Kategori G)']),
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
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('marketplace_income')->where('row_type', '!=', 'Sku')->delete();

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table('marketplace_income')->upsert($chunk, ['user_id', 'order_number', 'item_index'], array_keys($chunk[0] ?? []));
        }
        return count($payload);
    }

    private function row(array $headers, array $values): array { return array_combine($headers, array_pad($values, count($headers), null)) ?: []; }
    private function text(mixed $value): ?string { $value = trim((string) $value); return $value === '' || $value === '-' ? null : $value; }
    private function number(mixed $value): ?float { $value = $this->text($value); if ($value === null) return null; return (float) str_replace(['.', ',', ' '], '', $value); }
    private function sumNumbers(array $data, array $keys): ?float { $values = array_map(fn (string $key): ?float => $this->number($data[$key] ?? null), $keys); $values = array_filter($values, fn (?float $value): bool => $value !== null); return $values === [] ? null : array_sum($values); }

    private function integer(mixed $value): ?int { $value = $this->number($value); return $value === null ? null : (int) $value; }
    private function date(mixed $value): ?string { $value = $this->text($value); if ($value === null) return null; try { return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s'); } catch (Throwable) { return null; } }
}