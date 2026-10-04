<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadReportsRequest;
use App\Models\ReportImportOperation;
use App\Services\UploadReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class UploadReportsController extends Controller
{
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
