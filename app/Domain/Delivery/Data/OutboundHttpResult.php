<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Data;

final readonly class OutboundHttpResult
{
    public function __construct(
        public int $status,
        public string $summary,
        public int $durationMilliseconds,
        public bool $successful,
    ) {}
}
