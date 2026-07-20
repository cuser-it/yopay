<?php

declare(strict_types=1);

namespace App\Domain\Payment\Data;

use App\Models\PaymentOrder;

final readonly class GatewayCreationClaim
{
    public function __construct(
        public PaymentOrder $order,
        public bool $acquired,
        public ?int $attemptNumber = null,
    ) {}
}
