<?php
namespace App\Http\Controllers;
use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
class ReconciliationController extends Controller
{
 public function index(Request $request, MarketplaceReconciliationService $service)
 { return Inertia::render('Finance/Reconciliation', ['rows'=>$service->joinedQuery($request->user()->id)->orderBy('orders.order_number')->orderBy('orders.item_index')->paginate(50)->through(fn (object $row): object => $service->calculateFinancials($row))->withQueryString()]); }
}