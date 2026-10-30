<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    /** @use HasFactory<\Database\Factories\ContractFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'apartment_id',
        'contract_date',
        'price_minor',
    ];

    protected function casts(): array
    {
        return ['contract_date' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }
}
