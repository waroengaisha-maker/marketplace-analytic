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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:25', 'max:500'],
            'search' => ['nullable', 'string', 'max:255'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:Settled,Unsettled,Batal,Tidak Valid'],
            'column_filters' => ['nullable', 'json'],
            'sort_field' => ['nullable', 'string', 'in:settlement_status,order_number,order_product_name,quantity,discounted_price,order_created_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'multi_sort_meta' => ['nullable', 'json'],
        ]);
        $hasAppliedFilter = $request->hasAny(['from', 'to']);
        $rows = $hasAppliedFilter
            ? $service->reconciliationPage($request->user()->id, $filters['from'] ?? null, $filters['to'] ?? null, $filters)
            : null;
        $summaryRows = $hasAppliedFilter
            ? $service->reconciliationSummaryRows($request->user()->id, $filters['from'] ?? null, $filters['to'] ?? null, $filters)
            : [];

        return Inertia::render('Finance/Reconciliation', [
            'rows' => $rows?->items() ?? [],
            'pagination' => $rows ? [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ] : null,
            'summaryRows' => $summaryRows,
            'hasAppliedFilter' => $hasAppliedFilter,
            'appliedFrom' => $filters['from'] ?? null,
            'appliedTo' => $filters['to'] ?? null,
        ]);
    }
}
