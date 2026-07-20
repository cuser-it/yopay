<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum PaymentMethod: string
{
    case Alipay = 'alipay';
    case WeChatPay = 'wxpay';
}
