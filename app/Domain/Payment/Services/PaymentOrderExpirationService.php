<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\PaymentOrder;
use Illuminate\Support\Facades\DB;

final readonly class PaymentOrderExpirationService
{
    public function __construct(
        private PaymentStateTransitionService $transitions,
        private PaymentEventOutboxService $outbox,
    ) {}

    public function expireDue(int $limit = 200): int
    {
        $ids = PaymentOrder::query()
            ->whereIn('status', [PaymentStatus::Creating->value, PaymentStatus::Pending->value])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
        $expired = 0;

        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id): bool {
                $order = PaymentOrder::query()->lockForUpdate()->find($id);

                if ($order === null || $order->expires_at->isFuture() || $order->status->isTerminal()) {
                    return false;
                }

                if ($order->status === PaymentStatus::Creating && $order->gateway_create_attempt_count === 0) {
                    $this->transitions->transition($order, PaymentStatus::Failed, 'uninitialized_order_expired');

                    return true;
                }

                if ($order->status === PaymentStatus::Creating) {
                    $this->transitions->transition($order, PaymentStatus::Pending, 'gateway_creation_expiry_recovery');
                }

                $this->transitions->transition($order, PaymentStatus::Expired, 'scheduled_expiration');
                $this->outbox->create($order, 'payment.expired');

                return true;
            }, 3);

            if ($changed) {
                $expired++;
            }
        }

        return $expired;
    }
}
