<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class CheckoutPageTest extends TestCase
{
    public function test_checkout_result_exposes_public_restart_and_merchant_return_actions(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('x-show="order.can_start_new_payment"', false)
            ->assertSee('返回商户网站')
            ->assertSee('重新发起付款')
            ->assertSee('class="amount-input__control"', false)
            ->assertDontSee('class="input amount-input__control"', false);
    }
}
