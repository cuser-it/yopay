<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Domain\Payment\Data\CreatePaymentOrderData;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Exceptions\IdempotencyConflictException;
use App\Domain\Payment\Services\PaymentOrderCreationService;
use App\Domain\Payment\Support\PaymentOrderTransformer;
use App\Domain\Payment\ValueObjects\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CreatePublicPaymentRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;

final readonly class PublicPaymentController
{
    public function __construct(
        private PaymentOrderCreationService $orders,
        private ConfigRepository $config,
    ) {}

    public function store(CreatePublicPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $amount = Money::fromDecimalString($validated['amount'], 'CNY');
        $minimum = (int) $this->config->get('payment.public_minimum_amount_cents', 1);
        $maximum = (int) $this->config->get('payment.public_maximum_amount_cents', 100000000);

        if ($amount->cents < $minimum || $amount->cents > $maximum) {
            return ApiResponse::error('INVALID_AMOUNT', '收款金额超出允许范围。', 422);
        }

        try {
            $created = $this->orders->create(new CreatePaymentOrderData(
                source: OrderSource::PublicCheckout,
                amount: $amount,
                subject: '在线收款',
                paymentMethod: PaymentMethod::from($validated['payment_method']),
                clientIp: $request->ip() ?? '0.0.0.0',
            ), $validated['idempotency_key']);
        } catch (IdempotencyConflictException) {
            return ApiResponse::error('IDEMPOTENCY_CONFLICT', '该幂等键已用于不同的订单参数。', 409);
        }

        $data = PaymentOrderTransformer::checkout($created->order);
        $data['checkout_token'] = $created->checkoutToken;
        $data['checkout_url'] = route('checkout.resume', ['token' => $created->checkoutToken]);

        return ApiResponse::success($data, $created->replayed ? 200 : 201, [
            'idempotent_replay' => $created->replayed,
        ]);
    }
}
