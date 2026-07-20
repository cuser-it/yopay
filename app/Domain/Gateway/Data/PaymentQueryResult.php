<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Data;

use App\Domain\Gateway\Enums\GatewayPaymentStatus;
use App\Domain\Payment\ValueObjects\Money;
use DateTimeImmutable;

final readonly class PaymentQueryResult
{
    public function __construct(
        public GatewayPaymentStatus $status,
        public ?Money $paidAmount = null,
        public ?string $gatewayOrderNumber = null,
        public ?string $gatewayTradeNumber = null,
        public ?DateTimeImmutable $paidAt = null,
    ) {}
}
