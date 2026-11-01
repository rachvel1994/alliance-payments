<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\ReallocateClientPayments;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ReallocationController extends Controller
{
    public function __invoke(
        Request $request,
        Client $client,
        ReallocateClientPayments $action,
    ): RedirectResponse {
        $action->handle($client->id, $request->user()->id);

        return back()->with('success', 'გადახდების განაწილება განახლდა.');
    }
}
