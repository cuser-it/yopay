<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payment\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function test_transition_matrix_preserves_payment_exception_states(): void
    {
        self::assertTrue(PaymentStatus::Creating->canTransitionTo(PaymentStatus::Pending));
        self::assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::AmountMismatch));
        self::assertTrue(PaymentStatus::Cancelled->canTransitionTo(PaymentStatus::PaidAfterCancel));
        self::assertTrue(PaymentStatus::Expired->canTransitionTo(PaymentStatus::PaidAfterCancel));
        self::assertFalse(PaymentStatus::Failed->canTransitionTo(PaymentStatus::Paid));
        self::assertFalse(PaymentStatus::PaidAfterCancel->canTransitionTo(PaymentStatus::Paid));
    }
}
