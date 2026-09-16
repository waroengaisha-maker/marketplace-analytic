<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\JsonResponse;
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
            'sort_field' => ['nullable', 'string', 'in:order_number,order_created_at,buyer_username,business_status,line_count,net_quantity,subtotal,admin,shipping,promo,processing,total_fee,tax,hpp,penghasilan,laba'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:Settled,Unsettled,Batal,Tidak Valid,Refunded,Partially Refunded,Returned,Unmatched,Cancelled,Invalid'],
        ]);
        $orderNumber = $request->query('order');
        $orderNumber = is_string($orderNumber) && $orderNumber !== '' ? $orderNumber : null;

        [$from, $to] = $this->resolveDateRange($filters);

        $page = $service->orderSummariesPage(
            $request->user()->id,
            $from,
            $to,
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
            'appliedFrom' => $from,
            'appliedTo' => $to,
            'summaries' => $service->orderSummariesTotals(
                $request->user()->id,
                $from,
                $to,
                $filters,
            ),
            'details' => $orderNumber !== null ? [
                'order_number' => $orderNumber,
                'rows' => $service->orderLines($request->user()->id, $orderNumber),
            ] : null,
        ]);
    }

    /**
     * Line-level export data for every filtered order (Excel "Detail Per Item"
     * sheet), mirrored from the Reconciliation export model.
     */
    public function exportLines(Request $request, MarketplaceReconciliationService $service): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', 'in:Settled,Unsettled,Batal,Tidak Valid,Refunded,Partially Refunded,Returned,Unmatched,Cancelled,Invalid'],
        ]);

        [$from, $to] = $this->resolveDateRange($filters);

        return response()->json([
            'rows' => $service->orderExportLines(
                $request->user()->id,
                $from,
                $to,
                $filters,
            ),
        ]);
    }

    /**
     * Orders page defaults to the current month (1st of month through today)
     * when no explicit date range is provided, so both the filter and the
     * rendered data share the same default.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveDateRange(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($from === null && $to === null) {
            $today = today();

            return [$today->copy()->startOfMonth()->format('Y-m-d'), $today->format('Y-m-d')];
        }

        return [$from, $to];
    }
}
