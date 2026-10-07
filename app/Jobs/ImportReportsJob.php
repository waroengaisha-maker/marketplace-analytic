<?php

namespace App\Jobs;

use App\Events\ImportStatusUpdated;
use App\Models\ReportImportOperation;
use App\Services\IncomeReportImporter;
use App\Services\OrderReportImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportReportsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $operationId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->operationId;
    }

    public function handle(OrderReportImporter $orders, IncomeReportImporter $income): void
    {
        $operation = ReportImportOperation::query()->findOrFail($this->operationId);

        if ($operation->status === 'completed') {
            return;
        }

        $operation->update(['status' => 'processing', 'error_message' => null]);
        $operation->refresh();
        ImportStatusUpdated::dispatch($operation);

        try {
            $result = DB::transaction(function () use ($operation, $orders, $income): array {
                return [
                    'orders' => $operation->order_path
                        ? $orders->import(Storage::disk('local')->path($operation->order_path), $operation->user_id)
                        : 0,
                    'income' => $operation->income_path
                        ? $income->import(Storage::disk('local')->path($operation->income_path), $operation->user_id)
                        : 0,
                ];
            });

            $operation->update([
                'status' => 'completed',
                'orders' => $result['orders'],
                'income' => $result['income'],
            ]);
            $operation->refresh();
            ImportStatusUpdated::dispatch($operation);
        } catch (Throwable $exception) {
            report($exception);
            $operation->update([
                'status' => 'failed',
                'error_message' => 'Laporan gagal diproses. Silakan coba lagi atau hubungi administrator.',
            ]);
            $operation->refresh();
            ImportStatusUpdated::dispatch($operation);
            throw $exception;
        } finally {
            $paths = array_filter([$operation->order_path, $operation->income_path]);
            if ($paths !== []) {
                try {
                    Storage::disk('local')->delete(array_values($paths));
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }
}
