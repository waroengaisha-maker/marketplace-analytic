<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReconciliationController extends Controller
{
    public function index(Request $request, MarketplaceReconciliationService $service)
    {
        return Inertia::render('Finance/Reconciliation', [
            'rows' => $service->reconciliationRows($request->user()->id),
        ]);
    }
}
