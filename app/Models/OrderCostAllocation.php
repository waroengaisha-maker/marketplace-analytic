<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCostAllocation extends Model
{
    protected $table = 'order_cost_allocations';

    protected $fillable = [
        'user_id',
        'order_line_identity',
        'master_product_id',
        'master_unit_id',
        'effective_hpp_record_id',
        'hpp_per_base_unit',
        'quantity_base_unit',
        'total_hpp',
        'cost_status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'master_product_id' => 'integer',
        'master_unit_id' => 'integer',
        'effective_hpp_record_id' => 'integer',
        'hpp_per_base_unit' => 'decimal:6',
        'quantity_base_unit' => 'decimal:6',
        'total_hpp' => 'decimal:2',
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

    public function hppRecord(): BelongsTo
    {
        return $this->belongsTo(MasterProductHpp::class, 'effective_hpp_record_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
