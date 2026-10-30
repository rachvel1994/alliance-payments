<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationRun extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime'
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'allocation_run_id');
    }
}
