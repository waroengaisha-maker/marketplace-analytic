<?php

namespace App\Services;

class IncomeReportImporter
{
    public function __construct(private readonly ReportImportService $importer) {}

    public function import(string $path): int
    {
        return $this->importer->importIncome($path);
    }
}