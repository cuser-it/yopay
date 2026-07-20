<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Delivery\Services\OutboundDeliveryRecoveryService;
use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\NotificationDelivery;
use App\Models\PaymentEvent;
use App\Models\PaymentOrder;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OutboundDeliveryRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_processing_deliveries_are_returned_to_retry_queue(): void
    {
        config()->set('payment.webhooks.processing_timeout_seconds', 120);
        $order = $this->order();
        $event = PaymentEvent::query()->create([
            'event_id' => (string) Str::ulid(),
            'order_id' => $order->getKey(),
            'event_type' => 'payment.paid',
            'payload' => ['event_id' => 'event-test', 'data' => ['order_no' => $order->order_no]],
            'occurred_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);
        $webhook = WebhookDelivery::query()->create([
            'payment_event_id' => $event->getKey(),
            'destination_hash' => hash('sha256', 'https://example.com/webhook'),
            'destination_url' => 'https://example.com/webhook',
            'secret_ciphertext' => 'whsec_test',
            'status' => 'processing',
            'attempt_count' => 1,
            'last_attempt_at' => now()->subMinutes(5),
        ]);
        $notification = NotificationDelivery::query()->create([
            'order_id' => $order->getKey(),
            'payment_event_id' => $event->getKey(),
            'channel' => 'http',
            'destination_hash' => hash('sha256', 'https://example.com/admin'),
            'destination_ciphertext' => 'https://example.com/admin',
            'status' => 'processing',
            'attempt_count' => 1,
            'last_attempt_at' => now()->subMinutes(5),
        ]);

        $recovered = app(OutboundDeliveryRecoveryService::class)->recoverStaleProcessing();

        self::assertSame(2, $recovered);
        self::assertSame('retrying', $webhook->fresh()->status);
        self::assertSame('retrying', $notification->fresh()->status);
        self::assertNotNull($webhook->fresh()->next_attempt_at);
        self::assertNotNull($notification->fresh()->next_attempt_at);
    }

    private function order(): PaymentOrder
    {
        return PaymentOrder::query()->create([
            'order_no' => 'PAY-DELIVERY-RECOVERY',
            'source' => OrderSource::PublicCheckout,
            'status' => PaymentStatus::Paid,
            'version' => 2,
            'expected_amount_cents' => 1000,
            'paid_amount_cents' => 1000,
            'amount_difference_cents' => 0,
            'currency' => 'CNY',
            'subject' => 'Delivery recovery',
            'gateway' => 'easypay',
            'gateway_api_version' => GatewayApiVersion::V2,
            'gateway_trade_no' => 'GW-DELIVERY-RECOVERY',
            'checkout_token_hash' => hash('sha256', 'delivery-recovery'),
            'checkout_token_ciphertext' => 'delivery-recovery',
            'status_changed_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5),
            'checkout_token_expires_at' => now()->addHour(),
            'paid_at' => now()->subMinutes(10),
        ]);
    }
}
