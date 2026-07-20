<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OperationsDeliveryController
{
    public function index(): View
    {
        $webhooks = WebhookDelivery::query()->with('paymentEvent.order')->latest('id')->paginate(40, ['*'], 'webhooks');
        $notifications = NotificationDelivery::query()->with('order')->latest('id')->paginate(40, ['*'], 'notifications');

        return view('operations.deliveries', compact('webhooks', 'notifications'));
    }

    public function retryWebhook(Request $request, WebhookDelivery $delivery): RedirectResponse
    {
        abort_unless($this->queueRetry($delivery), 409);
        $this->audit($request, 'webhook_delivery.retry', $delivery);

        return back()->with('status', 'Webhook 已加入重试队列。');
    }

    public function retryNotification(Request $request, NotificationDelivery $delivery): RedirectResponse
    {
        abort_unless($this->queueRetry($delivery), 409);
        $this->audit($request, 'notification_delivery.retry', $delivery);

        return back()->with('status', '管理员通知已加入重试队列。');
    }

    private function queueRetry(WebhookDelivery|NotificationDelivery $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $locked = $delivery->newQuery()->lockForUpdate()->findOrFail($delivery->getKey());

            if (! in_array($locked->status, ['failed', 'retrying'], true)) {
                return false;
            }

            $locked->status = 'retrying';
            $locked->next_attempt_at = now();
            $locked->last_error = null;
            $locked->save();

            return true;
        }, 3);
    }

    private function audit(Request $request, string $action, WebhookDelivery|NotificationDelivery $delivery): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $request->user()->getKey(),
            'request_id' => $request->header('X-Request-ID'),
            'action' => $action,
            'subject_type' => $delivery::class,
            'subject_id' => (string) $delivery->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
