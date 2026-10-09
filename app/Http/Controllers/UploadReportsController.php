<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadReportsRequest;
use App\Models\ReportImportOperation;
use App\Services\UploadReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UploadReportsController extends Controller
{
    private const STALE_PROCESSING_MINUTES = 5;

    public function index(Request $request): Response
    {
        $operationId = $request->session()->get('import_operation_id');

        $operationQuery = ReportImportOperation::query()
            ->where('user_id', $request->user()->id);

        $activeOperation = $operationId
            ? $operationQuery->whereKey($operationId)->first()
            : $operationQuery
                ->whereIn('status', ['queued', 'processing'])
                ->latest('id')
                ->first();

        if (
            $activeOperation?->status === 'processing'
            && $activeOperation->updated_at?->lt(now()->subMinutes(self::STALE_PROCESSING_MINUTES))
        ) {
            $activeOperation->update([
                'status' => 'failed',
                'error_message' => 'Proses import berhenti dan tidak dapat dilanjutkan. Silakan upload kembali laporan.',
            ]);

            $activeOperation->refresh();
        }

        return Inertia::render('Imports/Upload', [
            'activeOperation' => $activeOperation ? [
                'id' => $activeOperation->id,
                'status' => $activeOperation->status,
                'orders' => $activeOperation->orders,
                'income' => $activeOperation->income,
                'error' => $activeOperation->status === 'failed'
                    ? $activeOperation->error_message
                    : null,
            ] : null,
        ]);
    }

    public function store(UploadReportsRequest $request, UploadReportsService $service): RedirectResponse
    {
        try {
            $operation = $service->storeAndQueue($request, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Laporan gagal masuk ke antrean. Silakan coba lagi atau hubungi administrator.');
        }

        return to_route('imports.upload')
            ->with('success', 'Laporan berhasil masuk ke antrean import.')
            ->with('import_operation_id', $operation->id);
    }

    public function status(int $operation, Request $request): JsonResponse
    {
        $record = ReportImportOperation::query()
            ->whereKey($operation)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'id' => $record->id,
            'status' => $record->status,
            'orders' => $record->orders,
            'income' => $record->income,
            'error' => $record->status === 'failed' ? $record->error_message : null,
        ]);
    }
}
