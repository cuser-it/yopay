<?php

declare(strict_types=1);

namespace App\Domain\Gateway;

use App\Domain\Gateway\Contracts\PaymentGateway;
use App\Domain\Gateway\EasyPay\EasyPayV1Adapter;
use App\Domain\Gateway\EasyPay\EasyPayV2Adapter;
use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\Exceptions\GatewayConfigurationException;

final readonly class GatewayRegistry
{
    public function __construct(
        private EasyPayV1Adapter $v1,
        private EasyPayV2Adapter $v2,
        private GatewayApiVersion $defaultVersion,
    ) {}

    public function default(): PaymentGateway
    {
        return $this->forVersion($this->defaultVersion);
    }

    public function forVersion(GatewayApiVersion|string $version): PaymentGateway
    {
        $resolved = is_string($version) ? GatewayApiVersion::tryFrom($version) : $version;

        return match ($resolved) {
            GatewayApiVersion::V1 => $this->v1,
            GatewayApiVersion::V2 => $this->v2,
            null => throw new GatewayConfigurationException('Unsupported payment gateway API version.'),
        };
    }
}
