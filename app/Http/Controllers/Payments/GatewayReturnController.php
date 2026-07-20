<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Gateway\GatewayRegistry;
use App\Models\PaymentOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final readonly class GatewayReturnController
{
    public function __construct(private GatewayRegistry $gateways) {}

    public function __invoke(Request $request, string $version): RedirectResponse
    {
        $apiVersion = GatewayApiVersion::tryFrom($version);

        abort_if($apiVersion === null, 404);

        try {
            $verified = $this->gateways->forVersion($apiVersion)->verifyCallback($request->all());
        } catch (Throwable) {
            abort(400, 'Invalid payment return signature.');
        }

        $order = PaymentOrder::query()
            ->where('order_no', $verified->localOrderNumber)
            ->where('gateway_api_version', $apiVersion->value)
            ->firstOrFail();

        return redirect()->route('checkout.resume', [
            'token' => (string) $order->checkout_token_ciphertext,
        ]);
    }
}
