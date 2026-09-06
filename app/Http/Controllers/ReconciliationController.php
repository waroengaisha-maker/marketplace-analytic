<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReconciliationController extends Controller
{
    public function index(Request $request, MarketplaceReconciliationService $service)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $hasAppliedFilter = $request->hasAny(['from', 'to']);

        return Inertia::render('Finance/Reconciliation', [
            'rows' => $hasAppliedFilter
                ? $service->reconciliationRows($request->user()->id, $filters['from'] ?? null, $filters['to'] ?? null)
                : [],
            'hasAppliedFilter' => $hasAppliedFilter,
        ]);
    }
}
