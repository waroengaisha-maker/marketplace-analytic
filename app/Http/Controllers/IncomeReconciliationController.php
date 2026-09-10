<?php

namespace App\Http\Controllers;

use App\Services\IncomeReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IncomeReconciliationController extends Controller
{
    public function index(Request $request, IncomeReconciliationService $service)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:25', 'max:500'],
            'search' => ['nullable', 'string', 'max:255'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:Matched,Orphan,Ambiguous'],
            'refund_type' => ['nullable', 'string', 'in:Full,Partial,None'],
            'sort_field' => ['nullable', 'string', 'in:order_number,product_name,total_income,refund_amount,income_match_status'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $rows = $service->page(
            $request->user()->id,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters,
        );

        return Inertia::render('Finance/IncomeReconciliation', [
            'rows' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
            'filters' => $filters,
        ]);
    }
}
