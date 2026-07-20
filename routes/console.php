<?php

declare(strict_types=1);

use App\Domain\Delivery\Services\OutboundDeliveryRecoveryService;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Services\PaymentOrderExpirationService;
use App\Jobs\DeliverAdministratorNotification;
use App\Jobs\DeliverWebhook;
use App\Jobs\ReconcilePaymentOrder;
use App\Models\ApiRequestNonce;
use App\Models\NotificationDelivery;
use App\Models\PaymentIdempotencyKey;
use App\Models\PaymentOrder;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    app(OutboundDeliveryRecoveryService::class)->recoverStaleProcessing();

    WebhookDelivery::query()
        ->whereIn('status', ['pending', 'retrying'])
        ->where(static function ($query): void {
            $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
        })
        ->orderBy('id')
        ->limit(100)
        ->pluck('id')
        ->each(static fn (int $id) => DeliverWebhook::dispatch($id));

    NotificationDelivery::query()
        ->whereIn('status', ['pending', 'retrying'])
        ->where(static function ($query): void {
            $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
        })
        ->orderBy('id')
        ->limit(100)
        ->pluck('id')
        ->each(static fn (int $id) => DeliverAdministratorNotification::dispatch($id));
})->name('deliver-outbound-notifications')->everyMinute()->withoutOverlapping();

Schedule::call(function (): void {
    PaymentOrder::query()
        ->whereIn('status', [
            PaymentStatus::Creating->value,
            PaymentStatus::Pending->value,
            PaymentStatus::Cancelled->value,
            PaymentStatus::Expired->value,
            PaymentStatus::Paid->value,
        ])
        ->where(static function ($query): void {
            $query->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<=', now()->subMinute());
        })
        ->where('created_at', '>=', now()->subDay())
        ->orderBy('id')
        ->limit(100)
        ->pluck('id')
        ->each(static fn (int $id) => ReconcilePaymentOrder::dispatch($id));
})->name('reconcile-payment-orders')->everyMinute()->withoutOverlapping();

Schedule::call(static fn () => app(PaymentOrderExpirationService::class)->expireDue())
    ->name('expire-payment-orders')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(function (): void {
    ApiRequestNonce::query()->where('expires_at', '<', now())->delete();
    PaymentIdempotencyKey::query()->whereNotNull('expires_at')->where('expires_at', '<', now()->subDay())->delete();
})->name('prune-api-nonces-and-idempotency-keys')->daily()->withoutOverlapping();
