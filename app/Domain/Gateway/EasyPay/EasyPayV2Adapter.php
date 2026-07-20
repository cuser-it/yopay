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

final class EasyPayV2Adapter implements PaymentGateway
{
    use MapsEasyPayData;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $merchantId,
        private readonly string $merchantPrivateKey,
        private readonly string $platformPublicKey,
        private readonly int $timestampToleranceSeconds,
    ) {}

    public function apiVersion(): GatewayApiVersion
    {
        return GatewayApiVersion::V2;
    }

    public function createPayment(PaymentCreateRequest $request): PaymentCreateResult
    {
        $parameters = [
            'pid' => $this->merchantId,
            'method' => 'web',
            'type' => $request->method->value,
            'out_trade_no' => $request->localOrderNumber,
            'notify_url' => $request->notifyUrl,
            'return_url' => $request->returnUrl,
            'name' => $request->subject,
            'money' => $request->expectedAmount->format(),
            'clientip' => $request->clientIp,
            'timestamp' => time(),
        ];

        if ($request->metadata !== []) {
            $parameters['param'] = json_encode($request->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $payload = $this->signedPost('/api/pay/create', $parameters);
        $gatewayOrderNumber = (string) ($payload['trade_no'] ?? '');

        if ($gatewayOrderNumber === '') {
            throw new GatewayProtocolException('EasyPay V2 response did not include a gateway order number.');
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
        $payload = $this->signedPost('/api/pay/query', array_filter([
            'pid' => $this->merchantId,
            'out_trade_no' => $localOrderNumber,
            'trade_no' => $gatewayOrderNumber,
            'timestamp' => time(),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        $paidAmount = isset($payload['money']) && (string) $payload['money'] !== ''
            ? $this->parseMoney((string) $payload['money'])
            : null;

        return new PaymentQueryResult(
            status: $this->mapGatewayStatus($payload['status'] ?? $payload['trade_status'] ?? null),
            paidAmount: $paidAmount,
            gatewayOrderNumber: isset($payload['trade_no']) ? (string) $payload['trade_no'] : $gatewayOrderNumber,
            gatewayTradeNumber: isset($payload['trade_no']) ? (string) $payload['trade_no'] : $gatewayOrderNumber,
        );
    }

    public function closePayment(string $localOrderNumber, ?string $gatewayOrderNumber = null): ClosePaymentResult
    {
        $payload = $this->signedPost('/api/pay/close', array_filter([
            'pid' => $this->merchantId,
            'out_trade_no' => $localOrderNumber,
            'trade_no' => $gatewayOrderNumber,
            'timestamp' => time(),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        return new ClosePaymentResult(
            closed: true,
            gatewayCode: (string) ($payload['code'] ?? ''),
            gatewayMessage: isset($payload['msg']) ? (string) $payload['msg'] : null,
        );
    }

    public function verifyCallback(array $payload): VerifiedGatewayPayment
    {
        $this->assertConfigured();

        if (! hash_equals($this->merchantId, (string) ($payload['pid'] ?? ''))) {
            throw new GatewayProtocolException('EasyPay V2 callback merchant does not match.');
        }

        $this->assertTimestamp($payload['timestamp'] ?? null);
        EasyPaySignature::verifyV2($payload, $this->platformPublicKey);
        $status = $this->mapGatewayStatus($payload['trade_status'] ?? $payload['status'] ?? null);

        if ($status !== GatewayPaymentStatus::Paid) {
            throw new GatewayProtocolException('EasyPay V2 callback is not a successful payment.');
        }

        $localOrderNumber = (string) ($payload['out_trade_no'] ?? '');
        $gatewayTradeNumber = (string) ($payload['trade_no'] ?? '');

        if ($localOrderNumber === '' || $gatewayTradeNumber === '' || ! isset($payload['money'], $payload['type'])) {
            throw new GatewayProtocolException('EasyPay V2 callback is missing required payment facts.');
        }

        return new VerifiedGatewayPayment(
            localOrderNumber: $localOrderNumber,
            gatewayTradeNumber: $gatewayTradeNumber,
            method: $this->mapPaymentMethod((string) $payload['type']),
            paidAmount: $this->parseMoney((string) $payload['money']),
            status: $status,
        );
    }

    private function signedPost(string $path, array $parameters): array
    {
        $this->assertConfigured();
        $parameters['sign'] = EasyPaySignature::signV2($parameters, $this->merchantPrivateKey);
        $parameters['sign_type'] = 'RSA';

        $payload = $this->http
            ->asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post($this->endpoint($path), $parameters)
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new GatewayProtocolException('EasyPay V2 returned an invalid JSON response.');
        }

        if ((int) ($payload['code'] ?? -1) !== 0) {
            throw new GatewayProtocolException((string) ($payload['msg'] ?? 'EasyPay V2 request failed.'));
        }

        $this->assertTimestamp($payload['timestamp'] ?? null);
        EasyPaySignature::verifyV2($payload, $this->platformPublicKey);

        return $payload;
    }

    private function assertConfigured(): void
    {
        if ($this->merchantId === '' || $this->merchantPrivateKey === '' || $this->platformPublicKey === '') {
            throw new GatewayConfigurationException('EasyPay V2 credentials are not configured.');
        }
    }

    private function assertTimestamp(mixed $timestamp): void
    {
        $value = filter_var($timestamp, FILTER_VALIDATE_INT);

        if ($value === false || abs(time() - $value) > $this->timestampToleranceSeconds) {
            throw new GatewayProtocolException('EasyPay V2 timestamp is outside the allowed window.');
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
