<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'personal_number',
        'phone',
        'current_allocation_run_id',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function allocationRuns(): HasMany
    {
        return $this->hasMany(AllocationRun::class);
    }

    public function currentAllocationRun(): BelongsTo
    {
        return $this->belongsTo(
            AllocationRun::class,
            'current_allocation_run_id'
        );
    }
}
