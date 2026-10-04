<?php

namespace App\Services;

use App\Models\AccountAuditLog;

class ShopeeSyncAuditService
{
    /**
     * Record a sync outcome without storing credentials or raw API payloads.
     *
     * @param  array<string, mixed>  $result
     */
    public function record(int $userId, string $operation, array $result): void
    {
        $metadata = [
            'operation' => $operation,
            'ok' => (bool) ($result['ok'] ?? false),
            'error' => config('app.debug')
                ? ($result['error'] ?? null)
                : (($result['ok'] ?? false) ? null : 'Shopee API request failed. Please try again later.'),
            'rate_limited' => (bool) ($result['rate_limited'] ?? false),
        ];

        foreach (['order_count', 'row_count', 'escrow_count', 'staged_total', 'pages', 'total_orders', 'cleared'] as $numeric) {
            if (array_key_exists($numeric, $result)) {
                $metadata[$numeric] = $result[$numeric];
            }
        }

        if (array_key_exists('cursor', $result)) {
            $metadata['cursor'] = $result['cursor'];
        }

        AccountAuditLog::query()->create([
            'user_id' => $userId,
            'actor_id' => $userId,
            'action' => 'shopee_api.sync.'.$operation,
            'metadata' => $metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
