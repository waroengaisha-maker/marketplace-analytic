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
            $result = $service->storeAndImport($request, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            $message = config('app.debug')
                ? 'Laporan gagal diproses: '.$exception->getMessage()
                : 'Laporan gagal diproses. Silakan coba lagi atau hubungi administrator.';

            return back()->withInput()->with('error', $message);
        }

        return to_route('imports.upload')->with('success', $result['orders'] > 0 && $result['income'] > 0 ? sprintf('Import berhasil. Order: %d baris, Income: %d baris.', $result['orders'], $result['income']) : ($result['orders'] > 0 ? sprintf('Import Order berhasil: %d baris.', $result['orders']) : sprintf('Import Income berhasil: %d baris.', $result['income'])));
    }
}