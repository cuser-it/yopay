<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Delivery\Services\SafeHttpDispatcher;
use App\Domain\Delivery\Support\WebhookSignature;
use App\Models\WebhookDelivery;
use App\Models\WebhookDeliveryAttempt;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(SafeHttpDispatcher $http, ConfigRepository $config): void
    {
        $delivery = $this->claim();

        if ($delivery === null) {
            return;
        }

        $attemptedAt = now();

        try {
            $event = $delivery->paymentEvent;

            if ($event === null) {
                throw new RuntimeException('Webhook delivery payment event is unavailable.');
            }

            $body = json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $timestamp = (string) time();
            $signature = WebhookSignature::sign($timestamp, $body, (string) $delivery->secret_ciphertext);
            $result = $http->postJson(
                url: $delivery->destination_url,
                body: $body,
                headers: [
                    'Accept' => 'application/json',
                    'X-Webhook-Id' => $event->event_id,
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Signature' => $signature,
                ],
                timeoutSeconds: (int) $config->get('payment.webhooks.timeout_seconds', 10),
            );
        } catch (Throwable $exception) {
            $error = substr($exception::class.': '.$exception->getMessage(), 0, 2000);
            $this->recordAttempt(
                delivery: $delivery,
                requestTimestamp: $attemptedAt,
                responseStatus: null,
                responseSummary: null,
                durationMilliseconds: null,
                error: $error,
            );
            $this->scheduleRetry($delivery, $error, $config);

            return;
        }

        $this->recordAttempt(
            delivery: $delivery,
            requestTimestamp: $attemptedAt,
            responseStatus: $result->status,
            responseSummary: $result->summary,
            durationMilliseconds: $result->durationMilliseconds,
            error: null,
        );

        if ($result->successful) {
            $this->markDelivered($delivery, $result->status, $result->summary);

            return;
        }

        $this->scheduleRetry(
            delivery: $delivery,
            error: 'HTTP '.$result->status,
            config: $config,
            responseStatus: $result->status,
            responseSummary: $result->summary,
        );
    }

    private function claim(): ?WebhookDelivery
    {
        return DB::transaction(function (): ?WebhookDelivery {
            $delivery = WebhookDelivery::query()->lockForUpdate()->find($this->deliveryId);

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

            return $delivery->load(['paymentEvent', 'endpoint']);
        }, 3);
    }

    private function recordAttempt(
        WebhookDelivery $delivery,
        DateTimeInterface $requestTimestamp,
        ?int $responseStatus,
        ?string $responseSummary,
        ?int $durationMilliseconds,
        ?string $error,
    ): void {
        WebhookDeliveryAttempt::query()->create([
            'webhook_delivery_id' => $delivery->getKey(),
            'attempt_no' => $delivery->attempt_count,
            'request_timestamp' => $requestTimestamp,
            'response_status' => $responseStatus,
            'response_summary' => $responseSummary,
            'duration_ms' => $durationMilliseconds,
            'error' => $error,
            'attempted_at' => now(),
        ]);
    }

    private function markDelivered(WebhookDelivery $delivery, int $status, string $summary): void
    {
        $updated = WebhookDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', 'processing')
            ->where('attempt_count', $delivery->attempt_count)
            ->update([
                'status' => 'delivered',
                'next_attempt_at' => null,
                'delivered_at' => now(),
                'response_status' => $status,
                'response_summary' => $summary,
                'last_error' => null,
            ]);

        if ($updated === 1 && $delivery->endpoint !== null) {
            $delivery->endpoint->forceFill([
                'last_success_at' => now(),
            ])->save();
        }
    }

    private function scheduleRetry(
        WebhookDelivery $delivery,
        string $error,
        ConfigRepository $config,
        ?int $responseStatus = null,
        ?string $responseSummary = null,
    ): void {
        $delays = $config->get('payment.webhooks.retry_delays_seconds', []);
        $delay = is_array($delays) ? ($delays[$delivery->attempt_count - 1] ?? null) : null;
        $updated = WebhookDelivery::query()
            ->whereKey($delivery->getKey())
            ->where('status', 'processing')
            ->where('attempt_count', $delivery->attempt_count)
            ->update([
                'status' => $delay === null ? 'failed' : 'retrying',
                'next_attempt_at' => $delay === null ? null : now()->addSeconds((int) $delay),
                'response_status' => $responseStatus,
                'response_summary' => $responseSummary,
                'last_error' => substr($error, 0, 2000),
            ]);

        if ($updated === 1 && $delivery->endpoint !== null) {
            $delivery->endpoint->forceFill([
                'last_failure_at' => now(),
            ])->save();
        }
    }
}
