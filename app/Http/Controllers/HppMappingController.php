<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\ShopeeProductMapping;
use App\Services\MasterProductCatalogService;
use App\Services\OrderHppSyncService;
use App\Services\ShopeeProductMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class HppMappingController extends Controller
{
    public function index(Request $request, ShopeeProductMappingService $mappingService)
    {
        $templateOptions = $this->buildTemplateOptions($request->user()->id);

        $rows = $this->resolvedRows($request, $mappingService);
        $rows = $this->filterRows($rows, $request);
        $rows = $this->sortRows($rows, $request);

        $total = $rows->count();
        $perPage = min(max((int) $request->query('per_page', 25), 10), 100);
        $page = max((int) $request->query('page', 1), 1);
        $current = $rows->forPage($page, $perPage)->values()->all();

        return Inertia::render('Products/HppMapping', [
            'templateOptions' => $templateOptions,
            'rows' => $current,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => max((int) ceil($total / $perPage), 1),
                'total' => $total,
            ],
        ]);
    }

    public function exportData(Request $request, ShopeeProductMappingService $mappingService): JsonResponse
    {
        $rows = $this->resolvedRows($request, $mappingService);
        $rows = $this->filterRows($rows, $request);
        $rows = $this->sortRows($rows, $request);

        return response()->json($rows->values()->all());
    }

    public function reallocate(Request $request, OrderHppSyncService $service): JsonResponse
    {
        $counts = $service->sync($request->user()->id);

        return response()->json($counts);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function resolvedRows(Request $request, ShopeeProductMappingService $mappingService): Collection
    {
        $orderRows = DB::table('marketplace_orders')
            ->selectRaw('MAX(id) as id, product_name as shopee_product_name, variation_name as shopee_variant_name, parent_sku as shopee_product_id, sku_reference as shopee_variant_id')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('product_name')
            ->groupBy('product_name', 'variation_name', 'parent_sku', 'sku_reference')
            ->orderBy('product_name')
            ->orderBy('variation_name')
            ->get();

        $resolvedRows = $orderRows->map(function ($orderRow) use ($request, $mappingService): array {
            return [
                'orderRow' => $orderRow,
                'resolved' => $mappingService->resolve($request->user()->id, [
                    'shopee_product_id' => $orderRow->shopee_product_id,
                    'shopee_variant_id' => $orderRow->shopee_variant_id,
                    'shopee_product_name' => $orderRow->shopee_product_name,
                    'shopee_variant_name' => $orderRow->shopee_variant_name,
                ]),
            ];
        });

        $mappingIds = $resolvedRows
            ->map(fn ($entry) => $entry['resolved']['mapping']->id ?? null)
            ->filter()
            ->unique()
            ->values();

        ShopeeProductMapping::query()
            ->whereIn('id', $mappingIds)
            ->with(['product', 'unit'])
            ->get();

        return $resolvedRows->map(function ($entry): array {
            $orderRow = $entry['orderRow'];
            $resolved = $entry['resolved'];
            $mapping = $resolved['mapping'] ?? null;

            $status = match ($resolved['status']) {
                'matched' => $resolved['match_method'],
                'ambiguous' => 'ambiguous',
                default => 'missing',
            };

            $autoMatch = $mapping?->match_method ?? ($resolved['status'] === 'ambiguous' ? 'ambiguous' : 'missing');

            $candidate = null;
            if ($mapping !== null && $mapping->product !== null) {
                $candidate = $mapping->product->template_item_code.' / '.($mapping->product->template_name ?? '-');
            } elseif ($mapping !== null) {
                $candidate = $mapping->shopee_product_name ?? '-';
            } elseif (! empty($resolved['candidates'])) {
                $candidate = collect($resolved['candidates'])
                    ->map(fn ($candidateMapping) => $candidateMapping?->product?->template_item_code ?? $candidateMapping->shopee_product_name ?? '-')
                    ->implode(', ');
            }

            return [
                'id' => (int) $orderRow->id,
                'productName' => $orderRow->shopee_product_name ?? '-',
                'variationName' => $orderRow->shopee_variant_name ?? '-',
                'shopeeProductId' => $orderRow->shopee_product_id ?? '-',
                'shopeeVariantId' => $orderRow->shopee_variant_id ?? '-',
                'autoMatch' => $autoMatch,
                'candidate' => $candidate ?? '-',
                'templateProductId' => $mapping?->master_product_id,
                'templateItemCode' => $mapping?->product?->template_item_code,
                'templateUnitId' => $mapping?->master_unit_id,
                'templateUnitCode' => $mapping?->unit?->unit_code,
                'unit' => $mapping?->unit?->unit_code ?? '-',
                'conversion' => $mapping?->unit?->conversion_to_base ?? 1,
                'confidence' => (float) ($mapping?->match_confidence ?? 0),
                'status' => $status,
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
        $status = (string) $request->query('status');

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                return str_contains(strtolower(implode(' ', [
                    $row['productName'],
                    $row['variationName'],
                    $row['shopeeProductId'],
                    $row['shopeeVariantId'],
                    $row['candidate'],
                ])), $search);
            })->values();
        }

        if ($status !== '' && $status !== 'all') {
            $rows = $rows->filter(fn (array $row): bool => $row['autoMatch'] === $status)->values();
        }

        return $rows;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, Request $request): Collection
    {
        $allowed = ['productName', 'variationName', 'autoMatch', 'candidate', 'unit'];
        $field = (string) $request->query('sort_field', 'productName');
        $descending = strtolower((string) $request->query('sort_order', 'asc')) === 'desc';

        if (! in_array($field, $allowed, true)) {
            $field = 'productName';
        }

        return $rows->sortBy(fn (array $row): string => (string) ($row[$field] ?? ''), SORT_STRING, $descending)->values();
    }

    public function syncTemplateCatalog(Request $request, MasterProductCatalogService $catalog): JsonResponse
    {
        $result = $catalog->syncFromTemplateRows($request->user()->id);

        return response()->json([
            'ok' => $result['ok'],
            'created' => $result['created'],
            'existing' => $result['existing'],
            'errors' => $result['errors'],
            'templateOptions' => $this->buildTemplateOptions($request->user()->id),
        ]);
    }

    private function buildTemplateOptions(int $userId): array
    {
        return MasterProduct::query()
            ->forUser($userId)
            ->active()
            ->with([
                'units' => fn ($query) => $query->orderBy('conversion_to_base'),
                'hppRecords' => fn ($query) => $query->orderByDesc('effective_from'),
            ])
            ->orderBy('template_name')
            ->orderBy('template_item_code')
            ->get()
            ->map(function (MasterProduct $product): array {
                $units = $product->units
                    ->map(function ($unit) use ($product): array {
                        $latestHpp = $product->hppRecords->firstWhere('master_unit_id', $unit->id);

                        return [
                            'value' => $unit->unit_code,
                            'label' => $unit->unit_code.' / '.($unit->conversion_to_base ?? 1),
                            'code' => $unit->unit_code,
                            'conversion' => (float) ($unit->conversion_to_base ?? 1),
                            'hpp_amount' => $latestHpp !== null ? (float) $latestHpp->hpp_amount : 0,
                        ];
                    })
                    ->values()
                    ->all();

                $templateHpp = $product->hppRecords->sortByDesc('effective_from')->first();

                return [
                    'value' => $product->template_item_code,
                    'label' => trim((string) ($product->template_name ?? $product->template_item_code)).' — '.$product->template_item_code,
                    'hpp_amount' => $templateHpp !== null ? (float) $templateHpp->hpp_amount : 0,
                    'units' => $units,
                ];
            })
            ->values()
            ->all();
    }

    public function store(Request $request, ShopeeProductMappingService $mappingService)
    {
        $validated = $request->validate([
            'mappings' => ['sometimes', 'array'],
            'mappings.*.id' => ['nullable', 'integer'],
            'mappings.*.shopee_product_id' => ['nullable', 'string', 'max:128'],
            'mappings.*.shopee_variant_id' => ['nullable', 'string', 'max:128'],
            'mappings.*.shopee_product_name' => ['nullable', 'string', 'max:255'],
            'mappings.*.shopee_variant_name' => ['nullable', 'string', 'max:255'],
            'mappings.*.template_item_code' => ['required_without:mappings.*.template_product_id', 'string', 'max:64'],
            'mappings.*.template_product_id' => ['nullable', 'integer'],
            'mappings.*.template_unit_code' => ['nullable', 'string', 'max:32'],
            'mappings.*.template_unit_id' => ['nullable', 'integer'],
            'mappings.*.manual_override_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payloads = $validated['mappings'] ?? [];
        $errors = [];

        foreach ($payloads as $index => $payload) {
            $product = null;

            if (! empty($payload['template_product_id'])) {
                $product = MasterProduct::query()->forUser($request->user()->id)->find($payload['template_product_id']);
            } elseif (! empty($payload['template_item_code'])) {
                $product = MasterProduct::query()->forUser($request->user()->id)->where('template_item_code', $payload['template_item_code'])->first();
            }

            if ($product === null) {
                $errors["mappings.$index.template_item_code"] = 'Template item tidak ditemukan untuk akun Anda.';

                continue;
            }

            $unit = null;
            if (! empty($payload['template_unit_id'])) {
                $unit = $product->units()->whereKey($payload['template_unit_id'])->first();
            } elseif (! empty($payload['template_unit_code'])) {
                $unit = $product->units()->where('unit_code', $payload['template_unit_code'])->first();
            }

            if ($unit === null) {
                $errors["mappings.$index.template_unit_code"] = 'Satuan harus dipilih dari unit milik template item terpilih.';

                continue;
            }

            $mappingService->createManualMapping($request->user()->id, $product, $unit->id, [
                'shopee_product_id' => $payload['shopee_product_id'] ?? null,
                'shopee_variant_id' => $payload['shopee_variant_id'] ?? null,
                'shopee_product_name' => $payload['shopee_product_name'] ?? null,
                'shopee_variant_name' => $payload['shopee_variant_name'] ?? null,
                'manual_override_note' => $payload['manual_override_note'] ?? null,
                'manual_override_by' => $request->user()->id,
                'match_confidence' => 1.00,
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return back()->with('success', 'Manual mapping HPP berhasil disimpan.');
    }
}
