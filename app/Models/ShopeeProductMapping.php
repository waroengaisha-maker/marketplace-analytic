<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeProductMapping extends Model
{
    protected $table = 'shopee_product_mapping';

    protected $fillable = [
        'user_id',
        'master_product_id',
        'master_unit_id',
        'shopee_product_id',
        'shopee_variant_id',
        'shopee_product_name',
        'shopee_variant_name',
        'normalized_shopee_name',
        'match_method',
        'match_confidence',
        'is_active',
        'ambiguous',
        'manual_override_by',
        'manual_override_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'master_product_id' => 'integer',
        'master_unit_id' => 'integer',
        'match_confidence' => 'decimal:2',
        'is_active' => 'boolean',
        'ambiguous' => 'boolean',
        'manual_override_by' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MasterProductUnit::class, 'master_unit_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
