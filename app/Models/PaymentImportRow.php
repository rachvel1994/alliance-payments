<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentImportRow extends Model
{
    protected $fillable = [
        'payment_import_id',
        'payment_id',
        'row_number',
        'raw_data',
        'status',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array'
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PaymentImport::class, 'payment_import_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
