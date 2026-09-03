<?php

namespace App\Services;

class OrderReportImporter
{
    public function __construct(private readonly ReportImportService $importer) {}

    public function import(string $path): int
    {
        return $this->importer->importOrders($path);
    }
}