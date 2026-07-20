<?php

declare(strict_types=1);

namespace App\Domain\Gateway\EasyPay;

use OpenSSLAsymmetricKey;

final class EasyPayRsaKey
{
    public static function privateKey(string $value): OpenSSLAsymmetricKey
    {
        $normalized = self::normalizeInput($value);

        if ($normalized === '') {
            throw new EasyPayRsaKeyException(EasyPayRsaKeyException::MISSING);
        }

        if (self::isEncryptedPrivateKey($normalized)) {
            throw new EasyPayRsaKeyException(EasyPayRsaKeyException::ENCRYPTED);
        }

        foreach (self::candidates($normalized, ['PRIVATE KEY', 'RSA PRIVATE KEY']) as $candidate) {
            $key = openssl_pkey_get_private($candidate);

            if ($key !== false) {
                self::assertRsa($key);

                return $key;
            }
        }

        throw new EasyPayRsaKeyException(EasyPayRsaKeyException::INVALID);
    }

    public static function publicKey(string $value): OpenSSLAsymmetricKey
    {
        $normalized = self::normalizeInput($value);

        if ($normalized === '') {
            throw new EasyPayRsaKeyException(EasyPayRsaKeyException::MISSING);
        }

        foreach (self::candidates($normalized, ['PUBLIC KEY', 'RSA PUBLIC KEY']) as $candidate) {
            $key = openssl_pkey_get_public($candidate);

            if ($key !== false) {
                self::assertRsa($key);

                return $key;
            }
        }

        throw new EasyPayRsaKeyException(EasyPayRsaKeyException::INVALID);
    }

    private static function normalizeInput(string $value): string
    {
        $normalized = trim(str_replace("\u{FEFF}", '', $value));

        if (strlen($normalized) >= 2) {
            $quote = $normalized[0];

            if (($quote === '"' || $quote === "'") && str_ends_with($normalized, $quote)) {
                $normalized = substr($normalized, 1, -1);
            }
        }

        $normalized = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $normalized);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);

        return trim($normalized);
    }

    private static function isEncryptedPrivateKey(string $value): bool
    {
        return str_contains($value, '-----BEGIN ENCRYPTED PRIVATE KEY-----')
            || preg_match('/^Proc-Type:\s*4,ENCRYPTED$/mi', $value) === 1;
    }

    private static function candidates(string $value, array $labels): array
    {
        if (str_contains($value, '-----BEGIN')) {
            return [self::normalizePem($value)];
        }

        $body = preg_replace('/\s+/', '', $value) ?? '';

        if ($body === '' || base64_decode($body, true) === false) {
            return [];
        }

        return array_map(
            static fn (string $label): string => self::wrapPem($body, $label),
            $labels,
        );
    }

    private static function normalizePem(string $value): string
    {
        if (preg_match('/-----BEGIN ([A-Z0-9 ]+)-----(.*?)-----END \1-----/s', $value, $matches) !== 1) {
            return $value;
        }

        $body = preg_replace('/\s+/', '', $matches[2]) ?? '';

        return self::wrapPem($body, $matches[1]);
    }

    private static function wrapPem(string $body, string $label): string
    {
        return "-----BEGIN {$label}-----\n"
            .chunk_split($body, 64, "\n")
            ."-----END {$label}-----";
    }

    private static function assertRsa(OpenSSLAsymmetricKey $key): void
    {
        $details = openssl_pkey_get_details($key);

        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new EasyPayRsaKeyException(EasyPayRsaKeyException::NOT_RSA);
        }
    }
}
