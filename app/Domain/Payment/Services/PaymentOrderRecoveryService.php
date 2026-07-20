<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\PaymentOrderUnavailableException;
use App\Domain\Payment\Support\CheckoutToken;
use App\Domain\Payment\Support\GatewayCreationLease;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;

final readonly class PaymentOrderRecoveryService
{
    public function __construct(
        private GatewayCreationLease $creationLease,
        private GatewayPaymentInitializationService $initializer,
        private PaymentStateTransitionService $transitions,
        private PaymentEventOutboxService $outbox,
    ) {}

    public function restore(string $checkoutToken): PaymentOrder
    {
        $order = PaymentOrder::query()
            ->where('checkout_token_hash', CheckoutToken::hash($checkoutToken))
            ->first();

        if ($order === null || $order->checkout_token_expires_at->isPast()) {
            throw new PaymentOrderUnavailableException('The checkout token is invalid or expired.');
        }

        $order = $this->applyExpiration($order);

        if (
            in_array($order->status, [PaymentStatus::Creating, PaymentStatus::Pending], true)
            && $order->payment_method !== null
            && $order->payment_action_payload === null
            && ! $this->creationLease->isActive($order->gateway_create_last_attempt_at)
        ) {
            $order = $this->initializer->initialize($order, $order->payment_method);
        }

        return $order->refresh();
    }

    private function applyExpiration(PaymentOrder $order): PaymentOrder
    {
        if ($order->expires_at->isFuture() || $order->status->isTerminal()) {
            return $order;
        }

        return DB::transaction(function () use ($order): PaymentOrder {
            $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->expires_at->isFuture() || $locked->status->isTerminal()) {
                return $locked;
            }

            if ($locked->status === PaymentStatus::Creating && $locked->gateway_create_attempt_count === 0) {
                $this->transitions->transition($locked, PaymentStatus::Failed, 'uninitialized_order_expired');

                return $locked->refresh();
            }

            if ($locked->status === PaymentStatus::Creating) {
                $this->transitions->transition($locked, PaymentStatus::Pending, 'gateway_creation_expiry_recovery');
            }

            $this->transitions->transition($locked, PaymentStatus::Expired, 'checkout_expired');
            $this->outbox->create($locked, 'payment.expired');

            return $locked->refresh();
        }, 3);
    }
}
