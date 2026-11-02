<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Projects\CreateProject;
use App\Http\Requests\StoreProjectRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    public function store(StoreProjectRequest $request, CreateProject $create): RedirectResponse
    {
        $project = $create->handle($request->validated());

        return ($request->user()->can('apartments.create')
            ? to_route('apartments.create', ['project_id' => $project->id])
            : to_route('projects.create'))
            ->with('success', 'პროექტი დაემატა.');
    }
}
