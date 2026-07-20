<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

final class WebhookSignature
{
    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(string $timestamp, string $body, string $secret, string $received): bool
    {
        $normalized = strtolower(str_starts_with($received, 'sha256=') ? substr($received, 7) : $received);

        return preg_match('/^[a-f0-9]{64}$/', $normalized) === 1
            && hash_equals(self::sign($timestamp, $body, $secret), $normalized);
    }
}
