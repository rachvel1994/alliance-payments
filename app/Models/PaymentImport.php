<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentImport extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'file_hash',
        'status',
        'total_rows',
        'imported_rows',
        'duplicate_rows',
        'rejected_rows',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime'
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PaymentImportRow::class);
    }
}
