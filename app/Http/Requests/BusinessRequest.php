<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

abstract class BusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function amountMinor(string $field): int
    {
        try {
            return Money::toMinor($this->validated($field));
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages([$field => $error->getMessage()]);
        }
    }

    public function messages(): array
    {
        return [
            'required' => 'ველი „:attribute“ აუცილებელია.',
            'string' => ':attribute უნდა იყოს ტექსტი.',
            'integer' => ':attribute უნდა იყოს მთელი რიცხვი.',
            'numeric' => ':attribute უნდა იყოს რიცხვი.',
            'gt' => ':attribute უნდა იყოს :value-ზე მეტი.',
            'min' => ':attribute უნდა იყოს მინიმუმ :min.',
            'max' => ':attribute არ უნდა აღემატებოდეს :max-ს.',
            'between' => ':attribute უნდა იყოს :min-დან :max-მდე.',
            'exists' => 'შერჩეული :attribute ვერ მოიძებნა.',
            'unique' => 'ასეთი :attribute უკვე არსებობს.',
            'date_format' => ':attribute უნდა იყოს ფორმატში :format.',
            'after_or_equal' => ':attribute არ უნდა იყოს ხელშეკრულების თარიღზე ადრე.',
            'decimal' => ':attribute უნდა შეიცავდეს მაქსიმუმ 2 ათწილადს.',
            'personal_number.regex' => 'პირადი ნომერი უნდა შედგებოდეს ზუსტად 11 ციფრისგან.',
        ];
    }
}
