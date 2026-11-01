<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Imports\StartPaymentImport;
use App\Http\Requests\StorePaymentImportRequest;
use Illuminate\Http\RedirectResponse;

final class PaymentImportController extends Controller
{
    public function store(StorePaymentImportRequest $request, StartPaymentImport $action): RedirectResponse
    {
        $action->handle($request->file('file'), $request->user()->id);

        return back()->with('success', 'ფაილი დაემატა დამუშავების რიგს.');
    }
}
