<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request, MarketplaceReconciliationService $service)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_field' => ['nullable', 'string', 'in:buyer_username,order_count,line_count,net_quantity,subtotal,total_fee,penghasilan,hpp,laba'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ]);
        $buyer = $request->query('customer');
        $buyer = is_string($buyer) && $buyer !== '' ? $buyer : null;

        [$from, $to] = $this->resolveDateRange($filters);

        $page = $service->customerSummariesPage(
            $request->user()->id,
            $from,
            $to,
            $filters,
        );

        return Inertia::render('Customers/Index', [
            'customers' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'appliedFrom' => $from,
            'appliedTo' => $to,
            'summaries' => $service->customerSummariesTotals(
                $request->user()->id,
                $from,
                $to,
                $filters,
            ),
            'details' => $buyer !== null ? [
                'buyer_username' => $buyer,
                'rows' => $service->customerHistory($request->user()->id, $buyer, $from, $to),
            ] : null,
        ]);
    }

    /**
     * All filtered customer summaries without pagination (Excel sheet), so the
     * export includes every customer in the date range, not just the currently
     * visible page.
     */
    public function exportData(Request $request, MarketplaceReconciliationService $service): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        [$from, $to] = $this->resolveDateRange($filters);

        return response()->json([
            'customers' => $service->customerSummariesAll(
                $request->user()->id,
                $from,
                $to,
                $filters,
            ),
        ]);
    }

    /**
     * Customers page defaults to the full order history (no date filter) when
     * no explicit date range is provided.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveDateRange(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($from === null && $to === null) {
            return [null, null];
        }

        return [$from, $to];
    }
}
