<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Contracts\CreateContract;
use App\Http\Requests\StoreContractRequest;
use App\Queries\ContractFormOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ContractController extends Controller
{
    public function create(ContractFormOptions $options): Response
    {
        return Inertia::render('Contracts/Create', $options->handle());
    }

    public function store(StoreContractRequest $request, CreateContract $create): RedirectResponse
    {
        $contract = $create->handle($request->contractData(), $request->user()->id);

        return to_route('contracts.show', $contract)->with('success', 'ხელშეკრულება და გადახდის გრაფიკი შეიქმნა.');
    }
}
