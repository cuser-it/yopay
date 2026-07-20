<?php

declare(strict_types=1);

namespace App\Domain\Developer\Data;

use App\Models\DeveloperApiCredential;

final readonly class IssuedApiCredential
{
    public function __construct(
        public DeveloperApiCredential $credential,
        public string $secret,
    ) {}
}
