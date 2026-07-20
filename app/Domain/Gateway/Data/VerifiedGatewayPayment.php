<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Data;

use App\Domain\Gateway\Enums\GatewayPaymentStatus;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\ValueObjects\Money;
use DateTimeImmutable;

final readonly class VerifiedGatewayPayment
{
    public function __construct(
        public string $localOrderNumber,
        public string $gatewayTradeNumber,
        public PaymentMethod $method,
        public Money $paidAmount,
        public GatewayPaymentStatus $status,
        public ?DateTimeImmutable $paidAt = null,
    ) {}
}
