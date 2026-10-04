<?php

namespace App\Jobs;

use App\Models\ShopeeApiConnection;
use App\Models\ShopeeSyncOperation;
use App\Services\ShopeeSyncAuditService;
use App\Services\ShopeeSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ShopeeSyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $operationId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->operationId;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(
        ShopeeSyncService $sync,
        ShopeeSyncAuditService $audit,
    ): void {
        $operation = ShopeeSyncOperation::query()->findOrFail($this->operationId);

        if (in_array($operation->status, ['completed', 'failed'], true)) {
            return;
        }

        $lock = Cache::lock(
            'shopee-api:sync:user:'.$operation->user_id,
            (int) config('shopee-api.sync.lock_seconds', 1800)
        );

        if (! $lock->get()) {
            $this->release(10);

            return;
        }

        try {
            $operation->update([
                'status' => 'processing',
                'started_at' => $operation->started_at ?? now(),
                'error_message' => null,
            ]);

            $connection = ShopeeApiConnection::query()
                ->whereKey($operation->connection_id)
                ->where('user_id', $operation->user_id)
                ->firstOrFail();

            $options = $operation->options ?? [];

            $result = match ($operation->operation) {
                'orders' => $sync->syncSampleOrders($connection, $options),
                'income' => $sync->syncSampleIncome($connection, $options),
                'escrow' => $sync->syncSampleEscrow($connection, $options),
                default => throw new \LogicException('Unsupported Shopee sync operation.'),
            };

            $audit->record($operation->user_id, $operation->operation, $result);

            $operation->update([
                'status' => ($result['ok'] ?? false) ? 'completed' : 'failed',
                'result' => $this->summary($result),
                'error_message' => ($result['ok'] ?? false)
                    ? null
                    : 'Shopee sync gagal. Silakan coba lagi atau hubungi administrator.',
                'finished_at' => now(),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Persist only bounded operation metadata. Full normalized rows remain in
     * the connection staging payload and are not duplicated in the queue table.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function summary(array $result): array
    {
        $summary = [];

        foreach ([
            'ok', 'order_count', 'row_count', 'escrow_count', 'staged_total',
            'pages', 'total_orders', 'limit', 'page_size', 'max_pages',
            'cursor', 'more', 'capped', 'error', 'rate_limited',
        ] as $key) {
            if (array_key_exists($key, $result)) {
                $summary[$key] = $result[$key];
            }
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $summary['error_count'] = count($result['errors']);
        }

        return $summary;
    }

    public function failed(Throwable $exception): void
    {
        $operation = ShopeeSyncOperation::query()->find($this->operationId);

        if ($operation === null || $operation->status === 'completed') {
            return;
        }

        $operation->update([
            'status' => 'failed',
            'error_message' => 'Shopee sync gagal. Silakan coba lagi atau hubungi administrator.',
            'finished_at' => now(),
        ]);

        app(ShopeeSyncAuditService::class)->record(
            $operation->user_id,
            $operation->operation,
            [
                'ok' => false,
                'error' => 'Shopee sync failed after queue retries.',
                'rate_limited' => false,
            ],
        );

        report($exception);
    }
}
