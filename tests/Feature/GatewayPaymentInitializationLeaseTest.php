<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Gateway\EasyPay\EasyPayV1Adapter;
use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\PaymentMethodConflictException;
use App\Domain\Payment\Services\GatewayPaymentInitializationService;
use App\Domain\Payment\Support\GatewayCreationLease;
use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GatewayPaymentInitializationLeaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'payment.gateway.default' => 'v1',
            'payment.gateway.creation_lease_seconds' => 30,
            'payment.gateway.easypay.merchant_id' => '10001',
            'payment.gateway.easypay.v1.merchant_key' => 'test-merchant-key',
        ]);
        app()->forgetInstance(EasyPayV1Adapter::class);
        app()->forgetInstance(GatewayRegistry::class);
    }

    public function test_recent_gateway_creation_claim_does_not_create_a_second_upstream_order(): void
    {
        $order = $this->order(
            method: PaymentMethod::Alipay,
            attemptCount: 1,
            lastAttemptAt: now()->subSeconds(10),
        );
        Http::fake();

        $initialized = app(GatewayPaymentInitializationService::class)->initialize($order, PaymentMethod::Alipay);

        Http::assertNothingSent();
        self::assertSame(PaymentStatus::Creating, $initialized->status);
        self::assertSame(1, $initialized->gateway_create_attempt_count);
    }

    public function test_gateway_creation_lease_cannot_be_configured_below_safe_minimum(): void
    {
        config()->set('payment.gateway.creation_lease_seconds', 5);

        self::assertTrue(app(GatewayCreationLease::class)->isActive(now()->subSeconds(20)));
    }

    public function test_stale_gateway_creation_claim_can_be_retried(): void
    {
        $order = $this->order(
            method: PaymentMethod::Alipay,
            attemptCount: 1,
            lastAttemptAt: now()->subSeconds(31),
        );
        Http::fake([
            '*' => Http::response([
                'code' => 1,
                'trade_no' => 'GW-LEASE-RETRY',
                'qrcode' => 'https://pay.example.test/qr/GW-LEASE-RETRY',
            ]),
        ]);

        $initialized = app(GatewayPaymentInitializationService::class)->initialize($order, PaymentMethod::Alipay);
        $replayed = app(GatewayPaymentInitializationService::class)->initialize($initialized, PaymentMethod::Alipay);

        Http::assertSentCount(1);
        self::assertSame(PaymentStatus::Pending, $initialized->status);
        self::assertSame(2, $initialized->gateway_create_attempt_count);
        self::assertSame('GW-LEASE-RETRY', $initialized->gateway_order_no);
        self::assertSame($initialized->getKey(), $replayed->getKey());
        self::assertSame('GW-LEASE-RETRY', $replayed->gateway_order_no);
    }

    public function test_different_payment_method_is_rejected_while_claim_is_active(): void
    {
        $order = $this->order(
            method: PaymentMethod::Alipay,
            attemptCount: 1,
            lastAttemptAt: now()->subSeconds(10),
        );
        Http::fake();

        $this->expectException(PaymentMethodConflictException::class);

        try {
            app(GatewayPaymentInitializationService::class)->initialize($order, PaymentMethod::WxPay);
        } finally {
            Http::assertNothingSent();
        }
    }

    private function order(
        ?PaymentMethod $method,
        int $attemptCount,
        ?\DateTimeInterface $lastAttemptAt,
    ): PaymentOrder {
        return PaymentOrder::query()->create([
            'order_no' => 'PAY-'.strtoupper(substr(hash('sha256', uniqid('', true)), 0, 20)),
            'source' => OrderSource::PublicCheckout,
            'status' => PaymentStatus::Creating,
            'version' => 1,
            'expected_amount_cents' => 1000,
            'currency' => 'CNY',
            'subject' => 'Gateway lease payment',
            'payment_method' => $method,
            'gateway' => 'easypay',
            'gateway_api_version' => GatewayApiVersion::V1,
            'checkout_token_hash' => hash('sha256', uniqid('checkout-', true)),
            'checkout_token_ciphertext' => 'checkout-token',
            'gateway_create_attempt_count' => $attemptCount,
            'gateway_create_last_attempt_at' => $lastAttemptAt,
            'status_changed_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'checkout_token_expires_at' => now()->addHours(2),
        ]);
    }
}
