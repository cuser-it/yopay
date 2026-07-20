<?php

declare(strict_types=1);

namespace App\Domain\Gateway\EasyPay;

use RuntimeException;

final class EasyPayRsaKeyException extends RuntimeException
{
    public const string MISSING = 'missing';

    public const string ENCRYPTED = 'encrypted';

    public const string INVALID = 'invalid';

    public const string NOT_RSA = 'not_rsa';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
