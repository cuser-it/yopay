<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Delivery\Services\SafeHttpDispatcher;
use App\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DeliverAdministratorNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(SafeHttpDispatcher $http, ConfigRepository $config): void
    {
        $delivery = $this->claim();

        if ($delivery === null) {
            return;
        }

        try {
            if (! in_array($delivery->channel, ['http', 'webhook'], true)) {
                throw new RuntimeException('Unsupported notification channel.');
            }

            $event = $delivery->paymentEvent;

            if ($event === null) {
                throw new RuntimeException('Notification payment event is unavailable.');
            }

            $body = json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $result = $http->postJson(
                url: (string) $delivery->destination_ciphertext,
                body: $body,
                headers: [
                    'Accept' => 'application/json',
                    'X-Payment-Event-Id' => $event->event_id,
                    'X-Payment-Event-Type' => $event->event_type,
                ],
                timeoutSeconds: (int) $config->get('payment.webhooks.timeout_seconds', 10),
            );
            $this->finish(
                delivery: $delivery,
                successful: $result->successful,
                responseSummary: 'HTTP '.$result->status.' '.$result->summary,
                error: $result->successful ? null : 'HTTP '.$result->status,
                config: $config,
            );
        } catch (Throwable $exception) {
            $this->finish(
                delivery: $delivery,
                successful: false,
                responseSummary: null,
                error: $exception::class.': '.$exception->getMessage(),
                config: $config,
            );
        }
    }

    private function claim(): ?NotificationDelivery
    {
        return DB::transaction(function (): ?NotificationDelivery {
            $delivery = NotificationDelivery::query()->lockForUpdate()->find($this->deliveryId);

            if (
                $delivery === null
                || ! in_array($delivery->status, ['pending', 'retrying'], true)
                || ($delivery->next_attempt_at !== null && $delivery->next_attempt_at->isFuture())
            ) {
                return null;
            }

            $delivery->status = 'processing';
            $delivery->attempt_count++;
            $delivery->last_attempt_at = now();
            $delivery->save();

            return $delivery->load('paymentEvent');
        }, 3);
    }

    private function finish(
        NotificationDelivery $delivery,
        bool $successful,
        ?string $responseSummary,
        ?string $error,
        ConfigRepository $config,
    ): void {
        if ($successful) {
            NotificationDelivery::query()
                ->whereKey($delivery->getKey())
                ->where('status', 'processing')
                ->where('attempt_count', $delivery->attempt_count)
                ->update([
                    'status' => 'delivered',
                    'next_attempt_at' => null,
                    'delivered_at' => now(),
                    'response_summary' => $responseSummary,
                    'last_error' => null,
                ]);

            return;
        }

        $delays = $config->get('payment.webhooks.retry_delays_seconds', []);
        $delay = is_array($delays) ? ($delays[$delivery->attempt_count - 1] ?? null) : null;
        NotificationDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', 'processing')
            ->where('attempt_count', $delivery->attempt_count)
            ->update([
                'status' => $delay === null ? 'failed' : 'retrying',
                'next_attempt_at' => $delay === null ? null : now()->addSeconds((int) $delay),
                'response_summary' => $responseSummary,
                'last_error' => $error === null ? null : substr($error, 0, 2000),
            ]);
    }
}
