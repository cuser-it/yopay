<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentStatus: string
{
    case Creating = 'creating';
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case AmountMismatch = 'amount_mismatch';
    case PaidAfterCancel = 'paid_after_cancel';
    case Refunded = 'refunded';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Creating => in_array($next, [self::Pending, self::Failed], true),
            self::Pending => in_array($next, [self::Paid, self::AmountMismatch, self::Expired, self::Cancelled], true),
            self::Cancelled, self::Expired => $next === self::PaidAfterCancel,
            self::Paid => $next === self::Refunded,
            self::Failed, self::AmountMismatch, self::PaidAfterCancel, self::Refunded => false,
        };
    }

    public function isPaymentConfirmed(): bool
    {
        return in_array($this, [self::Paid, self::AmountMismatch, self::PaidAfterCancel, self::Refunded], true);
    }

    public function isAbnormal(): bool
    {
        return in_array($this, [self::AmountMismatch, self::PaidAfterCancel], true);
    }

    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Creating, self::Pending], true);
    }
}
