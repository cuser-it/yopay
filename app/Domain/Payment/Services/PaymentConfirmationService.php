<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\DuplicateGatewayTradeException;
use App\Domain\Payment\Exceptions\PaymentMethodConflictException;
use App\Domain\Payment\Exceptions\PaymentOrderUnavailableException;
use App\Domain\Payment\ValueObjects\Money;
use App\Models\PaymentOrder;
use DateTimeInterface;

final readonly class PaymentConfirmationService
{
    public function __construct(
        private PaymentStateTransitionService $transitions,
        private PaymentEventOutboxService $outbox,
    ) {}

    public function confirmLocked(
        PaymentOrder $order,
        Money $paidAmount,
        string $gatewayTradeNumber,
        PaymentMethod $method,
        string $source,
        ?DateTimeInterface $paidAt = null,
    ): PaymentStatus {
        if ($order->payment_method !== null && $order->payment_method !== $method) {
            throw new PaymentMethodConflictException('Verified payment method does not match the initialized order.');
        }

        $duplicateTrade = PaymentOrder::query()
            ->where('gateway', $order->gateway)
            ->where('gateway_trade_no', $gatewayTradeNumber)
            ->whereKeyNot($order->getKey())
            ->lockForUpdate()
            ->exists();

        if ($duplicateTrade) {
            throw new DuplicateGatewayTradeException('The verified gateway trade number is already bound to another order.');
        }

        if ($order->status->isPaymentConfirmed()) {
            if (
                $order->gateway_trade_no === $gatewayTradeNumber
                && $order->paid_amount_cents === $paidAmount->cents
                && ($order->payment_method === null || $order->payment_method === $method)
            ) {
                return $order->status;
            }

            throw new PaymentOrderUnavailableException('The order already has different confirmed payment facts.');
        }

        if ($order->status === PaymentStatus::Creating) {
            $this->transitions->transition($order, PaymentStatus::Pending, $source.'_creation_recovery');
        }

        if (! in_array($order->status, [PaymentStatus::Pending, PaymentStatus::Cancelled, PaymentStatus::Expired], true)) {
            throw new PaymentOrderUnavailableException('The order cannot accept a verified payment in its current state.');
        }

        $difference = $paidAmount->differenceFrom(new Money($order->expected_amount_cents, $order->currency));
        $next = match ($order->status) {
            PaymentStatus::Cancelled, PaymentStatus::Expired => PaymentStatus::PaidAfterCancel,
            default => $difference === 0 ? PaymentStatus::Paid : PaymentStatus::AmountMismatch,
        };

        $order->payment_method = $method;
        $order->paid_amount_cents = $paidAmount->cents;
        $order->amount_difference_cents = $difference;
        $order->gateway_trade_no = $gatewayTradeNumber;
        $order->paid_at = $paidAt ?? now();
        $order->save();

        $this->transitions->transition($order, $next, $source, [
            'gateway_trade_no' => $gatewayTradeNumber,
            'paid_amount_cents' => $paidAmount->cents,
            'amount_difference_cents' => $difference,
        ]);
        $this->outbox->create($order, $this->eventType($next));

        return $next;
    }

    private function eventType(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Paid => 'payment.paid',
            PaymentStatus::AmountMismatch => 'payment.amount_mismatch',
            PaymentStatus::PaidAfterCancel => 'payment.paid_after_cancel',
            default => throw new PaymentOrderUnavailableException('No payment event exists for this state.'),
        };
    }
}
