<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Data;

use App\Domain\Gateway\Enums\GatewayPaymentStatus;

final readonly class PaymentCreateResult
{
    public function __construct(
        public string $gatewayOrderNumber,
        public GatewayPaymentStatus $status,
        public PaymentAction $action,
        public ?string $gatewayCode = null,
        public ?string $gatewayMessage = null,
    ) {}
}
