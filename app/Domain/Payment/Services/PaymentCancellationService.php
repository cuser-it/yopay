<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Data\CancelledPaymentOrder;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\PaymentOrderUnavailableException;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PaymentCancellationService
{
    public function __construct(
        private GatewayRegistry $gateways,
        private PaymentStateTransitionService $transitions,
        private PaymentEventOutboxService $outbox,
    ) {}

    public function cancel(PaymentOrder $order, string $source): CancelledPaymentOrder
    {
        $order = DB::transaction(function () use ($order, $source): PaymentOrder {
            $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->status === PaymentStatus::Cancelled) {
                return $locked;
            }

            if (! in_array($locked->status, [PaymentStatus::Creating, PaymentStatus::Pending], true)) {
                throw new PaymentOrderUnavailableException('The payment order is not cancellable.');
            }

            if ($locked->status === PaymentStatus::Creating) {
                $this->transitions->transition($locked, PaymentStatus::Pending, $source.'_creation_abandoned');
            }

            $this->transitions->transition($locked, PaymentStatus::Cancelled, $source);
            $this->outbox->create($locked, 'payment.cancelled');

            return $locked->refresh();
        }, 3);

        $closed = false;

        if ($order->gateway_create_attempt_count > 0) {
            try {
                $closed = $this->gateways
                    ->forVersion($order->gateway_api_version)
                    ->closePayment($order->order_no, $order->gateway_order_no)
                    ->closed;
            } catch (Throwable $exception) {
                Log::warning('Gateway close-order request failed after local cancellation.', [
                    'order_no' => $order->order_no,
                    'exception' => $exception::class,
                ]);
            }
        }

        return new CancelledPaymentOrder($order, $closed);
    }
}
