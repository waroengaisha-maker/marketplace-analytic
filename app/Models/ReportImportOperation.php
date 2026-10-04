<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportImportOperation extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'orders',
        'income',
        'order_path',
        'income_path',
        'error_message',
    ];

    protected $casts = [
        'orders' => 'integer',
        'income' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
