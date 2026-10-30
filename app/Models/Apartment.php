<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apartment extends Model
{
    /** @use HasFactory<\Database\Factories\ApartmentFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'number',
        'floor',
        'area',
        'price_minor',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2'
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }
}
