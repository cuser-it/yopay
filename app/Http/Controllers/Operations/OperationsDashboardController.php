<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Domain\Payment\Enums\PaymentStatus;
use App\Models\NotificationDelivery;
use App\Models\PaymentOrder;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;

final class OperationsDashboardController
{
    public function __invoke(): View
    {
        $metrics = [
            'paid_today' => PaymentOrder::query()->where('status', PaymentStatus::Paid->value)->whereDate('paid_at', today())->count(),
            'abnormal_open' => PaymentOrder::query()->whereIn('status', [PaymentStatus::AmountMismatch->value, PaymentStatus::PaidAfterCancel->value])->count(),
            'webhook_failures' => WebhookDelivery::query()->where('status', 'failed')->count(),
            'notification_failures' => NotificationDelivery::query()->where('status', 'failed')->count(),
        ];
        $orders = PaymentOrder::query()->latest('id')->limit(12)->get();

        return view('operations.dashboard', compact('metrics', 'orders'));
    }
}
