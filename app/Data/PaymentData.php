<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class PaymentData
{
    public function __construct(
        public string  $paidAt,
        public int     $amountMinor,
        public ?string $personalNumber,
        public string  $payerName,
        public ?string $purpose,
        public string  $bankReference,
    )
    {
    }

    public static function fromArray(array $input): self
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $input[$key] = $value === '' ? null : $value;
            }
        }

        $data = self::validate($input);

        try {
            $minor = Money::toMinor($data['amount']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        return new self(
            paidAt: $data['paid_at'],
            amountMinor: $minor,
            personalNumber: $data['personal_number'] ?? null,
            payerName: $data['payer_name'],
            purpose: $data['purpose'] ?? null,
            bankReference: $data['bank_reference'],
        );
    }

    public function attributes(): array
    {
        return [
            'paid_at' => $this->paidAt,
            'amount_minor' => $this->amountMinor,
            'personal_number' => $this->personalNumber,
            'payer_name' => $this->payerName,
            'purpose' => $this->purpose,
        ];
    }

    /**
     * @param array $input
     * @return array
     * @throws ValidationException
     */
    private static function validate(array $input): array
    {
        return Validator::make($input, [
            'paid_at' => ['required', 'date_format:Y-m-d'],
            'personal_number' => ['nullable', 'string', 'regex:/\A\d{11}\z/'],
            'payer_name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'bank_reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ], [
            'required' => 'ველი „:attribute“ აუცილებელია.',
            'date_format' => ':attribute უნდა შეესაბამებოდეს ფორმატს :format.',
            'string' => ':attribute უნდა იყოს ტექსტი.',
            'max' => ':attribute არ უნდა აღემატებოდეს :max სიმბოლოს.',
            'personal_number.regex' => 'პირადი ნომერი უნდა შედგებოდეს ზუსტად 11 ციფრისგან.',
            'amount.numeric' => 'თანხა უნდა იყოს რიცხვი.',
            'amount.gt' => 'თანხა ნულზე მეტი უნდა იყოს.',
        ], [
            'paid_at' => 'გადახდის თარიღი',
            'personal_number' => 'პირადი ნომერი',
            'payer_name' => 'გადამხდელის სახელი',
            'purpose' => 'დანიშნულება',
            'bank_reference' => 'საბანკო ნომერი',
            'amount' => 'თანხა',
        ])->validate();
    }
}
