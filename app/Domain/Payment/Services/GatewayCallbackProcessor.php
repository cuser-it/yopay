<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\Exceptions\InvalidGatewaySignatureException;
use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Support\CanonicalPayload;
use App\Models\PaymentCallback;
use App\Models\PaymentOrder;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class GatewayCallbackProcessor
{
    public function __construct(
        private GatewayRegistry $gateways,
        private ConfigRepository $config,
        private PaymentConfirmationService $confirmation,
    ) {}

    public function process(GatewayApiVersion $version, array $payload): PaymentCallback
    {
        $fingerprint = CanonicalPayload::hash([
            'gateway' => 'easypay',
            'version' => $version->value,
            'payload' => $payload,
        ]);
        $callback = PaymentCallback::query()->firstOrCreate([
            'fingerprint' => $fingerprint,
        ], [
            'request_id' => (string) Str::ulid(),
            'gateway' => 'easypay',
            'gateway_api_version' => $version,
            'gateway_trade_no' => isset($payload['trade_no']) ? (string) $payload['trade_no'] : null,
            'signature_valid' => false,
            'merchant_valid' => false,
            'processing_status' => 'received',
            'sanitized_payload' => $this->sanitize($payload),
            'received_at' => now(),
        ]);
        $claimed = $this->claim($callback);

        if ($claimed === null) {
            return $callback->fresh() ?? $callback;
        }

        $callback = $claimed;
        $expectedMerchant = (string) $this->config->get('payment.gateway.easypay.merchant_id', '');
        $receivedMerchant = (string) ($payload['pid'] ?? '');
        $merchantValid = $expectedMerchant !== '' && hash_equals($expectedMerchant, $receivedMerchant);
        $callback->merchant_valid = $merchantValid;
        $callback->save();

        if (! $merchantValid) {
            return $this->fail($callback, 'merchant_mismatch', 'MERCHANT_MISMATCH');
        }

        try {
            $verified = $this->gateways->forVersion($version)->verifyCallback($payload);
            $callback->signature_valid = true;
            $callback->gateway_trade_no = $verified->gatewayTradeNumber;
            $callback->save();
        } catch (InvalidGatewaySignatureException) {
            return $this->fail($callback, 'invalid_signature', 'INVALID_SIGNATURE');
        } catch (Throwable $exception) {
            Log::warning('EasyPay callback verification failed.', [
                'callback_id' => $callback->getKey(),
                'exception' => $exception::class,
            ]);

            return $this->fail($callback, 'verification_failed', 'INVALID_CALLBACK');
        }

        try {
            return DB::transaction(function () use ($callback, $verified): PaymentCallback {
                $lockedOrder = PaymentOrder::query()
                    ->where('order_no', $verified->localOrderNumber)
                    ->lockForUpdate()
                    ->first();

                if ($lockedOrder === null) {
                    return $this->fail($callback, 'order_not_found', 'ORDER_NOT_FOUND');
                }

                $callback->order_id = $lockedOrder->getKey();
                $status = $this->confirmation->confirmLocked(
                    order: $lockedOrder,
                    paidAmount: $verified->paidAmount,
                    gatewayTradeNumber: $verified->gatewayTradeNumber,
                    method: $verified->method,
                    source: 'gateway_callback',
                    paidAt: $verified->paidAt,
                );
                $callback->processing_status = 'processed';
                $callback->outcome = $status->value;
                $callback->error_code = null;
                $callback->processed_at = now();
                $callback->save();

                return $callback;
            }, 3);
        } catch (Throwable $exception) {
            Log::error('Verified EasyPay callback could not be applied.', [
                'callback_id' => $callback->getKey(),
                'order_no' => $verified->localOrderNumber,
                'exception' => $exception::class,
            ]);

            return $this->fail($callback, 'processing_failed', class_basename($exception));
        }
    }

    private function claim(PaymentCallback $callback): ?PaymentCallback
    {
        return DB::transaction(function () use ($callback): ?PaymentCallback {
            $locked = PaymentCallback::query()->lockForUpdate()->findOrFail($callback->getKey());
            $retryableFailure = $locked->processing_status === 'failed'
                && in_array($locked->outcome, ['verification_failed', 'order_not_found', 'processing_failed'], true);
            $timeout = max(30, (int) $this->config->get('payment.callback_processing_timeout_seconds', 120));
            $staleProcessing = $locked->processing_status === 'processing'
                && $locked->updated_at !== null
                && $locked->updated_at->lte(now()->subSeconds($timeout));

            if ($locked->processing_status !== 'received' && ! $retryableFailure && ! $staleProcessing) {
                return null;
            }

            $locked->forceFill([
                'processing_status' => 'processing',
                'outcome' => null,
                'error_code' => null,
                'processed_at' => null,
            ])->save();

            return $locked;
        }, 3);
    }

    private function fail(PaymentCallback $callback, string $outcome, string $errorCode): PaymentCallback
    {
        $callback->processing_status = 'failed';
        $callback->outcome = $outcome;
        $callback->error_code = substr($errorCode, 0, 64);
        $callback->processed_at = now();
        $callback->save();

        return $callback;
    }

    private function sanitize(array $payload): array
    {
        $allowed = [
            'pid', 'trade_no', 'out_trade_no', 'type', 'name', 'money',
            'trade_status', 'status', 'timestamp', 'sign_type',
        ];

        return array_intersect_key($payload, array_flip($allowed));
    }
}
