<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Developer\Support\DeveloperUrlPolicy;
use App\Models\NotificationDelivery;
use App\Models\PaymentEvent;
use App\Models\PaymentOrder;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;

final readonly class PaymentEventOutboxService
{
    public function __construct(
        private ConfigRepository $config,
        private DeveloperUrlPolicy $urlPolicy,
    ) {}

    public function create(PaymentOrder $order, string $eventType): PaymentEvent
    {
        $occurredAt = now()->toImmutable();
        $eventId = (string) Str::ulid();
        $event = PaymentEvent::query()->create([
            'event_id' => $eventId,
            'order_id' => $order->getKey(),
            'application_id' => $order->application_id,
            'event_type' => $eventType,
            'payload' => $this->payload($order, $eventId, $eventType, $occurredAt->toAtomString()),
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
        ]);

        $this->createWebhookDeliveries($event, $order, $eventType);
        $this->createNotificationDeliveries($event, $order);

        return $event;
    }

    private function createWebhookDeliveries(PaymentEvent $event, PaymentOrder $order, string $eventType): void
    {
        if ($order->application_id === null) {
            return;
        }

        WebhookEndpoint::query()
            ->where('application_id', $order->application_id)
            ->where('enabled', true)
            ->get()
            ->filter(static fn (WebhookEndpoint $endpoint): bool => in_array($eventType, $endpoint->subscribed_events ?? [], true))
            ->each(function (WebhookEndpoint $endpoint) use ($event): void {
                $this->createWebhookDelivery(
                    event: $event,
                    url: $endpoint->url,
                    secret: (string) $endpoint->secret_ciphertext,
                    endpointId: $endpoint->getKey(),
                );
            });

        if ($order->notify_url !== null && $order->notify_secret_ciphertext !== null) {
            $this->createWebhookDelivery(
                event: $event,
                url: $order->notify_url,
                secret: (string) $order->notify_secret_ciphertext,
                endpointId: null,
            );
        }
    }

    private function createWebhookDelivery(PaymentEvent $event, string $url, string $secret, ?int $endpointId): void
    {
        WebhookDelivery::query()->firstOrCreate([
            'payment_event_id' => $event->getKey(),
            'destination_hash' => hash('sha256', $this->urlPolicy->canonicalize($url)),
        ], [
            'webhook_endpoint_id' => $endpointId,
            'destination_url' => $url,
            'secret_ciphertext' => $secret,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => now(),
        ]);
    }

    private function createNotificationDeliveries(PaymentEvent $event, PaymentOrder $order): void
    {
        $targets = $this->config->get('payment.notifications.targets', []);

        if (! is_array($targets)) {
            return;
        }

        foreach ($targets as $target) {
            if (! is_array($target) || empty($target['channel']) || empty($target['destination'])) {
                continue;
            }

            $channel = (string) $target['channel'];
            $destination = (string) $target['destination'];

            NotificationDelivery::query()->firstOrCreate([
                'payment_event_id' => $event->getKey(),
                'channel' => $channel,
                'destination_hash' => hash('sha256', $destination),
            ], [
                'order_id' => $order->getKey(),
                'destination_ciphertext' => $destination,
                'status' => 'pending',
                'attempt_count' => 0,
                'next_attempt_at' => now(),
            ]);
        }
    }

    private function payload(PaymentOrder $order, string $eventId, string $eventType, string $createdAt): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'created_at' => $createdAt,
            'data' => [
                'order_no' => $order->order_no,
                'external_order_no' => $order->external_order_no,
                'status' => $order->status->value,
                'expected_amount_cents' => $order->expected_amount_cents,
                'paid_amount_cents' => $order->paid_amount_cents,
                'amount_difference_cents' => $order->amount_difference_cents,
                'currency' => $order->currency,
                'payment_type' => $order->payment_method?->value,
                'channel_trade_no' => $order->gateway_trade_no,
                'created_at' => $order->created_at?->toAtomString(),
                'paid_at' => $order->paid_at?->toAtomString(),
                'metadata' => $order->metadata ?? [],
            ],
        ];
    }
}
