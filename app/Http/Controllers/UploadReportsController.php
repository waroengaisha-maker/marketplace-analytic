<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadReportsRequest;
use App\Services\UploadReportsService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class UploadReportsController extends Controller
{
    public function store(UploadReportsRequest $request, UploadReportsService $service): RedirectResponse
    {
        try {
            $result = $service->storeAndImport($request);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withInput()->with('error', 'Laporan gagal diunggah. Silakan coba lagi.');
        }

        return to_route('imports.upload')->with('success', $result['orders'] > 0 && $result['income'] > 0 ? sprintf('Import berhasil. Order: %d baris, Income: %d baris.', $result['orders'], $result['income']) : ($result['orders'] > 0 ? sprintf('Import Order berhasil: %d baris.', $result['orders']) : sprintf('Import Income berhasil: %d baris.', $result['income'])));
    }
}