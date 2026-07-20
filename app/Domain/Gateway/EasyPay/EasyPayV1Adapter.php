<?php

declare(strict_types=1);

namespace App\Domain\Gateway\EasyPay;

use App\Domain\Gateway\Contracts\PaymentGateway;
use App\Domain\Gateway\Data\ClosePaymentResult;
use App\Domain\Gateway\Data\PaymentCreateRequest;
use App\Domain\Gateway\Data\PaymentCreateResult;
use App\Domain\Gateway\Data\PaymentQueryResult;
use App\Domain\Gateway\Data\VerifiedGatewayPayment;
use App\Domain\Gateway\EasyPay\Concerns\MapsEasyPayData;
use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\Enums\GatewayPaymentStatus;
use App\Domain\Gateway\Exceptions\GatewayConfigurationException;
use App\Domain\Gateway\Exceptions\GatewayProtocolException;
use Illuminate\Http\Client\Factory as HttpFactory;

final class EasyPayV1Adapter implements PaymentGateway
{
    use MapsEasyPayData;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $merchantId,
        private readonly string $merchantKey,
    ) {}

    public function apiVersion(): GatewayApiVersion
    {
        return GatewayApiVersion::V1;
    }

    public function createPayment(PaymentCreateRequest $request): PaymentCreateResult
    {
        $this->assertConfigured();

        $parameters = [
            'pid' => $this->merchantId,
            'type' => $request->method->value,
            'out_trade_no' => $request->localOrderNumber,
            'notify_url' => $request->notifyUrl,
            'return_url' => $request->returnUrl,
            'name' => $request->subject,
            'money' => $request->expectedAmount->format(),
            'clientip' => $request->clientIp,
        ];
        $parameters['sign'] = EasyPaySignature::signV1($parameters, $this->merchantKey);
        $parameters['sign_type'] = 'MD5';

        $payload = $this->http
            ->asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post($this->endpoint('/mapi.php'), $parameters)
            ->throw()
            ->json();

        if (! is_array($payload) || (int) ($payload['code'] ?? 0) !== 1) {
            throw new GatewayProtocolException((string) ($payload['msg'] ?? 'EasyPay V1 payment creation failed.'));
        }

        $gatewayOrderNumber = (string) ($payload['trade_no'] ?? '');

        if ($gatewayOrderNumber === '') {
            throw new GatewayProtocolException('EasyPay V1 response did not include a gateway order number.');
        }

        return new PaymentCreateResult(
            gatewayOrderNumber: $gatewayOrderNumber,
            status: GatewayPaymentStatus::Pending,
            action: $this->mapPaymentAction($payload),
            gatewayCode: (string) ($payload['code'] ?? ''),
            gatewayMessage: isset($payload['msg']) ? (string) $payload['msg'] : null,
        );
    }

    public function queryPayment(string $localOrderNumber, ?string $gatewayOrderNumber = null): PaymentQueryResult
    {
        $this->assertConfigured();

        $payload = $this->http
            ->connectTimeout(5)
            ->timeout(15)
            ->get($this->endpoint('/api.php'), [
                'act' => 'order',
                'pid' => $this->merchantId,
                'key' => $this->merchantKey,
                'out_trade_no' => $localOrderNumber,
            ])
            ->throw()
            ->json();

        if (! is_array($payload) || (int) ($payload['code'] ?? 0) !== 1) {
            throw new GatewayProtocolException((string) ($payload['msg'] ?? 'EasyPay V1 order query failed.'));
        }

        $paidAmount = isset($payload['money']) && (string) $payload['money'] !== ''
            ? $this->parseMoney((string) $payload['money'])
            : null;

        return new PaymentQueryResult(
            status: $this->mapGatewayStatus($payload['status'] ?? null),
            paidAmount: $paidAmount,
            gatewayOrderNumber: isset($payload['trade_no']) ? (string) $payload['trade_no'] : $gatewayOrderNumber,
            gatewayTradeNumber: isset($payload['trade_no']) ? (string) $payload['trade_no'] : null,
        );
    }

    public function closePayment(string $localOrderNumber, ?string $gatewayOrderNumber = null): ClosePaymentResult
    {
        return new ClosePaymentResult(
            closed: false,
            gatewayCode: 'unsupported',
            gatewayMessage: 'EasyPay V1 does not provide a close-order capability.',
        );
    }

    public function verifyCallback(array $payload): VerifiedGatewayPayment
    {
        $this->assertConfigured();

        if (! hash_equals($this->merchantId, (string) ($payload['pid'] ?? ''))) {
            throw new GatewayProtocolException('EasyPay V1 callback merchant does not match.');
        }

        EasyPaySignature::verifyV1($payload, $this->merchantKey);
        $status = $this->mapGatewayStatus($payload['trade_status'] ?? null);

        if ($status !== GatewayPaymentStatus::Paid) {
            throw new GatewayProtocolException('EasyPay V1 callback is not a successful payment.');
        }

        $localOrderNumber = (string) ($payload['out_trade_no'] ?? '');
        $gatewayTradeNumber = (string) ($payload['trade_no'] ?? '');

        if ($localOrderNumber === '' || $gatewayTradeNumber === '' || ! isset($payload['money'], $payload['type'])) {
            throw new GatewayProtocolException('EasyPay V1 callback is missing required payment facts.');
        }

        return new VerifiedGatewayPayment(
            localOrderNumber: $localOrderNumber,
            gatewayTradeNumber: $gatewayTradeNumber,
            method: $this->mapPaymentMethod((string) $payload['type']),
            paidAmount: $this->parseMoney((string) $payload['money']),
            status: $status,
        );
    }

    private function assertConfigured(): void
    {
        if ($this->merchantId === '' || $this->merchantKey === '') {
            throw new GatewayConfigurationException('EasyPay V1 merchant credentials are not configured.');
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
