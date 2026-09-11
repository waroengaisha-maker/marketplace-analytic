<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterProduct extends Model
{
    protected $fillable = [
        'user_id',
        'template_item_code',
        'template_name',
        'normalized_name',
        'base_unit_id',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'base_unit_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(MasterProductUnit::class, 'base_unit_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(MasterProductUnit::class);
    }

    public function hppRecords(): HasMany
    {
        return $this->hasMany(MasterProductHpp::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ShopeeProductMapping::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderCostAllocation::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
