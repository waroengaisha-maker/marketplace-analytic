<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeApiConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'environment',
        'region',
        'partner_id',
        'partner_key',
        'shop_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'shop_name',
        'connected_at',
        'last_sync_at',
        'last_sync_status',
        'last_sync_error',
        'last_sync_order_cursor',
        'last_staged_at',
        'last_promoted_at',
        'promoted_fingerprint',
        'staging_orders',
        'staging_income',
        'staging_escrow',
    ];

    protected $casts = [
        'partner_id' => 'encrypted',
        'partner_key' => 'encrypted',
        'shop_id' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_staged_at' => 'datetime',
        'last_promoted_at' => 'datetime',
        'staging_orders' => 'array',
        'staging_income' => 'array',
        'staging_escrow' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isConfigured(): bool
    {
        return filled($this->partner_id)
            && filled($this->partner_key)
            && filled($this->shop_id)
            && filled($this->access_token);
    }

    public function missing(): array
    {
        return array_values(array_filter(
            ['partner_id', 'partner_key', 'shop_id', 'access_token'],
            fn (string $key): bool => blank($this->{$key})
        ));
    }

    public function safeState(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'missing' => $this->missing(),
            'environment' => $this->environment,
            'region' => $this->region,
            'shop_name' => $this->shop_name,
            'connected' => $this->connected_at !== null,
            'access_token_expires_at' => $this->access_token_expires_at?->toDateTimeString(),
            'refresh_token_expires_at' => $this->refresh_token_expires_at?->toDateTimeString(),
            'last_sync_at' => $this->last_sync_at?->toDateTimeString(),
            'last_sync_status' => $this->last_sync_status,
            'last_sync_error' => $this->last_sync_error,
            'last_staged_at' => $this->last_staged_at?->toDateTimeString(),
            'last_promoted_at' => $this->last_promoted_at?->toDateTimeString(),
            'staging_order_count' => count($this->staging_orders ?? []),
            'staging_income_count' => count($this->staging_income ?? []),
            'staging_escrow_count' => count($this->staging_escrow ?? []),
            'staging_stale' => $this->isStagingStale(),
        ];
    }

    /**
     * Staging data is considered stale when it was written more than the given
     * threshold ago OR the last sync resulted in a failure/rate-limit. A later
     * successful sync overwrites last_sync_status, so a failure flag is always
     * authoritative until a fresh sync completes.
     */
    public function isStagingStale(?int $staleAfterMinutes = null): bool
    {
        $threshold = $staleAfterMinutes ?? (int) config('shopee-api.staging_stale_after_minutes', 1440);

        if ($this->last_staged_at === null) {
            return false;
        }

        if ($this->last_staged_at->diffInMinutes(now()) > $threshold) {
            return true;
        }

        return in_array($this->last_sync_status, ['error', 'rate_limited'], true);
    }

    /**
     * True when no staging data has changed since the last successful promotion,
     * meaning a re-promote would be a no-op. Used to prevent duplicate promotion.
     */
    public function isPromotionCurrent(): bool
    {
        return $this->promoted_fingerprint !== null
            && $this->promoted_fingerprint === $this->stagingFingerprint();
    }

    /**
     * Deterministic fingerprint of the current staged payloads. Two promotions
     * of byte-identical staging share the same fingerprint, so unchanged staging
     * can be skipped without re-running the importers (no duplicate promotion).
     */
    public function stagingFingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->staging_orders ?? [],
            $this->staging_income ?? [],
            $this->staging_escrow ?? [],
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Check whether there is any staged data ready for promotion.
     */
    public function hasStagedData(): bool
    {
        return ! empty($this->staging_orders ?? [])
            || ! empty($this->staging_income ?? [])
            || ! empty($this->staging_escrow ?? []);
    }

    /**
     * Wipe all staging data and reset sync tracking fields.
     * Called after successful promotion or explicit user-initiated clear.
     */
    public function clearStaging(): void
    {
        $this->staging_orders = null;
        $this->staging_income = null;
        $this->staging_escrow = null;
        $this->promoted_fingerprint = null;
        $this->last_staged_at = null;
        $this->last_sync_order_cursor = null;
        $this->last_sync_status = null;
        $this->last_sync_error = null;
        $this->save();
    }

    /**
     * Record that staging data has been updated (called by sync service).
     */
    public function markStaged(): void
    {
        $this->last_staged_at = now();
        $this->save();
    }

    /**
     * Record that staged data was successfully promoted (called by promotion service).
     */
    public function markPromoted(): void
    {
        $this->last_promoted_at = now();
        $this->promoted_fingerprint = $this->stagingFingerprint();
        $this->save();
    }
}
