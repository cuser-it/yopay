<?php

declare(strict_types=1);

namespace App\Domain\Gateway\Contracts;

use App\Domain\Gateway\Data\ClosePaymentResult;
use App\Domain\Gateway\Data\PaymentCreateRequest;
use App\Domain\Gateway\Data\PaymentCreateResult;
use App\Domain\Gateway\Data\PaymentQueryResult;
use App\Domain\Gateway\Data\VerifiedGatewayPayment;
use App\Domain\Gateway\Enums\GatewayApiVersion;

interface PaymentGateway
{
    public function apiVersion(): GatewayApiVersion;

    public function createPayment(PaymentCreateRequest $request): PaymentCreateResult;

    public function queryPayment(string $localOrderNumber, ?string $gatewayOrderNumber = null): PaymentQueryResult;

    public function closePayment(string $localOrderNumber, ?string $gatewayOrderNumber = null): ClosePaymentResult;

    public function verifyCallback(array $payload): VerifiedGatewayPayment;
}
