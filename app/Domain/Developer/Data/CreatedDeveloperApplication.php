<?php

declare(strict_types=1);

namespace App\Domain\Developer\Data;

use App\Models\DeveloperApplication;

final readonly class CreatedDeveloperApplication
{
    public function __construct(
        public DeveloperApplication $application,
        public IssuedApiCredential $issuedCredential,
    ) {}
}
