<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Payment\Services\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcilePaymentOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $orderId) {}

    public function handle(PaymentReconciliationService $reconciliation): void
    {
        $reconciliation->reconcile($this->orderId);
    }
}
