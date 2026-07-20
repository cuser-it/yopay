<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_checkout_accepts_one_cent(): void
    {
        $this->postJson('/checkout-api/orders', [
            'amount' => '0.01',
            'payment_method' => 'alipay',
        ], [
            'Idempotency-Key' => 'one-cent-order-key',
        ])
            ->assertCreated()
            ->assertJsonPath('data.expected_amount_cents', 1);
    }

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
