<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Apartments\SaveApartment;
use App\Http\Requests\SaveApartmentRequest;
use App\Http\Requests\WorkspaceRequest;
use App\Models\Apartment;
use App\Models\Project;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ApartmentController extends Controller
{
    public function create(WorkspaceRequest $request): Response
    {
        return $this->form(projectId: $request->filled('project_id') ? (int) $request->validated('project_id') : null);
    }

    public function edit(Apartment $apartment): Response
    {
        return $this->form($apartment);
    }

    private function form(?Apartment $apartment = null, ?int $projectId = null): Response
    {
        $apartment?->loadExists('contract');

        return Inertia::render('Apartments/Form', [
            'apartment' => $apartment === null ? null : [
                ...$apartment->only('id', 'project_id', 'number', 'floor', 'area', 'status', 'contract_exists'),
                'price' => Money::formatMinor($apartment->price_minor),
            ],
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'city']),
            'selectedProjectId' => $projectId,
        ]);
    }

    public function store(SaveApartmentRequest $request, SaveApartment $save): RedirectResponse
    {
        $save->handle($request->apartmentAttributes());

        return to_route('apartments.index')->with('success', 'თავისუფალი ბინა დაემატა.');
    }

    public function update(SaveApartmentRequest $request, Apartment $apartment, SaveApartment $save): RedirectResponse
    {
        $save->handle($request->apartmentAttributes(), $apartment);

        return to_route('apartments.index')->with('success', 'ბინის მონაცემები განახლდა.');
    }
}
