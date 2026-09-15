<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $rows = $this->mapRows($products);
        $rows = $this->filterRows($rows, $request);
        $rows = $this->sortRows($rows, $request);

        $total = $rows->count();
        $perPage = min(max((int) $request->query('per_page', 10), 10), 100);
        $page = max((int) $request->query('page', 1), 1);
        $current = $rows->forPage($page, $perPage)->values()->all();

        return Inertia::render('Products/Hpp', [
            'products' => $current,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => max((int) ceil($total / $perPage), 1),
                'total' => $total,
            ],
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->where('status', 'active')->count(),
                'ambiguous' => $rows->where('status', 'ambiguous')->count(),
                'missing' => $rows->where('status', 'missing')->count(),
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mapRows(Collection $products): Collection
    {
        return $products->map(function (MasterProduct $product): array {
            $latestHpp = $product->hppRecords->first();
            $units = $product->units->map(function ($unit) use ($product): array {
                $latestUnitHpp = $product->hppRecords
                    ->where('master_unit_id', $unit->id)
                    ->sortByDesc('effective_from')
                    ->first();

                return [
                    'code' => $unit->unit_code,
                    'conversion' => (float) $unit->conversion_to_base,
                    'hpp' => $latestUnitHpp ? 'Rp '.number_format((float) $latestUnitHpp->hpp_amount, 0, ',', '.') : '-',
                ];
            })->values()->all();

            $active = $product->mappings()->where('is_active', true);
            $status = $active->exists() ? 'active' : 'missing';
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
                'match' => $active->exists() ? ($active->latest('updated_at')->value('match_method') ?? 'manual') : 'missing',
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRows(Collection $rows, Request $request): Collection
    {
        $search = strtolower(trim((string) $request->query('search')));

        if ($search === '') {
            return $rows;
        }

        return $rows->filter(function (array $row) use ($search): bool {
            return str_contains(strtolower(implode(' ', [$row['code'], $row['name'], $row['baseUnit']])), $search);
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, Request $request): Collection
    {
        $allowed = ['code', 'name', 'baseUnit', 'status'];
        $field = (string) $request->query('sort_field', 'code');
        $descending = strtolower((string) $request->query('sort_order', 'asc')) === 'desc';

        if (! in_array($field, $allowed, true)) {
            $field = 'code';
        }

        return $rows->sortBy(fn (array $row): string => (string) ($row[$field] ?? ''), SORT_STRING, $descending)->values();
    }
}
