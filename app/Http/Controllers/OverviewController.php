<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceRequest;
use App\Queries\OverviewQuery;
use Inertia\Inertia;
use Inertia\Response;

final class OverviewController extends Controller
{
    public function __invoke(WorkspaceRequest $request, OverviewQuery $query): Response
    {
        return Inertia::render('Overview', $query->handle(
            $request->validated(),
            $request->user()->getAllPermissions()->pluck('name')->all(),
        ));
    }
}
