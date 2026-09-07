<?php

namespace App\Services;

class ReportImportService
{
    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
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
}
