<?php

declare(strict_types=1);

namespace App\Domain\Developer\Support;

final class DeveloperRequestSignature
{
    public static function canonical(
        string $timestamp,
        string $nonce,
        string $method,
        string $requestTarget,
        string $rawBody,
    ): string {
        return implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($method),
            $requestTarget,
            hash('sha256', $rawBody),
        ]);
    }

    public static function sign(string $canonical, string $secret): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function verify(string $canonical, string $secret, string $received): bool
    {
        $normalized = strtolower(str_starts_with($received, 'sha256=') ? substr($received, 7) : $received);

        return preg_match('/^[a-f0-9]{64}$/', $normalized) === 1
            && hash_equals(self::sign($canonical, $secret), $normalized);
    }
}
