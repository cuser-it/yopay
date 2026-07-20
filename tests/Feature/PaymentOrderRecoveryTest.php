<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Services\PaymentOrderRecoveryService;
use App\Domain\Payment\Services\PaymentStateTransitionService;
use App\Domain\Payment\Support\CheckoutToken;
use App\Models\PaymentEvent;
use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PaymentOrderRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_creating_order_with_gateway_attempt_becomes_reconcilable_expired_order(): void
    {
        $token = 'checkout-recovery-attempted';
        $order = $this->order($token, 1);

        $restored = app(PaymentOrderRecoveryService::class)->restore($token);

        self::assertSame(PaymentStatus::Expired, $restored->status);
        self::assertSame(1, PaymentEvent::query()->where('event_type', 'payment.expired')->count());
        self::assertSame(
            ['creating', 'pending', 'expired'],
            $order->statusEvents()->orderBy('id')->get()->map(
                static fn ($event): string => $event->to_status->value,
            )->all(),
        );
    }

    public function test_expired_creating_order_without_gateway_attempt_becomes_failed(): void
    {
        $token = 'checkout-recovery-unattempted';
        $this->order($token, 0);

        $restored = app(PaymentOrderRecoveryService::class)->restore($token);

        self::assertSame(PaymentStatus::Failed, $restored->status);
        self::assertSame(0, PaymentEvent::query()->count());
    }

    public function test_recent_gateway_creation_claim_is_not_retried_during_restore(): void
    {
        config()->set('payment.gateway.creation_lease_seconds', 30);
        $token = 'checkout-recovery-active-claim';
        $this->order(
            token: $token,
            attemptCount: 1,
            expiresAt: now()->addMinutes(15),
            lastAttemptAt: now()->subSeconds(20),
        );
        Http::fake();

        $restored = app(PaymentOrderRecoveryService::class)->restore($token);

        Http::assertNothingSent();
        self::assertSame(PaymentStatus::Creating, $restored->status);
        self::assertSame(1, $restored->gateway_create_attempt_count);
    }

    private function order(
        string $token,
        int $attemptCount,
        ?\DateTimeInterface $expiresAt = null,
        ?\DateTimeInterface $lastAttemptAt = null,
    ): PaymentOrder {
        $order = PaymentOrder::query()->create([
            'order_no' => 'PAY-'.strtoupper(substr(hash('sha256', $token), 0, 20)),
            'source' => OrderSource::PublicCheckout,
            'status' => PaymentStatus::Creating,
            'version' => 1,
            'expected_amount_cents' => 1000,
            'currency' => 'CNY',
            'subject' => 'Recovery payment',
            'payment_method' => PaymentMethod::Alipay,
            'gateway' => 'easypay',
            'gateway_api_version' => GatewayApiVersion::V2,
            'checkout_token_hash' => CheckoutToken::hash($token),
            'checkout_token_ciphertext' => $token,
            'gateway_create_attempt_count' => $attemptCount,
            'gateway_create_last_attempt_at' => $lastAttemptAt,
            'status_changed_at' => now()->subMinutes(20),
            'expires_at' => $expiresAt ?? now()->subMinute(),
            'checkout_token_expires_at' => now()->addHour(),
        ]);
        app(PaymentStateTransitionService::class)->recordInitial($order, 'test_created');

        return $order;
    }
}
