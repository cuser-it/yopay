<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DeveloperPaymentController;
use App\Http\Controllers\Api\V1\WebhookDeliveryController;
use App\Http\Controllers\Api\V1\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', fn (): array => [
        'data' => ['status' => 'ok'],
        'meta' => null,
        'error' => null,
    ])->name('health');

    Route::middleware(['developer.auth', 'throttle:120,1'])->group(function (): void {
        Route::post('/payments', [DeveloperPaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{orderNo}', [DeveloperPaymentController::class, 'show'])->name('payments.show');
        Route::post('/payments/{orderNo}/cancel', [DeveloperPaymentController::class, 'cancel'])->name('payments.cancel');
        Route::get('/webhook-endpoints', [WebhookEndpointController::class, 'index'])->name('webhook-endpoints.index');
        Route::post('/webhook-endpoints', [WebhookEndpointController::class, 'store'])->name('webhook-endpoints.store');
        Route::delete('/webhook-endpoints/{endpointId}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
        Route::get('/webhook-deliveries', [WebhookDeliveryController::class, 'index'])->name('webhook-deliveries.index');
        Route::post('/webhook-deliveries/{deliveryId}/retry', [WebhookDeliveryController::class, 'retry'])->name('webhook-deliveries.retry');
    });
});
