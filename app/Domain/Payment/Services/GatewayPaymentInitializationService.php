<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Gateway\Data\PaymentCreateRequest;
use App\Domain\Gateway\Exceptions\GatewayConfigurationException;
use App\Domain\Gateway\GatewayRegistry;
use App\Domain\Payment\Data\GatewayCreationClaim;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\PaymentMethodConflictException;
use App\Domain\Payment\Support\GatewayCreationLease;
use App\Domain\Payment\ValueObjects\Money;
use App\Models\PaymentOrder;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class GatewayPaymentInitializationService
{
    public function __construct(
        private GatewayCreationLease $creationLease,
        private GatewayRegistry $gateways,
        private UrlGenerator $url,
        private PaymentStateTransitionService $transitions,
    ) {}

    public function initialize(PaymentOrder $order, PaymentMethod $method): PaymentOrder
    {
        $claim = DB::transaction(function () use ($order, $method): GatewayCreationClaim {
            $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->status->isTerminal()) {
                return new GatewayCreationClaim($locked, false);
            }

            if ($locked->payment_method !== null && $locked->payment_method !== $method) {
                throw new PaymentMethodConflictException('The payment method is immutable after initialization.');
            }

            if ($locked->status === PaymentStatus::Pending && $locked->payment_action_payload !== null) {
                return new GatewayCreationClaim($locked, false);
            }

            if (
                $locked->payment_method !== null
                && $this->creationLease->isActive($locked->gateway_create_last_attempt_at)
            ) {
                return new GatewayCreationClaim($locked, false);
            }

            $locked->payment_method = $method;
            $locked->gateway_create_attempt_count++;
            $locked->gateway_create_last_attempt_at = now();
            $locked->gateway_last_error = null;
            $locked->save();

            return new GatewayCreationClaim($locked, true, $locked->gateway_create_attempt_count);
        }, 3);

        $order = $claim->order;

        if (! $claim->acquired) {
            return $order;
        }

        $gateway = $this->gateways->forVersion($order->gateway_api_version);

        try {
            $result = $gateway->createPayment(new PaymentCreateRequest(
                localOrderNumber: $order->order_no,
                method: $method,
                expectedAmount: new Money($order->expected_amount_cents, $order->currency),
                subject: $order->subject,
                notifyUrl: $this->url->route('payments.callback', ['version' => $order->gateway_api_version->value]),
                returnUrl: $this->url->route('payments.return', ['version' => $order->gateway_api_version->value]),
                clientIp: $order->client_ip ?? '127.0.0.1',
                metadata: $order->metadata ?? [],
            ));

            return DB::transaction(function () use ($claim, $order, $result): PaymentOrder {
                $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());

                if ($locked->gateway_create_attempt_count !== $claim->attemptNumber) {
                    return $locked;
                }

                $locked->gateway_order_no = $result->gatewayOrderNumber;
                $locked->payment_action_type = $result->action->type;
                $locked->payment_action_payload = $result->action->payload;
                $locked->payment_direct_url = $result->action->directUrl;
                $locked->gateway_last_error = null;
                $locked->save();

                if ($locked->status === PaymentStatus::Creating) {
                    $this->transitions->transition($locked, PaymentStatus::Pending, 'gateway_created', [
                        'gateway_order_no' => $result->gatewayOrderNumber,
                    ]);
                }

                return $locked->refresh();
            }, 3);
        } catch (GatewayConfigurationException $exception) {
            return DB::transaction(function () use ($claim, $order, $exception): PaymentOrder {
                $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());

                if ($locked->gateway_create_attempt_count !== $claim->attemptNumber) {
                    return $locked;
                }

                $locked->gateway_last_error = $this->safeError($exception);
                $locked->save();

                if ($locked->status === PaymentStatus::Creating) {
                    $this->transitions->transition($locked, PaymentStatus::Failed, 'gateway_configuration_failed');
                }

                return $locked->refresh();
            }, 3);
        } catch (Throwable $exception) {
            Log::warning('Payment gateway creation needs recovery.', [
                'order_no' => $order->order_no,
                'gateway_version' => $order->gateway_api_version->value,
                'exception' => $exception::class,
            ]);

            return DB::transaction(function () use ($claim, $order, $exception): PaymentOrder {
                $locked = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());

                if ($locked->gateway_create_attempt_count !== $claim->attemptNumber) {
                    return $locked;
                }

                $locked->gateway_last_error = $this->safeError($exception);
                $locked->save();

                return $locked;
            }, 3);
        }
    }

    private function safeError(Throwable $exception): string
    {
        return substr($exception::class.': '.$exception->getMessage(), 0, 2000);
    }
}
