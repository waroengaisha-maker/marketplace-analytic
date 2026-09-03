<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class UploadReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_report' => ['required_without:income_report', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'income_report' => ['required_without:order_report', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ];
    }

    protected function passedValidation(): void
    {
        foreach ([
            'order_report' => ['sheet' => 'orders', 'headerRow' => 1, 'columns' => ['No. Pesanan', 'Nama Produk', 'Jumlah']],
            'income_report' => ['sheet' => 'Penghasilan', 'headerRow' => 3, 'columns' => ['No. Pesanan', 'Nama Produk', 'Total Penghasilan']],
        ] as $field => $definition) {
            if (!$this->hasFile($field)) {
                continue;
            }

            try {
                $spreadsheet = IOFactory::load($this->file($field)->getRealPath());
                $sheet = $spreadsheet->getSheetByName($definition['sheet']);

                if ($sheet === null) {
                    $this->failedValidationMessage($field, "Sheet `{$definition['sheet']}` tidak ditemukan.");
                }

                $headers = array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $sheet->toArray(null, true, true, false)[$definition['headerRow'] - 1] ?? [],
                );

                $missing = array_diff($definition['columns'], $headers);
                if ($missing !== []) {
                    $this->failedValidationMessage($field, 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.');
                }
            } catch (Throwable) {
                $this->failedValidationMessage($field, 'File tidak dapat dibaca sebagai laporan Excel yang valid.');
            }
        }
    }

    private function failedValidationMessage(string $field, string $message): never
    {
        $this->getValidatorInstance()->errors()->add($field, $message);
        throw new \Illuminate\Validation\ValidationException($this->getValidatorInstance());
    }
}