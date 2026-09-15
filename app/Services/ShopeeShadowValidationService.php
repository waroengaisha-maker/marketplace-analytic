<?php

namespace App\Services;

use App\Models\ShopeeApiConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shadow validation: compares raw Shopee API staging payloads against the
 * Excel-imported production rows WITHOUT writing to any production table.
 *
 * Three comparison views are produced:
 * - orders  : header-level (order_number, status, payments, timestamps)
 * - lines   : item-level, joined by recomputing line_identity from escrow items
 * - income  : order-level aggregates (row counts and total income)
 *
 * Every view classifies each key as matched / mismatched / missing_in_excel /
 * missing_in_api and carries field-level differences for review. Tenant data is
 * read strictly through the owning connection's user_id.
 */
class ShopeeShadowValidationService
{
    private const ORDER_FIELDS = [
        'order_status',
        'payment_method',
        'buyer_username',
        'shipping_option',
        'order_created_at',
        'payment_at',
        'shipped_at',
    ];

    private const LINE_FIELDS = [
        'parent_sku',
        'sku_reference',
        'product_name',
        'variation_name',
        'original_price',
        'discounted_price',
        'quantity',
    ];

    public function __construct(private readonly ShopeeResponseNormalizer $normalizer) {}

    /**
     * @return array<string, mixed>
     */
    public function report(ShopeeApiConnection $connection): array
    {
        $userId = $connection->user_id;

        $apiOrderHeaders = $this->normalizer->normalizeOrderHeaders($connection->staging_orders ?? []);
        $apiEscrow = collect($connection->staging_escrow ?? [])->mapWithKeys(
            fn (array $entry): array => [(string) data_get($entry, 'order_sn', '') => (array) data_get($entry, 'response.order_income', [])]
        );
        $apiLines = $this->apiLines((array) $connection->staging_orders ?? [], $apiEscrow);
        $apiIncomeHeaders = $this->normalizer->normalizeIncomeRows($connection->staging_income ?? []);

        [$excelOrderHeaders, $excelLines] = $this->excelOrders($userId);
        $excelIncomeHeaders = $this->excelIncome($userId);

        $orders = $this->compareOrders($apiOrderHeaders, $excelOrderHeaders);
        $lines = $this->compareLines($apiLines, $excelLines);
        $income = $this->compareIncome($apiIncomeHeaders, $excelIncomeHeaders);

        return [
            'ok' => true,
            'generated_at' => now()->toDateTimeString(),
            'sync' => [
                'last_sync_at' => $connection->last_sync_at?->toDateTimeString(),
                'last_sync_status' => $connection->last_sync_status,
                'last_sync_error' => $connection->last_sync_error,
            ],
            'sources' => [
                'api' => [
                    'orders' => count($apiOrderHeaders),
                    'lines' => count($apiLines),
                    'income' => count($apiIncomeHeaders),
                ],
                'excel' => [
                    'orders' => count($excelOrderHeaders),
                    'lines' => count($excelLines),
                    'income' => count($excelIncomeHeaders),
                ],
            ],
            'summary' => [
                'orders' => $this->summarize($orders),
                'lines' => $this->summarize($lines) + [
                    'line_identity_changed' => collect($lines)
                        ->where('line_identity_changed', true)
                        ->count(),
                ],
                'income' => $this->summarize($income),
            ],
            'orders' => $orders,
            'lines' => $lines,
            'income' => $income,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiHeaders
     * @param  array<int, array<string, mixed>>  $excelHeaders
     * @return array<int, array<string, mixed>>
     */
    private function compareOrders(array $apiHeaders, array $excelHeaders): array
    {
        $apiIndex = $this->indexBy($apiHeaders, 'order_number');
        $excelIndex = $this->indexBy($excelHeaders, 'order_number');

        return $this->compareSets($apiIndex, $excelIndex, self::ORDER_FIELDS, static fn (array $row): string => (string) $row['order_number']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiLines
     * @param  array<int, array<string, mixed>>  $excelLines
     * @return array<int, array<string, mixed>>
     */
    private function compareLines(array $apiLines, array $excelLines): array
    {
        $apiIndex = $this->indexBy($apiLines, 'line_identity');
        $excelIndex = $this->indexBy($excelLines, 'line_identity');

        $apiBySoft = $this->groupBySoftKey($apiLines);
        $excelBySoft = $this->groupBySoftKey($excelLines);

        $rows = $this->compareSets($apiIndex, $excelIndex, self::LINE_FIELDS, static fn (array $row): string => (string) $row['line_identity']);

        return array_map(function (array $row) use ($apiIndex, $excelIndex, $apiBySoft, $excelBySoft): array {
            $identity = (string) $row['key'];
            $api = $apiIndex[$identity] ?? null;
            $excel = $excelIndex[$identity] ?? null;
            $status = $row['status'];

            $softKey = $this->softKey($api ?? $excel ?? []);
            $counterpart = $status === 'missing_in_api'
                ? ($apiBySoft[$softKey][0] ?? null)
                : ($excelBySoft[$softKey][0] ?? null);

            $identityChanged = $counterpart !== null
                && (string) ($counterpart['line_identity'] ?? '') !== $identity;

            return $row + [
                'order_number' => $api['order_number'] ?? data_get($row, 'excel.order_number'),
                'line_identity' => $identity,
                'line_identity_changed' => $identityChanged,
                'changed_from_identity' => $identityChanged ? (string) ($counterpart['line_identity'] ?? '') : null,
                'api_product_name' => $api['product_name'] ?? null,
                'excel_product_name' => data_get($row, 'excel.product_name'),
            ];
        }, $rows);
    }

    /**
     * A soft key identifies the same logical product line regardless of the
     * identity algorithm's inputs (order, product name and variation name).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBySoftKey(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$this->softKey($row)][] = $row;
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function softKey(?array $row): string
    {
        if ($row === null) {
            return '';
        }

        return implode('|', [
            (string) ($row['order_number'] ?? ''),
            mb_strtolower(trim((string) ($row['product_name'] ?? ''))),
            mb_strtolower(trim((string) ($row['variation_name'] ?? ''))),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $apiHeaders
     * @param  array<int, array<string, mixed>>  $excelHeaders
     * @return array<int, array<string, mixed>>
     */
    private function compareIncome(array $apiHeaders, array $excelHeaders): array
    {
        $api = $this->aggregateIncome($apiHeaders);
        $excel = $this->aggregateIncome($excelHeaders);

        $keys = array_values(array_unique(array_merge(array_keys($api), array_keys($excel))));

        return array_map(function (string $key) use ($api, $excel): array {
            $apiRow = $api[$key] ?? null;
            $excelRow = $excel[$key] ?? null;

            if ($apiRow === null) {
                return ['key' => $key, 'order_number' => $key, 'status' => 'missing_in_api', 'api' => null, 'excel' => $excelRow, 'differences' => []];
            }

            if ($excelRow === null) {
                return ['key' => $key, 'order_number' => $key, 'status' => 'missing_in_excel', 'api' => $apiRow, 'excel' => null, 'differences' => []];
            }

            $differences = $this->fieldDiffs($apiRow, $excelRow, ['count', 'total_income']);

            return [
                'key' => $key,
                'order_number' => $key,
                'status' => $differences === [] ? 'matched' : 'mismatched',
                'api' => $apiRow,
                'excel' => $excelRow,
                'differences' => $differences,
            ];
        }, $keys);
    }

    /**
     * @param  array<string, array<string, mixed>>  $api
     * @param  array<string, array<string, mixed>>  $excel
     * @param  list<string>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function compareSets(array $api, array $excel, array $fields, callable $label): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($api), array_keys($excel))));

        return array_map(function (string $key) use ($api, $excel, $fields, $label): array {
            $apiRow = $api[$key] ?? null;
            $excelRow = $excel[$key] ?? null;

            if ($apiRow === null) {
                return ['key' => $key, 'label' => $label($excelRow), 'status' => 'missing_in_api', 'api' => null, 'excel' => $excelRow, 'differences' => []];
            }

            if ($excelRow === null) {
                return ['key' => $key, 'label' => $label($apiRow), 'status' => 'missing_in_excel', 'api' => $apiRow, 'excel' => null, 'differences' => []];
            }

            $differences = $this->fieldDiffs($apiRow, $excelRow, $fields);

            return [
                'key' => $key,
                'label' => $label($apiRow),
                'status' => $differences === [] ? 'matched' : 'mismatched',
                'api' => $apiRow,
                'excel' => $excelRow,
                'differences' => $differences,
            ];
        }, $keys);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function summarize(array $rows): array
    {
        $counts = [
            'matched' => 0,
            'mismatched' => 0,
            'missing_in_excel' => 0,
            'missing_in_api' => 0,
        ];

        foreach ($rows as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']]++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexBy(array $rows, string $field): array
    {
        $index = [];

        foreach ($rows as $row) {
            $key = (string) ($row[$field] ?? '');
            if ($key !== '') {
                $index[$key] = $row;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $api
     * @param  array<string, mixed>  $excel
     * @param  list<string>  $fields
     * @return array<int, array{field: string, api: mixed, excel: mixed}>
     */
    private function fieldDiffs(array $api, array $excel, array $fields): array
    {
        $diffs = [];

        foreach ($fields as $field) {
            $apiValue = $api[$field] ?? null;
            $excelValue = $excel[$field] ?? null;

            if (! $this->equal($apiValue, $excelValue)) {
                $diffs[] = ['field' => $field, 'api' => $apiValue, 'excel' => $excelValue];
            }
        }

        return $diffs;
    }

    private function equal(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.005;
        }

        return $this->normalizeScalar($a) === $this->normalizeScalar($b);
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return strtolower(trim($value));
    }

    /**
     * Recomputes line_identity for the API escrow items using the same
     * ReportLineIdentity algorithm the Excel importers rely on, so the shadow
     * report can compare identity stability.
     *
     * @param  array<int, array<string, mixed>>  $stagedOrders
     * @param  Collection<int, array<string, mixed>>  $apiEscrow
     * @return array<int, array<string, mixed>>
     */
    private function apiLines(array $stagedOrders, Collection $apiEscrow): array
    {
        $lines = [];
        $fallbackOrders = $this->indexBy(
            $this->normalizer->normalizeOrderHeaders($stagedOrders),
            'order_number'
        );

        foreach ($apiEscrow as $orderSn => $orderIncome) {
            $normalized = $this->normalizer->normalizeOrderLines($orderIncome);

            foreach ($normalized as $line) {
                $productKey = hash('sha256', mb_strtolower(trim((string) ($line['product_name'] ?? ''))));
                $variationKey = $line['variation_name'] === null ? null : hash('sha256', mb_strtolower(trim((string) $line['variation_name'])));

                $lines[] = $line + [
                    'order_number' => $orderSn,
                    'product_key' => $productKey,
                    'variation_key' => $variationKey,
                    'line_identity' => ReportLineIdentity::make(
                        (string) $orderSn,
                        $productKey,
                        $variationKey,
                        $line['discounted_price'] === null ? null : (float) $line['discounted_price'],
                        $line['quantity'] === null ? null : (int) $line['quantity'],
                    ),
                    'order_status' => data_get($fallbackOrders, "$orderSn.order_status"),
                ];
            }
        }

        return $lines;
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function excelOrders(int $userId): array
    {
        $rows = DB::table('marketplace_orders')
            ->where('user_id', $userId)
            ->get();

        $headers = [];
        $lines = [];

        foreach ($rows as $row) {
            $line = [
                'order_number' => $row->order_number,
                'order_status' => $row->order_status,
                'payment_method' => $row->payment_method,
                'buyer_username' => $row->buyer_username,
                'shipping_option' => $row->shipping_option,
                'order_created_at' => $this->toDateTimeString($row->order_created_at),
                'payment_at' => $this->toDateTimeString($row->payment_at),
                'shipped_at' => $this->toDateTimeString($row->shipped_at),
                'parent_sku' => $row->parent_sku,
                'sku_reference' => $row->sku_reference,
                'product_name' => $row->product_name,
                'variation_name' => $row->variation_name,
                'original_price' => $row->original_price === null ? null : (float) $row->original_price,
                'discounted_price' => $row->discounted_price === null ? null : (float) $row->discounted_price,
                'quantity' => $row->quantity === null ? null : (int) $row->quantity,
                'line_identity' => $row->line_identity,
            ];

            $headers[$row->order_number] ??= $line;
            $lines[] = $line;
        }

        return [array_values($headers), $lines];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function excelIncome(int $userId): array
    {
        $rows = DB::table('marketplace_income')
            ->where('user_id', $userId)
            ->get()
            ->all();

        return array_map(fn (mixed $row): array => [
            'order_number' => $row->order_number,
            'row_type' => $row->row_type,
            'status' => null,
            'total_income' => $row->total_income === null ? null : (float) $row->total_income,
            'income_released_at' => $this->toDateTimeString($row->fund_released_at),
        ], $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $headers
     * @return array<string, array<string, mixed>>
     */
    private function aggregateIncome(array $headers): array
    {
        $aggregate = [];

        foreach ($headers as $row) {
            $key = (string) ($row['order_number'] ?? '');
            if ($key === '') {
                continue;
            }

            $aggregate[$key] ??= ['count' => 0, 'total_income' => 0.0];
            $aggregate[$key]['count']++;
            $aggregate[$key]['total_income'] += (float) ($row['total_income'] ?? 0);
        }

        return $aggregate;
    }

    private function toDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
