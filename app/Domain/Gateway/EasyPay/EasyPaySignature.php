<?php

declare(strict_types=1);

namespace App\Domain\Gateway\EasyPay;

use App\Domain\Gateway\Exceptions\GatewayConfigurationException;
use App\Domain\Gateway\Exceptions\InvalidGatewaySignatureException;

final class EasyPaySignature
{
    public static function canonicalize(array $parameters): string
    {
        $filtered = [];

        foreach ($parameters as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type' || $value === '' || $value === null || ! is_scalar($value)) {
                continue;
            }

            $filtered[(string) $key] = (string) $value;
        }

        ksort($filtered, SORT_STRING);

        return implode('&', array_map(
            static fn (string $key, string $value): string => $key.'='.$value,
            array_keys($filtered),
            array_values($filtered),
        ));
    }

    public static function signV1(array $parameters, string $merchantKey): string
    {
        if ($merchantKey === '') {
            throw new GatewayConfigurationException('EasyPay V1 merchant key is not configured.');
        }

        return md5(self::canonicalize($parameters).$merchantKey);
    }

    public static function verifyV1(array $parameters, string $merchantKey): void
    {
        $receivedSignature = strtolower((string) ($parameters['sign'] ?? ''));
        $expectedSignature = self::signV1($parameters, $merchantKey);

        if ($receivedSignature === '' || ! hash_equals($expectedSignature, $receivedSignature)) {
            throw new InvalidGatewaySignatureException('EasyPay V1 signature verification failed.');
        }
    }

    public static function signV2(array $parameters, string $privateKey): string
    {
        try {
            $key = EasyPayRsaKey::privateKey($privateKey);
        } catch (EasyPayRsaKeyException $exception) {
            throw new GatewayConfigurationException(self::privateKeyError($exception), previous: $exception);
        }

        $signature = '';
        $signed = openssl_sign(self::canonicalize($parameters), $signature, $key, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new GatewayConfigurationException('EasyPay V2 request signing failed.');
        }

        return base64_encode($signature);
    }

    public static function verifyV2(array $parameters, string $publicKey): void
    {
        $signature = base64_decode((string) ($parameters['sign'] ?? ''), true);

        if ($signature === false) {
            throw new InvalidGatewaySignatureException('EasyPay V2 signature data is invalid.');
        }

        try {
            $key = EasyPayRsaKey::publicKey($publicKey);
        } catch (EasyPayRsaKeyException $exception) {
            throw new GatewayConfigurationException(self::publicKeyError($exception), previous: $exception);
        }

        $verified = openssl_verify(self::canonicalize($parameters), $signature, $key, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new InvalidGatewaySignatureException('EasyPay V2 signature verification failed.');
        }
    }

    private static function privateKeyError(EasyPayRsaKeyException $exception): string
    {
        return match ($exception->reason) {
            EasyPayRsaKeyException::MISSING => 'EasyPay V2 merchant private key is not configured.',
            EasyPayRsaKeyException::ENCRYPTED => 'EasyPay V2 encrypted merchant private keys are not supported.',
            EasyPayRsaKeyException::NOT_RSA => 'EasyPay V2 merchant private key must be an RSA key.',
            default => 'EasyPay V2 merchant private key is invalid.',
        };
    }

    private static function publicKeyError(EasyPayRsaKeyException $exception): string
    {
        return match ($exception->reason) {
            EasyPayRsaKeyException::MISSING => 'EasyPay V2 platform public key is not configured.',
            EasyPayRsaKeyException::NOT_RSA => 'EasyPay V2 platform public key must be an RSA key.',
            default => 'EasyPay V2 platform public key is invalid.',
        };
    }
}
