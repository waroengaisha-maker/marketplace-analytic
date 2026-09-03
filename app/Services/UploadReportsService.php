<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadReportsService
{
    /** @throws Throwable */
    public function store(Request $request): void
    {
        $storedPaths = [];

        try {
            $storedPaths[] = $request->file('order_report')->store('reports/orders');
            $storedPaths[] = $request->file('income_report')->store('reports/income');
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::delete($path);
            }
            throw $exception;
        }
    }
}