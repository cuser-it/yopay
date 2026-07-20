<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Domain\Gateway\Enums\GatewayApiVersion;
use App\Domain\Payment\Services\GatewayCallbackProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class GatewayCallbackController
{
    public function __construct(private GatewayCallbackProcessor $processor) {}

    public function __invoke(Request $request, string $version): Response
    {
        $apiVersion = GatewayApiVersion::tryFrom($version);

        if ($apiVersion === null) {
            return response('fail', 404)->header('Content-Type', 'text/plain');
        }

        $callback = $this->processor->process($apiVersion, $request->all());
        $accepted = $callback->processing_status === 'processed';

        return response($accepted ? 'success' : 'fail', $accepted ? 200 : 400)
            ->header('Content-Type', 'text/plain');
    }
}
