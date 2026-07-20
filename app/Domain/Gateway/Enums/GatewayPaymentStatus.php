<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Enums;

enum GatewayPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Closed = 'closed';
    case Refunded = 'refunded';
    case Unknown = 'unknown';
}
