<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Data;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\ValueObjects\Money;

final readonly class PaymentCreateRequest
{
    public function __construct(
        public string $localOrderNumber,
        public PaymentMethod $method,
        public Money $expectedAmount,
        public string $subject,
        public string $notifyUrl,
        public string $returnUrl,
        public string $clientIp,
        public array $metadata = [],
    ) {}
}
