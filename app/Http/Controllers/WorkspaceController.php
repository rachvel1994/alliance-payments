<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceRequest;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentImport;
use App\Queries\WorkspaceQuery;
use Inertia\Inertia;
use Inertia\Response;

final class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceQuery $query) {}

    public function apartments(WorkspaceRequest $request): Response
    {
        return Inertia::render('Workspace', $this->query->apartments($request->validated()));
    }

    public function contracts(WorkspaceRequest $request): Response
    {
        return Inertia::render('Workspace', $this->query->contracts($request->validated()));
    }

    public function contract(WorkspaceRequest $request, Contract $contract): Response
    {
        return Inertia::render('Workspace', $this->query->contract($contract, $request->validated()));
    }

    public function payments(WorkspaceRequest $request): Response
    {
        return Inertia::render('Workspace', $this->query->payments($request->validated()));
    }

    public function payment(WorkspaceRequest $request, Payment $payment): Response
    {
        return Inertia::render('Workspace', $this->query->payment($payment, $request->validated()));
    }

    public function overdue(WorkspaceRequest $request): Response
    {
        return Inertia::render('Workspace', $this->query->overdue($request->validated()));
    }

    public function imports(WorkspaceRequest $request): Response
    {
        return Inertia::render('Workspace', $this->query->imports($request->validated()));
    }

    public function import(WorkspaceRequest $request, PaymentImport $import): Response
    {
        return Inertia::render('Workspace', $this->query->import($import, $request->validated()));
    }
}
