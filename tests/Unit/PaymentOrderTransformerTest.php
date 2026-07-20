<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Support\PaymentOrderTransformer;
use App\Models\PaymentOrder;
use Tests\TestCase;

final class PaymentOrderTransformerTest extends TestCase
{
    public function test_paid_public_checkout_can_start_a_new_payment(): void
    {
        $data = PaymentOrderTransformer::checkout($this->order(OrderSource::PublicCheckout, PaymentStatus::Paid));

        $this->assertTrue($data['can_start_new_payment']);
    }

    public function test_paid_developer_order_must_return_to_the_merchant_for_a_new_order(): void
    {
        $data = PaymentOrderTransformer::checkout($this->order(OrderSource::DeveloperApi, PaymentStatus::Paid));

        $this->assertFalse($data['can_start_new_payment']);
    }

    private function order(OrderSource $source, PaymentStatus $status): PaymentOrder
    {
        return (new PaymentOrder())->forceFill([
            'order_no' => 'PAY_TEST',
            'source' => $source->value,
            'status' => $status->value,
            'subject' => 'Test payment',
            'expected_amount_cents' => 1000,
            'paid_amount_cents' => 1000,
            'currency' => 'CNY',
            'gateway_create_attempt_count' => 0,
            'expires_at' => now()->addHour(),
        ]);
    }
}
