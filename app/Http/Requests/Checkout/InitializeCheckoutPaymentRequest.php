<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Domain\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InitializeCheckoutPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['payment_method' => ['required', Rule::enum(PaymentMethod::class)]];
    }
}
