<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Responses\ApiResponse;
use App\Models\DeveloperApplication;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WebhookDeliveryController
{
    public function index(Request $request): JsonResponse
    {
        $application = $this->application($request);
        $deliveries = WebhookDelivery::query()
            ->whereHas('paymentEvent', static fn ($query) => $query->where('application_id', $application->getKey()))
            ->with('paymentEvent:id,event_id,event_type')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(static fn (WebhookDelivery $delivery): array => self::transform($delivery))
            ->all();

        return ApiResponse::success(['items' => $deliveries]);
    }

    public function retry(Request $request, int $deliveryId): JsonResponse
    {
        $application = $this->application($request);
        $delivery = WebhookDelivery::query()
            ->whereKey($deliveryId)
            ->whereHas('paymentEvent', static fn ($query) => $query->where('application_id', $application->getKey()))
            ->first();

        if ($delivery === null) {
            return ApiResponse::error('WEBHOOK_DELIVERY_NOT_FOUND', 'Webhook 投递记录不存在。', 404);
        }

        $queued = DB::transaction(function () use ($delivery): bool {
            $locked = WebhookDelivery::query()->lockForUpdate()->findOrFail($delivery->getKey());

            if (! in_array($locked->status, ['failed', 'retrying'], true)) {
                return false;
            }

            $locked->status = 'retrying';
            $locked->next_attempt_at = now();
            $locked->last_error = null;
            $locked->save();

            return true;
        }, 3);

        if (! $queued) {
            return ApiResponse::error('WEBHOOK_DELIVERY_NOT_RETRYABLE', '当前 Webhook 投递状态不可重试。', 409);
        }

        return ApiResponse::success(['queued' => true]);
    }

    private function application(Request $request): DeveloperApplication
    {
        return $request->attributes->get('developer_application');
    }

    private static function transform(WebhookDelivery $delivery): array
    {
        return [
            'id' => $delivery->getKey(),
            'event_id' => $delivery->paymentEvent?->event_id,
            'event_type' => $delivery->paymentEvent?->event_type,
            'status' => $delivery->status,
            'attempt_count' => $delivery->attempt_count,
            'next_attempt_at' => $delivery->next_attempt_at?->toAtomString(),
            'last_attempt_at' => $delivery->last_attempt_at?->toAtomString(),
            'delivered_at' => $delivery->delivered_at?->toAtomString(),
            'response_status' => $delivery->response_status,
            'response_summary' => $delivery->response_summary,
            'last_error' => $delivery->last_error,
        ];
    }
}
