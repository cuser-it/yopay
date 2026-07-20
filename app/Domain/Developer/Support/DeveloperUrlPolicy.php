<?php

declare(strict_types=1);

namespace App\Domain\Developer\Support;

use App\Domain\Delivery\Support\PublicHttpUrlGuard;
use App\Models\DeveloperApplication;
use InvalidArgumentException;
use RuntimeException;

final readonly class DeveloperUrlPolicy
{
    public function __construct(private PublicHttpUrlGuard $urlGuard) {}

    public function assertNotifyUrlAllowed(DeveloperApplication $application, ?string $url): void
    {
        $this->assertAllowed($url, $application->allowed_notify_urls ?? [], 'notify URL');

        if ($url === null) {
            return;
        }

        try {
            $this->urlGuard->resolve($url);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        }
    }

    public function assertReturnUrlAllowed(DeveloperApplication $application, ?string $url): void
    {
        $this->assertAllowed($url, $application->allowed_return_urls ?? [], 'return URL');
    }

    public function canonicalize(string $url): string
    {
        return $this->normalize($url);
    }

    private function assertAllowed(?string $url, array $allowlist, string $label): void
    {
        if ($url === null) {
            return;
        }

        $normalized = $this->normalize($url);

        foreach ($allowlist as $allowed) {
            if (! is_string($allowed)) {
                continue;
            }

            $allowedNormalized = $this->normalize($allowed);

            if ($normalized === $allowedNormalized) {
                return;
            }

            if (! str_contains($allowedNormalized, '?') && (
                str_starts_with($normalized, $allowedNormalized.'/')
                || str_starts_with($normalized, $allowedNormalized.'?')
            )) {
                return;
            }
        }

        throw new InvalidArgumentException("The {$label} is not in the application allowlist.");
    }

    private function normalize(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('Developer URLs must use HTTPS and include a public host.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Developer URLs cannot contain credentials or fragments.');
        }

        $portNumber = (int) ($parts['port'] ?? 443);

        if ($portNumber !== 443) {
            throw new InvalidArgumentException('Developer URLs must use HTTPS port 443.');
        }

        $host = strtolower((string) $parts['host']);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        $this->assertSafePathAndQuery($path, $query);

        return 'https://'.$host.$path.$query;
    }

    private function assertSafePathAndQuery(string $path, string $query): void
    {
        foreach (explode('/', $path) as $segment) {
            $decoded = rawurldecode($segment);

            if (
                in_array($decoded, ['.', '..'], true)
                || str_contains($decoded, '/')
                || str_contains($decoded, '\\')
                || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1
            ) {
                throw new InvalidArgumentException('Developer URLs contain an unsafe path.');
            }
        }

        if (preg_match('/[\x00-\x1F\x7F]/', rawurldecode($query)) === 1) {
            throw new InvalidArgumentException('Developer URLs contain an unsafe query string.');
        }
    }
}
