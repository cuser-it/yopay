<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Data\OutboundHttpResult;
use App\Domain\Delivery\Support\PublicHttpUrlGuard;
use Illuminate\Http\Client\Factory as HttpFactory;

final readonly class SafeHttpDispatcher
{
    public function __construct(
        private HttpFactory $http,
        private PublicHttpUrlGuard $guard,
    ) {}

    public function postJson(string $url, string $body, array $headers, int $timeoutSeconds): OutboundHttpResult
    {
        $target = $this->guard->resolve($url);
        $resolvedAddress = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
        $startedAt = hrtime(true);
        $response = $this->http
            ->withHeaders($headers)
            ->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => [
                    $target['host'].':'.$target['port'].':'.$resolvedAddress,
                ]],
            ])
            ->connectTimeout(5)
            ->timeout($timeoutSeconds)
            ->withBody($body, 'application/json')
            ->post($target['url']);
        $duration = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $summary = preg_replace('/\s+/', ' ', trim($response->body())) ?? '';

        return new OutboundHttpResult(
            status: $response->status(),
            summary: substr($summary, 0, 500),
            durationMilliseconds: $duration,
            successful: $response->successful(),
        );
    }
}
