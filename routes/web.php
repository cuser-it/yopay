<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Checkout\CheckoutOrderController;
use App\Http\Controllers\Checkout\PublicPaymentController;
use App\Http\Controllers\CheckoutPageController;
use App\Http\Controllers\Developer\DeveloperApplicationController;
use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\OperationsDeliveryController;
use App\Http\Controllers\Operations\OperationsOrderController;
use App\Http\Controllers\Payments\GatewayCallbackController;
use App\Http\Controllers\Payments\GatewayReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CheckoutPageController::class, 'create'])->name('checkout.create');
Route::get('/checkout/{token}', [CheckoutPageController::class, 'resume'])
    ->where('token', '[A-Za-z0-9_-]{40,80}')
    ->name('checkout.resume');

Route::prefix('checkout-api')->name('checkout.api.')->group(function (): void {
    Route::post('/orders', [PublicPaymentController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('orders.store');
    Route::get('/orders/{token}', [CheckoutOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{token}/initialize', [CheckoutOrderController::class, 'initialize'])
        ->middleware('throttle:30,1')
        ->name('orders.initialize');
    Route::post('/orders/{token}/cancel', [CheckoutOrderController::class, 'cancel'])
        ->middleware('throttle:20,1')
        ->name('orders.cancel');
});

Route::match(['get', 'post'], '/payments/callbacks/easypay/{version}', GatewayCallbackController::class)
    ->whereIn('version', ['v1', 'v2'])
    ->name('payments.callback');
Route::get('/payments/return/easypay/{version}', GatewayReturnController::class)
    ->whereIn('version', ['v1', 'v2'])
    ->name('payments.return');

Route::match(['get', 'post'], '/payapi/notify.php', GatewayCallbackController::class)
    ->defaults('version', 'v1')
    ->name('legacy.payapi.callback');
Route::get('/payapi/return.php', GatewayReturnController::class)
    ->defaults('version', 'v1')
    ->name('legacy.payapi.return');
Route::match(['get', 'post'], '/api/notify.php', GatewayCallbackController::class)
    ->defaults('version', 'v1')
    ->name('legacy.api.callback');
Route::get('/api/return.php', GatewayReturnController::class)
    ->defaults('version', 'v1')
    ->name('legacy.api.return');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');


Route::middleware('auth')->prefix('developer')->name('developer.')->group(function (): void {
    Route::get('/applications', [DeveloperApplicationController::class, 'index'])->name('applications.index');
    Route::post('/applications', [DeveloperApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [DeveloperApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/credentials/rotate', [DeveloperApplicationController::class, 'rotate'])->name('applications.credentials.rotate');
    Route::post('/applications/{application}/webhooks', [DeveloperApplicationController::class, 'storeWebhook'])->name('applications.webhooks.store');
    Route::delete('/applications/{application}/webhooks/{endpointId}', [DeveloperApplicationController::class, 'disableWebhook'])->name('applications.webhooks.disable');
    Route::post('/applications/{application}/deliveries/{deliveryId}/retry', [DeveloperApplicationController::class, 'retryDelivery'])->name('applications.deliveries.retry');
});

Route::middleware(['auth', 'admin'])->prefix('operations')->name('operations.')->group(function (): void {
    Route::get('/', OperationsDashboardController::class)->name('dashboard');
    Route::get('/orders', [OperationsOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OperationsOrderController::class, 'show'])->name('orders.show');
    Route::get('/deliveries', [OperationsDeliveryController::class, 'index'])->name('deliveries.index');
    Route::post('/webhook-deliveries/{delivery}/retry', [OperationsDeliveryController::class, 'retryWebhook'])->name('webhook-deliveries.retry');
    Route::post('/notification-deliveries/{delivery}/retry', [OperationsDeliveryController::class, 'retryNotification'])->name('notification-deliveries.retry');
});
