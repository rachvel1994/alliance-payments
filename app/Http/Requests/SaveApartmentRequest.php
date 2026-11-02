<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class SaveApartmentRequest extends BusinessRequest
{
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'number' => ['required', 'string', 'max:255', Rule::unique('apartments')
                ->where('project_id', $this->integer('project_id'))->ignore($this->route('apartment'))],
            'floor' => ['required', 'integer', 'between:0,2147483647'],
            'area' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'price' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function apartmentAttributes(): array
    {
        return [...$this->safe()->except('price'), 'price_minor' => $this->amountMinor('price')];
    }

    public function attributes(): array
    {
        return [
            'project_id' => 'პროექტი', 'number' => 'ბინის ნომერი', 'floor' => 'სართული',
            'area' => 'ფართი', 'price' => 'ბინის ფასი',
        ];
    }
}
