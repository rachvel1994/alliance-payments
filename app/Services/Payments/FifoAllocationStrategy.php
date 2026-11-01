<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Contracts\AllocationStrategy;

final class FifoAllocationStrategy implements AllocationStrategy
{
    public function allocate(array $payments, array $installments): array
    {
        $payments = collect($payments)
            ->filter(fn (array $payment): bool => (int) $payment['amount_minor'] > 0)
            ->sortBy(['paid_at', 'id'])
            ->values()->all();
        $installments = collect($installments)
            ->filter(fn (array $installment): bool => (int) $installment['amount_minor'] > 0)
            ->sortBy(['due_date', 'id'])
            ->values()->all();

        $allocations = [];
        $paymentIndex = 0;
        $installmentIndex = 0;

        while (isset($payments[$paymentIndex], $installments[$installmentIndex])) {
            $payment = $payments[$paymentIndex];
            $installment = $installments[$installmentIndex];
            $remaining = (int) $payment['amount_minor'];
            $due = (int) $installment['amount_minor'];
            $amount = min($remaining, $due);

            $allocations[] = [
                'payment_id' => (int) $payment['id'],
                'installment_id' => (int) $installment['id'],
                'amount_minor' => $amount,
            ];

            $payments[$paymentIndex]['amount_minor'] = $remaining - $amount;
            $installments[$installmentIndex]['amount_minor'] = $due - $amount;

            if ($amount === $remaining) {
                $paymentIndex++;
            }

            if ($amount === $due) {
                $installmentIndex++;
            }
        }

        return $allocations;
    }
}
