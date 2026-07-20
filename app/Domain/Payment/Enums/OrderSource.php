<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum OrderSource: string
{
    case PublicCheckout = 'public_checkout';
    case DeveloperApi = 'developer_api';
}
