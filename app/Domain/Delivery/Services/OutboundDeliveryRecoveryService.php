<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Models\NotificationDelivery;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class OutboundDeliveryRecoveryService
{
    public function __construct(private ConfigRepository $config) {}

    public function recoverStaleProcessing(): int
    {
        $timeoutSeconds = max(30, (int) $this->config->get('payment.webhooks.processing_timeout_seconds', 120));
        $staleBefore = now()->subSeconds($timeoutSeconds);
        $attributes = [
            'status' => 'retrying',
            'next_attempt_at' => now(),
            'last_error' => 'Recovered after an interrupted delivery attempt.',
        ];

        $webhooks = WebhookDelivery::query()
            ->where('status', 'processing')
            ->whereNotNull('last_attempt_at')
            ->where('last_attempt_at', '<=', $staleBefore)
            ->update($attributes);
        $notifications = NotificationDelivery::query()
            ->where('status', 'processing')
            ->whereNotNull('last_attempt_at')
            ->where('last_attempt_at', '<=', $staleBefore)
            ->update($attributes);

        return $webhooks + $notifications;
    }
}
