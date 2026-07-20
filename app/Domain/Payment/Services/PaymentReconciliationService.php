<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Gateway\Enums\GatewayPaymentStatus;
use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PaymentReconciliationService
{
    public function __construct(
        private GatewayRegistry $gateways,
        private PaymentConfirmationService $confirmation,
        private PaymentStateTransitionService $transitions,
        private PaymentEventOutboxService $outbox,
    ) {}

    public function reconcile(int $orderId): void
    {
        $order = PaymentOrder::query()->find($orderId);

        if ($order === null || ! in_array($order->status, [
            PaymentStatus::Creating,
            PaymentStatus::Pending,
            PaymentStatus::Cancelled,
            PaymentStatus::Expired,
            PaymentStatus::Paid,
        ], true)) {
            return;
        }

        try {
            $result = $this->gateways
                ->forVersion($order->gateway_api_version)
                ->queryPayment($order->order_no, $order->gateway_order_no);

            DB::transaction(function () use ($order, $result): void {
                $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());
                $locked->last_reconciled_at = now();
                $locked->gateway_order_no ??= $result->gatewayOrderNumber;
                $locked->save();

                if ($result->status === GatewayPaymentStatus::Paid) {
                    if ($result->paidAmount === null || $result->gatewayTradeNumber === null || $locked->payment_method === null) {
                        Log::warning('Verified gateway query omitted required payment facts.', ['order_no' => $locked->order_no]);

                        return;
                    }

                    $this->confirmation->confirmLocked(
                        order: $locked,
                        paidAmount: $result->paidAmount,
                        gatewayTradeNumber: $result->gatewayTradeNumber,
                        method: $locked->payment_method,
                        source: 'gateway_reconciliation',
                        paidAt: $result->paidAt,
                    );
                } elseif ($result->status === GatewayPaymentStatus::Refunded && $locked->status === PaymentStatus::Paid) {
                    $this->transitions->transition($locked, PaymentStatus::Refunded, 'gateway_reconciliation');
                    $this->outbox->create($locked, 'payment.refunded');
                }
            }, 3);
        } catch (Throwable $exception) {
            Log::warning('Payment reconciliation failed.', [
                'order_no' => $order->order_no,
                'exception' => $exception::class,
            ]);
        }
    }
}
