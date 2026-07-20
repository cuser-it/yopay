<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Gateway\Contracts\PaymentGateway;
use App\Domain\Gateway\EasyPay\EasyPayV1Adapter;
use App\Domain\Gateway\EasyPay\EasyPayV2Adapter;
use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\GatewayRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EasyPayV1Adapter::class, function ($app): EasyPayV1Adapter {
            $config = $app->make(ConfigRepository::class);

            return new EasyPayV1Adapter(
                http: $app->make(HttpFactory::class),
                baseUrl: (string) $config->get('payment.gateway.easypay.v1.base_url', ''),
                merchantId: (string) $config->get('payment.gateway.easypay.merchant_id', ''),
                merchantKey: (string) $config->get('payment.gateway.easypay.v1.merchant_key', ''),
            );
        });

        $this->app->singleton(EasyPayV2Adapter::class, function ($app): EasyPayV2Adapter {
            $config = $app->make(ConfigRepository::class);

            return new EasyPayV2Adapter(
                http: $app->make(HttpFactory::class),
                baseUrl: (string) $config->get('payment.gateway.easypay.v2.base_url', ''),
                merchantId: (string) $config->get('payment.gateway.easypay.merchant_id', ''),
                merchantPrivateKey: (string) $config->get('payment.gateway.easypay.v2.merchant_private_key', ''),
                platformPublicKey: (string) $config->get('payment.gateway.easypay.v2.platform_public_key', ''),
                timestampToleranceSeconds: (int) $config->get('payment.gateway.easypay.v2.timestamp_tolerance_seconds', 300),
            );
        });

        $this->app->singleton(GatewayRegistry::class, function ($app): GatewayRegistry {
            $config = $app->make(ConfigRepository::class);
            $defaultVersion = GatewayApiVersion::tryFrom((string) $config->get('payment.gateway.default', 'v2'))
                ?? GatewayApiVersion::V2;

            return new GatewayRegistry(
                v1: $app->make(EasyPayV1Adapter::class),
                v2: $app->make(EasyPayV2Adapter::class),
                defaultVersion: $defaultVersion,
            );
        });

        $this->app->bind(
            PaymentGateway::class,
            static fn ($app): PaymentGateway => $app->make(GatewayRegistry::class)->default(),
        );
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
