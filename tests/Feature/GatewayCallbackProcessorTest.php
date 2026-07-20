<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Gateway\EasyPay\EasyPaySignature;
use App\Domain\Gateway\EasyPay\EasyPayV1Adapter;
use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Services\GatewayCallbackProcessor;
use App\Models\PaymentCallback;
use App\Models\PaymentEvent;
use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GatewayCallbackProcessorTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT_ID = '10001';
    private const MERCHANT_KEY = 'test-merchant-key';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'payment.gateway.easypay.merchant_id' => self::MERCHANT_ID,
            'payment.gateway.easypay.v1.merchant_key' => self::MERCHANT_KEY,
            'payment.gateway.default' => 'v1',
            'payment.notifications.targets' => [],
        ]);
        app()->forgetInstance(EasyPayV1Adapter::class);
        app()->forgetInstance(GatewayRegistry::class);
    }

    public function test_duplicate_callback_creates_one_amount_mismatch_event(): void
    {
        $order = $this->order(PaymentStatus::Pending, 'PAY-CALLBACK-1', 1000);
        $payload = $this->payload($order->order_no, 'GW-TRADE-1', '9.99');
        $processor = app(GatewayCallbackProcessor::class);

        $first = $processor->process(GatewayApiVersion::V1, $payload);
        $duplicate = $processor->process(GatewayApiVersion::V1, $payload);

        self::assertSame('processed', $first->processing_status);
        self::assertSame('processed', $duplicate->processing_status);
        self::assertSame(PaymentStatus::AmountMismatch, $order->fresh()->status);
        self::assertSame(999, $order->fresh()->paid_amount_cents);
        self::assertSame(-1, $order->fresh()->amount_difference_cents);
        self::assertSame(1, PaymentCallback::query()->count());
        self::assertSame(1, PaymentEvent::query()->where('event_type', 'payment.amount_mismatch')->count());
    }

    public function test_verified_payment_after_cancel_is_preserved_as_exception(): void
    {
        $order = $this->order(PaymentStatus::Cancelled, 'PAY-CALLBACK-2', 1000);

        app(GatewayCallbackProcessor::class)->process(
            GatewayApiVersion::V1,
            $this->payload($order->order_no, 'GW-TRADE-2', '10.00'),
        );

        self::assertSame(PaymentStatus::PaidAfterCancel, $order->fresh()->status);
        self::assertSame(1000, $order->fresh()->paid_amount_cents);
        self::assertSame(1, PaymentEvent::query()->where('event_type', 'payment.paid_after_cancel')->count());
    }

    public function test_exact_callback_can_be_reprocessed_after_order_not_found(): void
    {
        $payload = $this->payload('PAY-LATE-ORDER', 'GW-TRADE-3', '10.00');
        $processor = app(GatewayCallbackProcessor::class);
        $failed = $processor->process(GatewayApiVersion::V1, $payload);

        self::assertSame('failed', $failed->processing_status);
        self::assertSame('order_not_found', $failed->outcome);

        $order = $this->order(PaymentStatus::Pending, 'PAY-LATE-ORDER', 1000);
        $reprocessed = $processor->process(GatewayApiVersion::V1, $payload);

        self::assertSame('processed', $reprocessed->processing_status);
        self::assertSame(PaymentStatus::Paid, $order->fresh()->status);
        self::assertSame(1, PaymentCallback::query()->count());
    }

    private function order(PaymentStatus $status, string $orderNo, int $expectedAmountCents): PaymentOrder
    {
        return PaymentOrder::query()->create([
            'order_no' => $orderNo,
            'source' => OrderSource::PublicCheckout,
            'status' => $status,
            'version' => 1,
            'expected_amount_cents' => $expectedAmountCents,
            'currency' => 'CNY',
            'subject' => 'Test payment',
            'payment_method' => PaymentMethod::Alipay,
            'gateway' => 'easypay',
            'gateway_api_version' => GatewayApiVersion::V1,
            'checkout_token_hash' => hash('sha256', $orderNo),
            'checkout_token_ciphertext' => 'checkout-'.$orderNo,
            'gateway_create_attempt_count' => 1,
            'status_changed_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'checkout_token_expires_at' => now()->addHours(2),
            'cancelled_at' => $status === PaymentStatus::Cancelled ? now() : null,
        ]);
    }

    private function payload(string $orderNo, string $tradeNo, string $money): array
    {
        $payload = [
            'pid' => self::MERCHANT_ID,
            'trade_no' => $tradeNo,
            'out_trade_no' => $orderNo,
            'type' => 'alipay',
            'name' => 'Test payment',
            'money' => $money,
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'MD5',
        ];
        $payload['sign'] = EasyPaySignature::signV1($payload, self::MERCHANT_KEY);

        return $payload;
    }
}
