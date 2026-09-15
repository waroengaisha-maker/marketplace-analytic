<?php

namespace App\Services;

use App\Models\AccountAuditLog;
use App\Models\ShopeeApiConnection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Controlled Shopee API -> production import.
 *
 * Promotion is gated on a passing shadow validation report and runs inside a
 * single transaction. Dry-run mode computes the exact promotion plan WITHOUT
 * writing a single production row. Persistence reuses OrderReportImporter and
 * IncomeReportImporter (replace-per-order-number + cost allocation cascade) so
 * the production contracts stay identical to the Excel path. Every attempt is
 * recorded in the account audit trail.
 */
class ShopeePromotionService
{
    public function __construct(
        private readonly ShopeeShadowValidationService $validator,
        private readonly ShopeeResponseNormalizer $normalizer,
        private readonly OrderReportImporter $orderImporter,
        private readonly IncomeReportImporter $incomeImporter,
    ) {}

    /**
     * Dry-run: validate + plan exactly like promote() but write zero rows.
     *
     * @return array<string, mixed>
     */
    public function preview(ShopeeApiConnection $connection): array
    {
        $report = $this->validator->report($connection);
        $plan = $this->plan($connection, $report);

        if ($plan['gate']['passed']) {
            $this->audit($connection, 'shopee_api.promotion.preview', [
                'dry_run' => true,
                'gate' => 'passed',
                'scheduled' => ['lines' => count($plan['orders']), 'income' => count($plan['income'])],
            ]);
        } else {
            $this->audit($connection, 'shopee_api.promotion.preview', [
                'dry_run' => true,
                'gate' => 'blocked',
                'reason' => $plan['gate']['reason'],
            ]);
        }

        return [
            'ok' => $plan['gate']['passed'],
            'dry_run' => true,
            'gate' => $plan['gate'],
            'validation' => $report['summary'],
            'sources' => $report['sources'],
            'scheduled' => ['lines' => count($plan['orders']), 'income' => count($plan['income'])],
            'promoted' => null,
            'error' => $plan['gate']['passed'] ? null : $plan['gate']['reason'],
        ];
    }

