<?php

declare(strict_types=1);

namespace App\Http\Requests\Developer;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDeveloperApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'allowed_notify_urls' => ['nullable', 'string', 'max:5000'],
            'allowed_return_urls' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
