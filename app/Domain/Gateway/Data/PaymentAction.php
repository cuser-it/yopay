<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Data;

use App\Domain\Gateway\Enums\PaymentActionType;

final readonly class PaymentAction
{
    public function __construct(
        public PaymentActionType $type,
        public string $payload,
        public ?string $directUrl = null,
    ) {}
}
