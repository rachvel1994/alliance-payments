<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\NewContractData;

final class StoreContractRequest extends BusinessRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'apartment_id' => ['required', 'integer', 'exists:apartments,id'],
            'contract_date' => ['required', 'date_format:Y-m-d'],
            'price' => ['required', 'numeric', 'gt:0'],
            'first_due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:contract_date'],
            'installments_count' => ['required', 'integer', 'between:1,360'],
        ];
    }

    public function contractData(): NewContractData
    {
        $data = $this->validated();

        return new NewContractData(
            (int) $data['client_id'], (int) $data['apartment_id'], $data['contract_date'],
            $this->amountMinor('price'), $data['first_due_date'], (int) $data['installments_count'],
        );
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'კლიენტი', 'apartment_id' => 'ბინა',
            'contract_date' => 'ხელშეკრულების თარიღი', 'price' => 'ხელშეკრულების ფასი',
            'first_due_date' => 'პირველი შენატანის თარიღი', 'installments_count' => 'შენატანების რაოდენობა',
        ];
    }
}