    /**
     * Promote validated staging data into production.
     *
     * Guards enforced before any production write:
     * - Stale staging (from a failed/partial sync) blocks promotion.
     * - Unchanged staging (same fingerprint as the last promotion) is skipped
     *   as a no-op so nothing is duplicated.
     * - Validation mismatches block promotion with zero writes.
     *
     * @return array<string, mixed>
     */
    public function promote(ShopeeApiConnection $connection, bool $dryRun = false): array
    {
        if ($dryRun) {
            return $this->preview($connection);
        }

        if ($connection->isStagingStale()) {
            $this->audit($connection, 'shopee_api.promotion.blocked', [
                'dry_run' => false,
                'gate' => 'blocked',
                'reason' => 'Staged data is stale from a failed/partial sync. Re-sync before promoting.',
            ]);

            return [
                'ok' => false,
                'dry_run' => false,
                'gate' => $this->gateFailure('Staged data is stale from a failed/partial sync. Re-sync before promoting.'),
                'validation' => $this->validator->report($connection)['summary'],
                'promoted' => null,
                'error' => 'Promotion blocked: staged data is stale from a failed/partial sync. Re-sync before promoting.',
            ];
        }

        if ($connection->isPromotionCurrent()) {
            $this->audit($connection, 'shopee_api.promotion.skipped', [
                'dry_run' => false,
                'gate' => 'passed',
                'reason' => 'Staged data is unchanged since the last successful promotion; nothing to promote.',
            ]);

            return [
                'ok' => true,
                'dry_run' => false,
                'gate' => $this->gateFailure('Unchanged staging; nothing to promote.'),
                'validation' => $this->validator->report($connection)['summary'],
                'promoted' => null,
                'skipped' => true,
                'error' => null,
            ];
        }

        $report = $this->validator->report($connection);
        $plan = $this->plan($connection, $report);

        if (! $plan['gate']['passed']) {
            $this->audit($connection, 'shopee_api.promotion.blocked', [
                'dry_run' => false,
                'gate' => 'blocked',
                'reason' => $plan['gate']['reason'],
                'validation' => $report['summary'],
            ]);

            return [
                'ok' => false,
                'dry_run' => false,
                'gate' => $plan['gate'],
                'validation' => $report['summary'],
                'promoted' => null,
                'error' => $plan['gate']['reason'],
            ];
        }

        try {
            $result = DB::transaction(function () use ($connection, $plan): array {
                $orderLines = $this->orderImporter->persist($plan['orders'], $connection->user_id);
                $incomeRows = $plan['income'] === []
                    ? 0
                    : $this->incomeImporter->persist($plan['income'], $connection->user_id);

                $audit = $this->audit($connection, 'shopee_api.promotion', [
                    'dry_run' => false,
                    'gate' => 'passed',
                    'validation' => $plan['summary'],
                    'promoted' => ['lines' => $orderLines, 'income' => $incomeRows],
                ]);

                $connection->markPromoted();

                return ['lines' => $orderLines, 'income' => $incomeRows, 'audit_id' => $audit->id];
            });

            return [
                'ok' => true,
                'dry_run' => false,
                'gate' => $plan['gate'],
                'validation' => $plan['summary'],
                'promoted' => ['lines' => $result['lines'], 'income' => $result['income']],
                'audit_id' => $result['audit_id'],
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $this->audit($connection, 'shopee_api.promotion.failed', [
                'dry_run' => false,
                'gate' => 'passed',
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'dry_run' => false,
                'gate' => $plan['gate'],
                'validation' => $plan['summary'],
                'promoted' => null,
                'error' => 'Promotion failed: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gateFailure(string $reason): array
    {
        return [
            'passed' => false,
            'mismatched' => 0,
            'scheduled_lines' => 0,
            'scheduled_income' => 0,
            'reason' => $reason,
        ];
    }

    /**
     * Validate the report and derive the promotion plan (payload rows) when the
     * gate passes. No writes happen here.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function plan(ShopeeApiConnection $connection, array $report): array
    {
        $gate = $this->gate($report);
        $summary = $report['summary'];

        return [
            'gate' => $gate,
            'summary' => $summary,
            'orders' => $gate['passed'] ? $this->planOrders($connection) : [],
            'income' => $gate['passed'] ? $this->planIncome($connection) : [],
        ];
    }

    /**
     * Only API rows that are marked matched / missing_in_excel are promotable.
     * Missing-in-API rows have no API counterpart and are never written.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function gate(array $report): array
    {
        $mismatched = 0;
        $scheduledLines = 0;
        $scheduledIncome = 0;

        foreach (['orders', 'lines', 'income'] as $view) {
            $summary = $report['summary'][$view] ?? [];
            $mismatched += (int) ($summary['mismatched'] ?? 0);

            if ($view === 'income') {
                $scheduledIncome += (int) ($summary['matched'] ?? 0) + (int) ($summary['missing_in_excel'] ?? 0);
            } elseif ($view === 'lines') {
                $scheduledLines += (int) ($summary['matched'] ?? 0) + (int) ($summary['missing_in_excel'] ?? 0);
            }
        }

        $reason = null;

        if ($mismatched > 0) {
            $reason = 'Validation failed: '.$mismatched.' mismatched row(s) block promotion.';
        } elseif ($scheduledLines === 0 && $scheduledIncome === 0) {
            $reason = 'Nothing to promote: no validated staged orders or income.';
        } elseif ($scheduledLines === 0) {
            $reason = 'No staged order lines available. Sync escrow details first.';
        }

        return [
            'passed' => $reason === null,
            'mismatched' => $mismatched,
            'scheduled_lines' => $scheduledLines,
            'scheduled_income' => $scheduledIncome,
            'reason' => $reason,
        ];
    }

    /**
     * Build marketplace_orders payload rows from staged escrow items enriched
     * with the staged order headers. line_identity and item_index follow the
     * exact algorithms the Excel importer uses (ReportLineIdentity + crc32).
     *
     * @return array<int, array<string, mixed>>
     */
    private function planOrders(ShopeeApiConnection $connection): array
    {
        $rows = [];
        $headerIndex = collect($this->normalizer->normalizeOrderHeaders($connection->staging_orders ?? []))
            ->keyBy('order_number')
            ->all();

        foreach (($connection->staging_escrow ?? []) as $entry) {
            $orderSn = (string) data_get($entry, 'order_sn', '');
            $orderIncome = (array) data_get($entry, 'response.order_income', []);

            if ($orderSn === '' || $orderIncome === []) {
                continue;
            }

            $header = (array) ($headerIndex[$orderSn] ?? []);
            $lines = $this->normalizer->normalizeOrderLines($orderIncome);
            $rawItems = is_array(data_get($orderIncome, 'items')) ? data_get($orderIncome, 'items') : [];

            foreach ($lines as $index => $line) {
                $rawItem = is_array($rawItems[$index] ?? null) ? $rawItems[$index] : [];
                $rows[] = $this->orderRow($connection->user_id, $orderSn, $line, $header, $rawItem);
            }
        }

        return $rows;
    }

    private function orderRow(int $userId, string $orderSn, array $line, array $header, array $rawItem): array
    {
        $productKey = hash('sha256', mb_strtolower(trim((string) ($line['product_name'] ?? ''))));
        $variationName = $line['variation_name'];
        $variationKey = $variationName === null ? null : hash('sha256', mb_strtolower(trim($variationName)));
        $discountedPrice = $line['discounted_price'] === null ? null : (float) $line['discounted_price'];
        $quantity = $line['quantity'] === null ? null : (int) $line['quantity'];
        $returnedQuantity = max(0, (int) ($rawItem['quantity_returned'] ?? 0));

        return [
            'user_id' => $userId,
            'order_number' => $orderSn,
            'item_index' => $this->itemIndex($orderSn, $line['product_name'] ?? '', $discountedPrice === null || $quantity === null ? null : $discountedPrice * max(0, $quantity - $returnedQuantity)),
            'line_identity' => ReportLineIdentity::make($orderSn, $productKey, $variationKey, $discountedPrice, $quantity),
            'order_status' => $header['order_status'] ?? null,
            'shipping_option' => $header['shipping_option'] ?? null,
            'tracking_number' => $header['tracking_number'] ?? null,
            'payment_method' => $header['payment_method'] ?? null,
            'buyer_username' => $header['buyer_username'] ?? null,
            'order_created_at' => $header['order_created_at'] ?? null,
            'payment_at' => $header['payment_at'] ?? null,
            'shipped_at' => $header['shipped_at'] ?? null,
            'parent_sku' => $line['parent_sku'],
            'product_name' => $line['product_name'],
            'product_key' => $productKey,
            'sku_reference' => $line['sku_reference'],
            'variation_name' => $variationName,
            'variation_key' => $variationKey,
            'original_price' => $line['original_price'],
            'discounted_price' => $discountedPrice,
            'unit_price' => $discountedPrice,
            'quantity' => $quantity,
            'returned_quantity' => $returnedQuantity,
            'raw_data' => json_encode([
                'source' => 'shopee_api',
                'payload' => $line + ['quantity_returned' => $returnedQuantity],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Build marketplace_income payload rows from staged income detail rows.
     *
     * The Shopee income endpoint carries no product/variation/quantity fields,
     * so item-level fields are left NULL and the identity is derived only from
     * genuinely available payload fields (order number, amount, release moment).
     * Such an identity can never equal a marketplace_orders line_identity, so
     * API income reconciles as Orphan/Unmatched by design instead of through a
     * fabricated match key, while staying unique per distinct amount/date to
     * respect the income_user_line_identity_unique key.
     *
     * @return array<int, array<string, mixed>>
     */
    private function planIncome(ShopeeApiConnection $connection): array
    {
        $rows = [];

        foreach ($this->normalizer->normalizeIncomeRows($connection->staging_income ?? []) as $row) {
            $orderSn = (string) ($row['order_number'] ?? '');
            if ($orderSn === '') {
                continue;
            }

            $totalIncome = $row['total_income'] === null ? null : (float) $row['total_income'];
            $rows[] = [
                'user_id' => $connection->user_id,
                'order_number' => $orderSn,
                'item_index' => null,
                'line_identity' => $this->incomeIdentity($orderSn, $row),
                'row_type' => $row['row_type'] ?? null,
                'product_key' => null,
                'variation_key' => null,
                'unit_price' => null,
                'quantity' => null,
                'total_income' => $totalIncome,
                'fund_released_at' => $row['income_released_at'] ?? null,
                'buyer_payment_method' => $row['payment_method'] ?? null,
                'raw_data' => json_encode([
                    'source' => 'shopee_api',
                    'payload' => $row,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    /**
     * Stable income identity built from the income payload's real fields only.
     * Distinct amounts or release moments yield distinct identities; identical
     * rows intentionally collide so duplicates surface as constraint failures.
     */
    private function incomeIdentity(string $orderNumber, array $row): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower(trim($orderNumber)),
            $row['total_income'] === null ? '' : number_format((float) $row['total_income'], 2, '.', ''),
            $row['income_released_at'] ?? '',
        ]));
    }

    /**
     * Deterministic unsigned item index shared with the Excel importers.
     */
    private function itemIndex(string $orderNumber, mixed $productName, ?float $lineAmount): int
    {
        $key = $orderNumber.'|'.mb_strtolower(trim((string) $productName)).'|'.($lineAmount === null ? '' : number_format($lineAmount, 2, '.', ''));

        return (int) sprintf('%u', crc32($key));
    }

    private function audit(ShopeeApiConnection $connection, string $action, array $metadata): AccountAuditLog
    {
        return AccountAuditLog::create([
            'user_id' => $connection->user_id,
            'actor_id' => $connection->user_id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
