<?php

namespace App\Services;

class IncomeReportImporter
{
    public function __construct(private readonly ReportImportService $importer) {}

    public function import(string $path, int $userId): int
    {
        return $this->importer->importIncome($path, $userId);
    }
}