<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Apartment;
use App\Models\Client;
use App\Support\Money;

final class ContractFormOptions
{
    public function handle(): array
    {
        return [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'personal_number']),
            'apartments' => Apartment::query()->where('status', 'free')->whereDoesntHave('contract')
                ->with('project:id,name,city')->orderBy('project_id')->orderBy('number')
                ->get(['id', 'project_id', 'number', 'price_minor', 'area', 'floor'])
                ->map(fn (Apartment $apartment): array => [
                    'id' => $apartment->id, 'label' => $apartment->project->name.' — '.$apartment->number,
                    'price' => Money::formatMinor($apartment->price_minor),
                    'description' => $apartment->project->city.' · '.$apartment->area.' მ² · სართული '.$apartment->floor,
                ]),
            'today' => now()->toDateString(),
        ];
    }
}
