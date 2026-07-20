<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\InvalidPaymentTransitionException;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderStatusEvent;
use Carbon\CarbonImmutable;

final class PaymentStateTransitionService
{
    public function recordInitial(PaymentOrder $order, string $source, array $context = []): void
    {
        PaymentOrderStatusEvent::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => null,
            'to_status' => $order->status,
            'source' => $source,
            'context' => $context === [] ? null : $context,
            'created_at' => $order->status_changed_at,
        ]);
    }

    public function transition(PaymentOrder $order, PaymentStatus $next, string $source, array $context = []): void
    {
        $current = $order->status;

        if ($current === $next) {
            return;
        }

        if (! $current->canTransitionTo($next)) {
            throw new InvalidPaymentTransitionException("Payment order cannot transition from {$current->value} to {$next->value}.");
        }

        $changedAt = CarbonImmutable::now();
        $order->status = $next;
        $order->status_changed_at = $changedAt;
        $order->version++;

        match ($next) {
            PaymentStatus::Paid, PaymentStatus::AmountMismatch, PaymentStatus::PaidAfterCancel => $order->paid_at ??= $changedAt,
            PaymentStatus::Cancelled => $order->cancelled_at ??= $changedAt,
            PaymentStatus::Failed => $order->failed_at ??= $changedAt,
            default => null,
        };

        $order->save();

        PaymentOrderStatusEvent::query()->create([
            'order_id' => $order->getKey(),
            'from_status' => $current,
            'to_status' => $next,
            'source' => $source,
            'context' => $context === [] ? null : $context,
            'created_at' => $changedAt,
        ]);
    }
}
