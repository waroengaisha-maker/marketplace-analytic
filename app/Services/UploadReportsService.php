<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadReportsService
{
    public function __construct(
        private readonly OrderReportImporter $orders,
        private readonly IncomeReportImporter $income,
    ) {}

    /** @return array{orders: int, income: int} */
    public function storeAndImport(Request $request, User $user): array
    {
        $paths = [];

        try {
            if ($request->hasFile('order_report')) {
                $paths['orders'] = $request->file('order_report')->store('reports/orders');
            }

            if ($request->hasFile('income_report')) {
                $paths['income'] = $request->file('income_report')->store('reports/income');
            }

            return DB::transaction(function () use ($request, $user): array {
                return [
                    'orders' => $request->hasFile('order_report') ? $this->orders->import($request->file('order_report')->getRealPath(), $user->id) : 0,
                    'income' => $request->hasFile('income_report') ? $this->income->import($request->file('income_report')->getRealPath(), $user->id) : 0,
                ];
            });
        } finally {
            if ($paths !== []) {
                try {
                    if (! Storage::delete(array_values($paths))) {
                        report(new \RuntimeException('Uploaded report cleanup failed.'));
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }
}
