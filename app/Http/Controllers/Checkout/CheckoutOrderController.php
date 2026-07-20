<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Exceptions\PaymentMethodConflictException;
use App\Domain\Payment\Exceptions\PaymentOrderUnavailableException;
use App\Domain\Payment\Services\GatewayPaymentInitializationService;
use App\Domain\Payment\Services\PaymentCancellationService;
use App\Domain\Payment\Services\PaymentOrderRecoveryService;
use App\Domain\Payment\Support\PaymentOrderTransformer;
use App\Http\Requests\Checkout\InitializeCheckoutPaymentRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final readonly class CheckoutOrderController
{
    public function __construct(
        private PaymentOrderRecoveryService $recovery,
        private GatewayPaymentInitializationService $initializer,
        private PaymentCancellationService $cancellation,
    ) {}

    public function show(string $token): JsonResponse
    {
        try {
            $order = $this->recovery->restore($token);
        } catch (PaymentOrderUnavailableException) {
            return ApiResponse::error('ORDER_NOT_FOUND', '订单不存在或恢复令牌已失效。', 404);
        }

        return ApiResponse::success(PaymentOrderTransformer::checkout($order));
    }

    public function initialize(InitializeCheckoutPaymentRequest $request, string $token): JsonResponse
    {
        try {
            $order = $this->recovery->restore($token);
            $order = $this->initializer->initialize(
                $order,
                PaymentMethod::from($request->validated('payment_method')),
            );
        } catch (PaymentOrderUnavailableException) {
            return ApiResponse::error('ORDER_NOT_FOUND', '订单不存在或恢复令牌已失效。', 404);
        } catch (PaymentMethodConflictException) {
            return ApiResponse::error('PAYMENT_METHOD_LOCKED', '支付方式已锁定，不能修改。', 409);
        }

        return ApiResponse::success(PaymentOrderTransformer::checkout($order));
    }

    public function cancel(string $token): JsonResponse
    {
        try {
            $order = $this->recovery->restore($token);
            $cancelled = $this->cancellation->cancel($order, 'payer_cancelled');
        } catch (PaymentOrderUnavailableException) {
            return ApiResponse::error('ORDER_NOT_CANCELLABLE', '当前订单不可取消。', 409);
        }

        $data = PaymentOrderTransformer::checkout($cancelled->order);
        $data['channel_order_closed'] = $cancelled->channelOrderClosed;

        return ApiResponse::success($data);
    }
}
