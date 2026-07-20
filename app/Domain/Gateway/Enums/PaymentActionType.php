<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Enums;

enum PaymentActionType: string
{
    case QrCode = 'qr_code';
    case Redirect = 'redirect';
    case UrlScheme = 'url_scheme';
}
