<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Data\CreatedPaymentOrder;
use App\Domain\Payment\Data\CreatePaymentOrderData;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\IdempotencyConflictException;
use App\Domain\Payment\Support\CanonicalPayload;
use App\Domain\Payment\Support\CheckoutToken;
use App\Models\PaymentIdempotencyKey;
use App\Models\PaymentOrder;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class PaymentOrderCreationService
{
    public function __construct(
        private ConfigRepository $config,
        private GatewayRegistry $gateways,
        private PaymentStateTransitionService $transitions,
        private GatewayPaymentInitializationService $initializer,
    ) {}

    public function create(CreatePaymentOrderData $data, string $idempotencyKey): CreatedPaymentOrder
    {
        $scopeKey = $data->source === OrderSource::DeveloperApi
            ? 'application:'.$data->application?->getKey()
            : 'public-checkout';
        $keyHash = hash('sha256', $idempotencyKey);
        $fingerprint = $this->fingerprint($data);

        try {
            $created = DB::transaction(function () use ($data, $scopeKey, $keyHash, $fingerprint): CreatedPaymentOrder {
            $idempotency = PaymentIdempotencyKey::query()
                ->where('scope_key', $scopeKey)
                ->where('idempotency_key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($idempotency !== null) {
                if (! hash_equals($idempotency->request_fingerprint, $fingerprint)) {
                    throw new IdempotencyConflictException('The idempotency key was already used with different payment parameters.');
                }

                $order = PaymentOrder::query()->findOrFail($idempotency->order_id);

                return new CreatedPaymentOrder($order, (string) $order->checkout_token_ciphertext, true);
            }

            $existingOrder = $this->findExistingDeveloperOrder($data);

            if ($existingOrder !== null) {
                $originalFingerprint = PaymentIdempotencyKey::query()
                    ->where('scope_key', $scopeKey)
                    ->where('order_id', $existingOrder->getKey())
                    ->oldest('id')
                    ->value('request_fingerprint');

                if (! is_string($originalFingerprint) || ! hash_equals($originalFingerprint, $fingerprint)) {
                    throw new IdempotencyConflictException('The external order number already exists with different payment parameters.');
                }

                PaymentIdempotencyKey::query()->create([
                    'scope_key' => $scopeKey,
                    'idempotency_key_hash' => $keyHash,
                    'request_fingerprint' => $fingerprint,
                    'order_id' => $existingOrder->getKey(),
                    'expires_at' => now()->addDay(),
                ]);

                return new CreatedPaymentOrder($existingOrder, (string) $existingOrder->checkout_token_ciphertext, true);
            }

            $now = now()->toImmutable();
            $checkoutToken = CheckoutToken::generate();
            $gateway = $this->gateways->default();
            $order = PaymentOrder::query()->create([
                'order_no' => $this->generateOrderNumber(),
                'application_id' => $data->application?->getKey(),
                'external_order_no' => $data->externalOrderNumber,
                'source' => $data->source,
                'status' => PaymentStatus::Creating,
                'version' => 1,
                'expected_amount_cents' => $data->amount->cents,
                'currency' => $data->amount->currency,
                'subject' => $data->subject,
                'description' => $data->description,
                'payment_method' => $data->paymentMethod,
                'gateway' => 'easypay',
                'gateway_api_version' => $gateway->apiVersion(),
                'checkout_token_hash' => CheckoutToken::hash($checkoutToken),
                'checkout_token_ciphertext' => $checkoutToken,
                'notify_url' => $data->notifyUrl,
                'notify_secret_ciphertext' => $data->notifySecret,
                'return_url' => $data->returnUrl,
                'client_ip' => $data->clientIp,
                'metadata' => $data->metadata === [] ? null : $data->metadata,
                'status_changed_at' => $now,
                'expires_at' => $now->addMinutes((int) $this->config->get('payment.order_expiration_minutes', 15)),
                'checkout_token_expires_at' => $now->addMinutes((int) $this->config->get('payment.checkout_token_ttl_minutes', 120)),
            ]);

            PaymentIdempotencyKey::query()->create([
                'scope_key' => $scopeKey,
                'idempotency_key_hash' => $keyHash,
                'request_fingerprint' => $fingerprint,
                'order_id' => $order->getKey(),
                'expires_at' => $now->addDay(),
            ]);
            $this->transitions->recordInitial($order, 'order_created');

            return new CreatedPaymentOrder($order, $checkoutToken, false);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $created = $this->recoverConcurrentCreation($data, $scopeKey, $keyHash, $fingerprint);

            if ($created === null) {
                throw $exception;
            }
        }

        if ($created->order->payment_method !== null && $created->order->status === PaymentStatus::Creating) {
            $order = $this->initializer->initialize($created->order, $created->order->payment_method);

            return new CreatedPaymentOrder($order, $created->checkoutToken, $created->replayed);
        }

        return $created;
    }

    private function recoverConcurrentCreation(
        CreatePaymentOrderData $data,
        string $scopeKey,
        string $keyHash,
        string $fingerprint,
    ): ?CreatedPaymentOrder {
        return DB::transaction(function () use ($data, $scopeKey, $keyHash, $fingerprint): ?CreatedPaymentOrder {
            $idempotency = PaymentIdempotencyKey::query()
                ->where('scope_key', $scopeKey)
                ->where('idempotency_key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($idempotency !== null) {
                if (! hash_equals($idempotency->request_fingerprint, $fingerprint)) {
                    throw new IdempotencyConflictException('The idempotency key was already used with different payment parameters.');
                }

                $order = PaymentOrder::query()->findOrFail($idempotency->order_id);

                return new CreatedPaymentOrder($order, (string) $order->checkout_token_ciphertext, true);
            }

            $existingOrder = $this->findExistingDeveloperOrder($data);

            if ($existingOrder === null) {
                return null;
            }

            $originalFingerprint = PaymentIdempotencyKey::query()
                ->where('scope_key', $scopeKey)
                ->where('order_id', $existingOrder->getKey())
                ->oldest('id')
                ->value('request_fingerprint');

            if (! is_string($originalFingerprint) || ! hash_equals($originalFingerprint, $fingerprint)) {
                throw new IdempotencyConflictException('The external order number already exists with different payment parameters.');
            }

            PaymentIdempotencyKey::query()->insertOrIgnore([
                'scope_key' => $scopeKey,
                'idempotency_key_hash' => $keyHash,
                'request_fingerprint' => $fingerprint,
                'order_id' => $existingOrder->getKey(),
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stored = PaymentIdempotencyKey::query()
                ->where('scope_key', $scopeKey)
                ->where('idempotency_key_hash', $keyHash)
                ->lockForUpdate()
                ->first();

            if ($stored === null) {
                return null;
            }

            if (! hash_equals($stored->request_fingerprint, $fingerprint)) {
                throw new IdempotencyConflictException('The idempotency key was already used with different payment parameters.');
            }

            return new CreatedPaymentOrder($existingOrder, (string) $existingOrder->checkout_token_ciphertext, true);
        }, 3);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23505'
            || ($sqlState === '23000' && $driverCode === 1062)
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    private function findExistingDeveloperOrder(CreatePaymentOrderData $data): ?PaymentOrder
    {
        if ($data->application === null || $data->externalOrderNumber === null) {
            return null;
        }

        return PaymentOrder::query()
            ->where('application_id', $data->application->getKey())
            ->where('external_order_no', $data->externalOrderNumber)
            ->lockForUpdate()
            ->first();
    }

    private function fingerprint(CreatePaymentOrderData $data): string
    {
        return CanonicalPayload::hash([
            'application_id' => $data->application?->getKey(),
            'external_order_no' => $data->externalOrderNumber,
            'source' => $data->source->value,
            'amount_cents' => $data->amount->cents,
            'currency' => $data->amount->currency,
            'subject' => $data->subject,
            'description' => $data->description,
            'payment_method' => $data->paymentMethod?->value,
            'notify_url' => $data->notifyUrl,
            'return_url' => $data->returnUrl,
            'metadata' => $data->metadata,
        ]);
    }

    private function generateOrderNumber(): string
    {
        return 'PAY'.now()->format('Ymd').strtoupper((string) Str::ulid());
    }
}
