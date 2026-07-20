<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ApiErrorEnvelopeTest extends TestCase
{
    public function test_checkout_validation_errors_use_the_json_envelope(): void
    {
        $this->postJson('/checkout-api/orders', [])
            ->assertUnprocessable()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta', null)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['message', 'details']]);
    }

    public function test_unknown_api_routes_use_the_json_envelope(): void
    {
        $this->getJson('/api/v1/not-a-route')
            ->assertNotFound()
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta', null)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
