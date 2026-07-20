<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Developer\Support\DeveloperUrlPolicy;
use App\Domain\Payment\Data\CreatePaymentOrderData;
use App\Domain\Payment\Enums\OrderSource;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Exceptions\IdempotencyConflictException;
use App\Domain\Payment\Exceptions\PaymentOrderUnavailableException;
use App\Domain\Payment\Services\PaymentCancellationService;
use App\Domain\Payment\Services\PaymentOrderCreationService;
use App\Domain\Payment\Support\PaymentOrderTransformer;
use App\Domain\Payment\ValueObjects\Money;
use App\Http\Requests\Developer\CreateDeveloperPaymentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\DeveloperApiCredential;
use App\Models\DeveloperApplication;
use App\Models\PaymentOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class DeveloperPaymentController
{
    public function __construct(
        private PaymentOrderCreationService $orders,
        private PaymentCancellationService $cancellation,
        private DeveloperUrlPolicy $urlPolicy,
    ) {}

    public function store(CreateDeveloperPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $application = $this->application($request);
        $credential = $this->credential($request);
        $amountCents = (int) $validated['amount_cents'];

        if (
            ($application->minimum_amount_cents !== null && $amountCents < $application->minimum_amount_cents)
            || ($application->maximum_amount_cents !== null && $amountCents > $application->maximum_amount_cents)
        ) {
            return ApiResponse::error('INVALID_AMOUNT', '订单金额超出应用允许范围。', 422);
        }

        try {
            $this->urlPolicy->assertNotifyUrlAllowed($application, $validated['notify_url'] ?? null);
            $this->urlPolicy->assertReturnUrlAllowed($application, $validated['return_url'] ?? null);
            $created = $this->orders->create(new CreatePaymentOrderData(
                source: OrderSource::DeveloperApi,
                amount: new Money($amountCents, $validated['currency']),
                subject: $validated['subject'],
                paymentMethod: isset($validated['payment_type'])
                    ? PaymentMethod::from($validated['payment_type'])
                    : null,
                clientIp: $request->ip() ?? '0.0.0.0',
                application: $application,
                externalOrderNumber: $validated['external_order_no'],
                description: $validated['description'] ?? null,
                notifyUrl: $validated['notify_url'] ?? null,
                notifySecret: (string) $credential->secret_ciphertext,
                returnUrl: $validated['return_url'] ?? null,
                metadata: $validated['metadata'] ?? [],
            ), $validated['idempotency_key']);
        } catch (IdempotencyConflictException) {
            return ApiResponse::error('IDEMPOTENCY_CONFLICT', '幂等键或外部订单号对应的参数不一致。', 409);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error('INVALID_CALLBACK_URL', $exception->getMessage(), 422);
        }

        $data = PaymentOrderTransformer::developer($created->order, $created->checkoutToken);

        if ($created->order->status === PaymentStatus::Failed) {
            return ApiResponse::error('CHANNEL_UNAVAILABLE', '支付通道暂不可用，请使用相同幂等键查询或重试。', 503, $data);
        }

        return ApiResponse::success($data, $created->replayed ? 200 : 201, [
            'idempotent_replay' => $created->replayed,
        ]);
    }

    public function show(Request $request, string $orderNo): JsonResponse
    {
        $order = $this->findOrder($this->application($request), $orderNo);

        if ($order === null) {
            return ApiResponse::error('ORDER_NOT_FOUND', '订单不存在或无权访问。', 404);
        }

        return ApiResponse::success(PaymentOrderTransformer::developer($order));
    }

    public function cancel(Request $request, string $orderNo): JsonResponse
    {
        $order = $this->findOrder($this->application($request), $orderNo);

        if ($order === null) {
            return ApiResponse::error('ORDER_NOT_FOUND', '订单不存在或无权访问。', 404);
        }

        try {
            $cancelled = $this->cancellation->cancel($order, 'developer_api_cancelled');
        } catch (PaymentOrderUnavailableException) {
            return ApiResponse::error('ORDER_NOT_CANCELLABLE', '当前订单状态不可取消。', 409);
        }

        $data = PaymentOrderTransformer::developer($cancelled->order);
        $data['channel_order_closed'] = $cancelled->channelOrderClosed;

        return ApiResponse::success($data);
    }

    private function application(Request $request): DeveloperApplication
    {
        return $request->attributes->get('developer_application');
    }

    private function credential(Request $request): DeveloperApiCredential
    {
        return $request->attributes->get('developer_credential');
    }

    private function findOrder(DeveloperApplication $application, string $orderNo): ?PaymentOrder
    {
        return PaymentOrder::query()
            ->where('application_id', $application->getKey())
            ->where('order_no', $orderNo)
            ->first();
    }
}
