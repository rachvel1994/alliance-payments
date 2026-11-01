<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'raw_data' => 'array',
            'row_number' => 'integer',
            'status' => ImportRowStatus::class,
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
