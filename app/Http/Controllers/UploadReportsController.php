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
            $service->store($request);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withInput()->withErrors(['upload' => 'Laporan gagal diunggah. Silakan coba lagi.']);
        }

        return to_route('imports.upload')->with('success', 'Kedua laporan berhasil diunggah dan siap diproses.');
    }
}