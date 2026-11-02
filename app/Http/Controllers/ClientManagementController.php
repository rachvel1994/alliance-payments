<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Clients\SaveClient;
use App\Http\Requests\SaveClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ClientManagementController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Clients/Form', ['client' => null]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Form', ['client' => $client->only('id', 'name', 'personal_number', 'phone')]);
    }

    public function store(SaveClientRequest $request, SaveClient $save): RedirectResponse
    {
        $save->handle($request->validated(), userId: $request->user()->id);

        return to_route('clients.index')->with('success', 'კლიენტი დაემატა.');
    }

    public function update(SaveClientRequest $request, Client $client, SaveClient $save): RedirectResponse
    {
        $save->handle($request->validated(), $client, $request->user()->id);

        return to_route('clients.index')->with('success', 'კლიენტის მონაცემები განახლდა.');
    }
}
