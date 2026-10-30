<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    /** @use HasFactory<\Database\Factories\InstallmentFactory> */
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'sequence',
        'due_date',
        'amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date'
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
