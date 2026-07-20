<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Domain\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePublicPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^[1-9]\d{0,6}(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
        ];
    }
}
