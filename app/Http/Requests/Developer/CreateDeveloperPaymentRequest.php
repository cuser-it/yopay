<?php

declare(strict_types=1);

namespace App\Http\Requests\Developer;

use App\Domain\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateDeveloperPaymentRequest extends FormRequest
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
            'external_order_no' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::in(['CNY'])],
            'subject' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_type' => ['nullable', Rule::enum(PaymentMethod::class)],
            'notify_url' => ['nullable', 'url:https', 'max:2048'],
            'return_url' => ['nullable', 'url:https', 'max:2048'],
            'metadata' => ['nullable', 'array', 'max:32'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
        ];
    }
}
