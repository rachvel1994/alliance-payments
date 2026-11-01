<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClientIndexRequest;
use App\Queries\ListClients;
use Inertia\Inertia;
use Inertia\Response;

final class ClientController extends Controller
{
    public function __invoke(ClientIndexRequest $request, ListClients $query): Response
    {
        return Inertia::render('Clients/Index', $query->handle($request->validated()));
    }
}
