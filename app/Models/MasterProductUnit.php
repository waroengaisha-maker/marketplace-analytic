<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterProductUnit extends Model
{
    protected $fillable = [
        'master_product_id',
        'unit_code',
        'unit_name',
        'conversion_to_base',
        'is_active',
    ];

    protected $casts = [
        'master_product_id' => 'integer',
        'conversion_to_base' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function hppRecords(): HasMany
    {
        return $this->hasMany(MasterProductHpp::class, 'master_unit_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ShopeeProductMapping::class, 'master_unit_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderCostAllocation::class, 'master_unit_id');
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('master_product_id', $productId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
