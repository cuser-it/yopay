<?php

declare(strict_types=1);

namespace App\Http\Requests\Developer;

use App\Domain\Developer\Services\WebhookEndpointService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:https', 'max:2048'],
            'subscribed_events' => ['required', 'array', 'min:1', 'max:6'],
            'subscribed_events.*' => ['required', 'string', Rule::in(WebhookEndpointService::EVENTS)],
        ];
    }
}
