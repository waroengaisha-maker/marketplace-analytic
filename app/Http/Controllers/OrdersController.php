<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrdersController extends Controller
{
    public function index(Request $request, MarketplaceReconciliationService $service)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_field' => ['nullable', 'string', 'in:order_number,order_created_at,line_count,subtotal,admin,shipping,promo,processing,tax,hpp,penghasilan,laba'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:Settled,Unsettled,Batal,Tidak Valid,Refunded,Partially Refunded,Returned,Unmatched,Cancelled,Invalid'],
        ]);
        $orderNumber = $request->query('order');
        $orderNumber = is_string($orderNumber) && $orderNumber !== '' ? $orderNumber : null;

        $page = $service->orderSummariesPage(
            $request->user()->id,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters,
        );

        return Inertia::render('Orders/Index', [
            'orders' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'appliedFrom' => $filters['from'] ?? null,
            'appliedTo' => $filters['to'] ?? null,
            'summaries' => $service->orderSummariesTotals(
                $request->user()->id,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters,
            ),
            'details' => $orderNumber !== null ? [
                'order_number' => $orderNumber,
                'rows' => $service->orderLines($request->user()->id, $orderNumber),
            ] : null,
        ]);
    }
}
