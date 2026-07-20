<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GatewayCallbackRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_easypay_callback_accepts_get_requests(): void
    {
        $this->get('/payments/callbacks/easypay/v1')
            ->assertBadRequest()
            ->assertSeeText('fail');
    }

    public function test_easypay_callback_accepts_post_requests(): void
    {
        $this->post('/payments/callbacks/easypay/v1')
            ->assertBadRequest()
            ->assertSeeText('fail');
    }
}
