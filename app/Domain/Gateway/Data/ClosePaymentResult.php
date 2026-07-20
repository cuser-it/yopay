<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Data;

final readonly class ClosePaymentResult
{
    public function __construct(
        public bool $closed,
        public ?string $gatewayCode = null,
        public ?string $gatewayMessage = null,
    ) {}
}
