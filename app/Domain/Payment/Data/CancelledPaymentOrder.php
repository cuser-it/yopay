<?php

declare(strict_types=1);

namespace App\Domain\Payment\Data;

use App\Models\PaymentOrder;

final readonly class CancelledPaymentOrder
{
    public function __construct(
        public PaymentOrder $order,
        public bool $channelOrderClosed,
    ) {}
}
