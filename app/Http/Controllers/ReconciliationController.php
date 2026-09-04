<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReconciliationController extends Controller
{
    public function index(Request $request, MarketplaceReconciliationService $service)
    {
        $rows = $service->joinedQuery($request->user()->id)
            ->orderBy('orders.order_number')
            ->orderBy('orders.item_index')
            ->get()
            ->map(fn (object $row): object => $service->calculateFinancials($row))
            ->values()
            ->all();

        return Inertia::render('Finance/Reconciliation', ['rows' => $rows]);
    }
}