<?php

declare(strict_types=1);

namespace App\Domain\Gateway\EasyPay\Concerns;

use App\Domain\Gateway\Data\PaymentAction;
use App\Domain\Gateway\Enums\GatewayPaymentStatus;
use App\Domain\Gateway\Enums\PaymentActionType;
use App\Domain\Gateway\Exceptions\GatewayProtocolException;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\ValueObjects\Money;

trait MapsEasyPayData
{
    private function mapPaymentMethod(string $method): PaymentMethod
    {
        return PaymentMethod::tryFrom($method)
            ?? throw new GatewayProtocolException('EasyPay returned an unsupported payment method.');
    }

    private function mapGatewayStatus(string|int|null $status): GatewayPaymentStatus
    {
        return match ((string) $status) {
            '1', 'TRADE_SUCCESS', 'TRADE_FINISHED', 'SUCCESS', 'success', 'paid' => GatewayPaymentStatus::Paid,
            '2', 'REFUNDED', 'refunded' => GatewayPaymentStatus::Refunded,
            '0', 'NOTPAY', 'pending', 'WAIT_BUYER_PAY' => GatewayPaymentStatus::Pending,
            'CLOSED', 'closed' => GatewayPaymentStatus::Closed,
            default => GatewayPaymentStatus::Unknown,
        };
    }

    private function mapPaymentAction(array $payload): PaymentAction
    {
        $payType = (string) ($payload['pay_type'] ?? '');
        $payInfo = (string) ($payload['pay_info'] ?? '');

        if ($payType !== '' && $payInfo !== '') {
            return match ($payType) {
                'qrcode' => new PaymentAction(PaymentActionType::QrCode, $payInfo, $payInfo),
                'jump' => new PaymentAction(PaymentActionType::Redirect, $payInfo, $payInfo),
                'urlscheme' => new PaymentAction(PaymentActionType::UrlScheme, $payInfo, $payInfo),
                'html' => throw new GatewayProtocolException('EasyPay returned an unsupported HTML payment action.'),
                default => throw new GatewayProtocolException('EasyPay returned an unsupported payment action.'),
            };
        }

        foreach (['qrcode', 'qr_code', 'code_url'] as $key) {
            if (! empty($payload[$key])) {
                $value = (string) $payload[$key];

                return new PaymentAction(PaymentActionType::QrCode, $value, (string) ($payload['payurl'] ?? $value));
            }
        }

        foreach (['payurl', 'pay_url', 'urlscheme'] as $key) {
            if (! empty($payload[$key])) {
                $value = (string) $payload[$key];
                $type = $key === 'urlscheme' ? PaymentActionType::UrlScheme : PaymentActionType::Redirect;

                return new PaymentAction($type, $value, $value);
            }
        }

        throw new GatewayProtocolException('EasyPay response did not include a payment action.');
    }

    private function parseMoney(string $amount, string $currency = 'CNY'): Money
    {
        try {
            return Money::fromDecimalString($amount, $currency);
        } catch (\InvalidArgumentException $exception) {
            throw new GatewayProtocolException('EasyPay returned an invalid amount.', previous: $exception);
        }
    }
}
