<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HppController extends Controller
{
    public function index(Request $request)
    {
        $products = MasterProduct::query()
            ->forUser($request->user()->id)
            ->with(['baseUnit', 'units', 'mappings', 'hppRecords' => fn ($query) => $query->orderBy('effective_from', 'desc')])
            ->orderBy('template_item_code')
            ->get();

        return Inertia::render('Products/Hpp', [
            'products' => $products->map(function (MasterProduct $product): array {
                $latestHpp = $product->hppRecords->first();
                $units = $product->units->map(function ($unit) use ($product): array {
                    $latestUnitHpp = $product->hppRecords
                        ->where('master_unit_id', $unit->id)
                        ->sortByDesc('effective_from')
                        ->first();

                    return [
                        'code' => $unit->unit_code,
                        'conversion' => (float) $unit->conversion_to_base,
                        'hpp' => $latestUnitHpp ? 'Rp '.number_format((float) $latestUnitHpp->hpp_amount, 0, ',', '.') : '-'
                    ];
                })->values()->all();

                $status = $product->mappings()->where('is_active', true)->exists() ? 'active' : 'missing';
                if ($product->mappings()->where('ambiguous', true)->exists()) {
                    $status = 'ambiguous';
                }

                return [
                    'code' => $product->template_item_code,
                    'name' => $product->template_name ?? '-',
                    'baseUnit' => $product->baseUnit?->unit_code ?? '-',
                    'units' => $units,
                    'hpp' => $latestHpp ? 'Rp '.number_format((float) $latestHpp->hpp_amount, 0, ',', '.') : 'Belum ada HPP',
                    'status' => $status,
                    'match' => $product->mappings()->where('is_active', true)->exists() ? $product->mappings()->where('is_active', true)->latest('updated_at')->value('match_method') ?? 'manual' : 'missing',
                ];
            })->values()->all(),
        ]);
    }
}
