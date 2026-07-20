<?php

declare(strict_types=1);

namespace App\Domain\Payment\Support;

final class CanonicalPayload
{
    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode(
            self::sortRecursively($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    public static function sortRecursively(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sortRecursively($value);
            }
        }

        if (! array_is_list($payload)) {
            ksort($payload, SORT_STRING);
        }

        return $payload;
    }
}
