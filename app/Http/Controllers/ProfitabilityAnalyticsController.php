<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfitabilityAnalyticsController extends Controller
{
    public function index(Request $request, MarketplaceReconciliationService $service)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'dimension' => ['nullable', 'string', 'in:product,sku,variation,day,month'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $hasAppliedFilter = $request->hasAny(['from', 'to']);
        $dimension = $validated['dimension'] ?? 'product';

        return Inertia::render('Analytics/Profitability', [
            'rows' => $hasAppliedFilter
                ? $service->profitabilityAnalytics(
                    $request->user()->id,
                    $dimension,
                    $validated['from'] ?? null,
                    $validated['to'] ?? null,
                    ['sort_order' => $validated['sort_order'] ?? 'desc'],
                )
                : [],
            'dimension' => $dimension,
            'hasAppliedFilter' => $hasAppliedFilter,
            'appliedFrom' => $validated['from'] ?? null,
            'appliedTo' => $validated['to'] ?? null,
        ]);
    }
}
