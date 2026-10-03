<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly MarketplaceReconciliationService $service,
    ) {}

    public function index(Request $request)
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.users.index');
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $range = $this->service->orderDateRange($request->user()->id);
        $hasAppliedFilter = $request->hasAny(['from', 'to']);

        return Inertia::render('Dashboard', [
            'stats' => $hasAppliedFilter
                ? $this->service->dashboardStats($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null)
                : [],
            'rows' => $hasAppliedFilter
                ? $this->service->reconciliationRows($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null)
                : [],
            'dateRange' => $range,
            'hasAppliedFilter' => $hasAppliedFilter,
            'filters' => [
                'from' => $validated['from'] ?? $range['min'],
                'to' => $validated['to'] ?? $range['max'],
            ],
        ]);
    }
}
