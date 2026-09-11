<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\ShopeeProductMapping;
use App\Models\TemplateItemRow;
use App\Services\ShopeeProductMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HppMappingController extends Controller
{
    public function index(Request $request)
    {
        $templateRows = TemplateItemRow::query()
            ->forUser($request->user()->id)
            ->where('is_active', true)
            ->orderBy('nama_item')
            ->orderBy('kode_item')
            ->get();

        $templateOptions = $templateRows
            ->groupBy('kode_item')
            ->map(function ($rows, $kodeItem): array {
                $first = $rows->first();

                return [
                    'value' => $kodeItem,
                    'label' => trim((string) ($first->nama_item ?? $kodeItem)).' — '.(string) $kodeItem,
                    'hpp_amount' => (float) ($first->hpp_amount ?? 0),
                    'units' => $rows
                        ->sortBy('satuan')
                        ->values()
                        ->map(function ($row): array {
                            return [
                                'value' => $row->satuan,
                                'label' => $row->satuan.' / '.($row->conversion_to_base ?? 1),
                                'code' => $row->satuan,
                                'conversion' => (float) ($row->conversion_to_base ?? 1),
                                'hpp_amount' => (float) ($row->hpp_amount ?? 0),
                            ];
                        })->values()->all(),
                ];
            })
            ->values()
            ->all();

        $orderRows = DB::table('marketplace_orders')
            ->selectRaw('MAX(id) as id, product_name as shopee_product_name, variation_name as shopee_variant_name, parent_sku as shopee_product_id, sku_reference as shopee_variant_id')
            ->where('user_id', $request->user()->id)
            ->whereNotNull('product_name')
            ->groupBy('product_name', 'variation_name', 'parent_sku', 'sku_reference')
            ->orderBy('product_name')
            ->orderBy('variation_name')
            ->get();

        $mappingLookup = ShopeeProductMapping::query()
            ->forUser($request->user()->id)
            ->with(['product', 'unit'])
            ->get()
            ->mapWithKeys(function (ShopeeProductMapping $mapping): array {
                $key = strtolower(trim((string) ($mapping->shopee_product_name ?? ''))).'|'.strtolower(trim((string) ($mapping->shopee_variant_name ?? '')));

                return [$key => $mapping];
            })
            ->all();

        $rows = $orderRows->map(function ($orderRow) use ($mappingLookup): array {
            $productName = $orderRow->shopee_product_name ?? null;
            $variationName = $orderRow->shopee_variant_name ?? null;
            $matchKey = strtolower(trim((string) ($productName ?? ''))).'|'.strtolower(trim((string) ($variationName ?? '')));
            $mapping = $mappingLookup[$matchKey] ?? null;

            $candidate = $mapping && $mapping->product
                ? $mapping->product->template_item_code.' / '.($mapping->product->template_name ?? '-')
                : '-';

            $status = $mapping?->ambiguous ? 'missing' : ($mapping?->match_method === 'manual' ? 'approved' : ($mapping ? 'review' : 'missing'));

            return [
                'id' => (int) $orderRow->id,
                'productName' => $productName ?? '-',
                'variationName' => $variationName ?? '-',
                'shopeeProductId' => $orderRow->shopee_product_id ?? '-',
                'shopeeVariantId' => $orderRow->shopee_variant_id ?? '-',
                'autoMatch' => $mapping?->match_method ?? 'missing',
                'candidate' => $candidate,
                'templateProductId' => $mapping?->master_product_id,
                'templateItemCode' => $mapping?->product?->template_item_code,
                'templateUnitId' => $mapping?->master_unit_id,
                'templateUnitCode' => $mapping?->unit?->unit_code,
                'unit' => $mapping?->unit?->unit_code ?? '-',
                'conversion' => $mapping?->unit?->conversion_to_base ?? 1,
                'confidence' => (float) ($mapping?->match_confidence ?? 0),
                'status' => $status,
            ];
        })->values()->all();

        return Inertia::render('Products/HppMapping', [
            'templateOptions' => $templateOptions,
            'rows' => $rows,
        ]);
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

        foreach ($payloads as $payload) {
            $productCode = $payload['template_item_code'] ?? null;
            $product = null;

            if (! empty($payload['template_product_id'])) {
                $product = MasterProduct::query()->forUser($request->user()->id)->findOrFail($payload['template_product_id']);
            } elseif ($productCode !== null) {
                $product = MasterProduct::query()->forUser($request->user()->id)->where('template_item_code', $productCode)->firstOrFail();
            }

            $unitId = null;
            $unitCode = $payload['template_unit_code'] ?? null;

            if (! empty($payload['template_unit_id'])) {
                $unitId = $product->units()->whereKey($payload['template_unit_id'])->value('id');
            } elseif ($unitCode !== null) {
                $unitId = $product->units()->where('unit_code', $unitCode)->value('id');
            }

            if ($unitId === null && $product->baseUnit !== null) {
                $unitId = $product->baseUnit->id;
            }

            $mappingService->createManualMapping($request->user()->id, $product, $unitId, [
                'shopee_product_id' => $payload['shopee_product_id'] ?? null,
                'shopee_variant_id' => $payload['shopee_variant_id'] ?? null,
                'shopee_product_name' => $payload['shopee_product_name'] ?? null,
                'shopee_variant_name' => $payload['shopee_variant_name'] ?? null,
                'manual_override_note' => $payload['manual_override_note'] ?? null,
                'manual_override_by' => $request->user()->id,
                'match_confidence' => 1.00,
            ]);
        }

        return back()->with('success', 'Manual mapping HPP berhasil disimpan.');
    }
}
