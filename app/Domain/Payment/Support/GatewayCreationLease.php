<?php

declare(strict_types=1);

namespace App\Domain\Payment\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class GatewayCreationLease
{
    public function __construct(private ConfigRepository $config) {}

    public function isActive(?DateTimeInterface $claimedAt): bool
    {
        if ($claimedAt === null) {
            return false;
        }

        return CarbonImmutable::instance($claimedAt)->isAfter(
            CarbonImmutable::now()->subSeconds($this->seconds()),
        );
    }

    private function seconds(): int
    {
        return max(30, (int) $this->config->get('payment.gateway.creation_lease_seconds', 30));
    }
}
