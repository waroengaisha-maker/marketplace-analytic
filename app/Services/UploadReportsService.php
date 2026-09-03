<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadReportsService
{
    public function __construct(
        private readonly OrderReportImporter $orders,
        private readonly IncomeReportImporter $income,
    ) {}

    /** @return array{orders: int, income: int} */
    public function storeAndImport(Request $request): array
    {
        $paths = [
            $request->file('order_report')->store('reports/orders'),
            $request->file('income_report')->store('reports/income'),
        ];

        try {
            $result = DB::transaction(fn (): array => [
                'orders' => $this->orders->import($request->file('order_report')->getRealPath()),
                'income' => $this->income->import($request->file('income_report')->getRealPath()),
            ]);

            Storage::delete($paths);
            return $result;
        } catch (\Throwable $exception) {
            throw $exception;
        }
    }
}