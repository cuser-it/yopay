<?php

declare(strict_types=1);

namespace App\Domain\Payment\Support;

use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\ValueObjects\Money;
use App\Models\PaymentOrder;

final class PaymentOrderTransformer
{
    public static function checkout(PaymentOrder $order): array
    {
        $showAction = in_array($order->status, [PaymentStatus::Creating, PaymentStatus::Pending], true);

        return [
            'order_no' => $order->order_no,
            'source' => $order->source->value,
            'status' => $order->status->value,
            'subject' => $order->subject,
            'expected_amount_cents' => $order->expected_amount_cents,
            'expected_amount' => (new Money($order->expected_amount_cents, $order->currency))->format(),
            'paid_amount_cents' => $order->paid_amount_cents,
            'paid_amount' => $order->paid_amount_cents === null
                ? null
                : (new Money($order->paid_amount_cents, $order->currency))->format(),
            'amount_difference_cents' => $order->amount_difference_cents,
            'currency' => $order->currency,
            'payment_method' => $order->payment_method?->value,
            'payment_method_selectable' => $order->payment_method === null && $order->status === PaymentStatus::Creating,
            'payment_action' => $showAction && $order->payment_action_payload !== null ? [
                'type' => $order->payment_action_type?->value ?? (string) $order->payment_action_type,
                'payload' => $order->payment_action_payload,
                'direct_url' => $order->payment_direct_url,
            ] : null,
            'recovery_required' => $order->status === PaymentStatus::Creating && $order->gateway_create_attempt_count > 0,
            'expires_at' => $order->expires_at->toAtomString(),
            'paid_at' => $order->paid_at?->toAtomString(),
            'cancelled_at' => $order->cancelled_at?->toAtomString(),
            'return_url' => $order->status->isTerminal() ? $order->return_url : null,
            'can_start_new_payment' => $order->source === OrderSource::PublicCheckout
                && in_array($order->status, [
                    PaymentStatus::Paid,
                    PaymentStatus::Expired,
                    PaymentStatus::Cancelled,
                    PaymentStatus::Failed,
                ], true),
            'updated_at' => $order->updated_at?->toAtomString(),
        ];
    }

    public static function developer(PaymentOrder $order, ?string $checkoutToken = null): array
    {
        return [
            'order_no' => $order->order_no,
            'external_order_no' => $order->external_order_no,
            'status' => $order->status->value,
            'expected_amount_cents' => $order->expected_amount_cents,
            'paid_amount_cents' => $order->paid_amount_cents,
            'amount_difference_cents' => $order->amount_difference_cents,
            'currency' => $order->currency,
            'subject' => $order->subject,
            'payment_type' => $order->payment_method?->value,
            'channel_trade_no' => $order->gateway_trade_no,
            'checkout_url' => $checkoutToken === null ? null : route('checkout.resume', ['token' => $checkoutToken]),
            'created_at' => $order->created_at?->toAtomString(),
            'expires_at' => $order->expires_at->toAtomString(),
            'paid_at' => $order->paid_at?->toAtomString(),
            'cancelled_at' => $order->cancelled_at?->toAtomString(),
            'exception_code' => $order->status->isAbnormal() ? strtoupper($order->status->value) : null,
        ];
    }
}
