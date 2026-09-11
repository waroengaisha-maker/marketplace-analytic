<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProductHpp extends Model
{
    protected $table = 'master_product_hpp';

    protected $fillable = [
        'master_product_id',
        'master_unit_id',
        'hpp_amount',
        'hpp_per_base_unit',
        'effective_from',
        'effective_to',
        'source_type',
        'source_reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'master_product_id' => 'integer',
        'master_unit_id' => 'integer',
        'hpp_amount' => 'decimal:2',
        'hpp_per_base_unit' => 'decimal:6',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'created_by' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MasterProduct::class, 'master_product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(MasterProductUnit::class, 'master_unit_id');
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('master_product_id', $productId);
    }

    public function scopeForUnit($query, int $unitId)
    {
        return $query->where('master_unit_id', $unitId);
    }

    public function scopeEffectiveAt($query, CarbonInterface $date)
    {
        return $query
            ->where('effective_from', '<=', $date)
            ->where(function ($subQuery) use ($date): void {
                $subQuery->whereNull('effective_to')->orWhere('effective_to', '>', $date);
            });
    }
}
