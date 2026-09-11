<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateItemRow extends Model
{
    protected $table = 'template_item_rows';

    protected $fillable = [
        'user_id',
        'kode_item',
        'barcode',
        'sku',
        'nama_item',
        'jenis',
        'merek',
        'rak',
        'conversion_to_base',
        'tipe_item',
        'satuan',
        'hpp_amount',
        'harga_jual',
        'keterangan',
        'poin',
        'komisi_sales',
        'source_file',
        'is_active',
    ];

    protected $casts = [
        'conversion_to_base' => 'decimal:6',
        'hpp_amount' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
