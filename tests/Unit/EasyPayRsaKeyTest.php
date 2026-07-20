<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Gateway\EasyPay\EasyPayRsaKey;
use App\Domain\Gateway\EasyPay\EasyPaySignature;
use App\Domain\Install\EasyPayConfigurationValidator;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Tests\TestCase;

final class EasyPayRsaKeyTest extends TestCase
{
    public function test_private_key_parser_accepts_pkcs8_pkcs1_raw_base64_and_literal_newlines(): void
    {
        $pkcs8 = $this->fixture('easypay-rsa-private-pkcs8.pem');
        $pkcs1 = $this->fixture('easypay-rsa-private-pkcs1.pem');
        $variants = [
            $pkcs8,
            $pkcs1,
            $this->pemBody($pkcs8),
            '"'.str_replace("\n", '\\n', trim($pkcs1)).'"',
        ];

        foreach ($variants as $variant) {
            $key = EasyPayRsaKey::privateKey($variant);

            $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
            $this->assertSame(OPENSSL_KEYTYPE_RSA, openssl_pkey_get_details($key)['type'] ?? null);
        }
    }

    public function test_public_key_parser_accepts_spki_pkcs1_and_raw_base64(): void
    {
        $publicKey = $this->fixture('easypay-rsa-public.pem');
        $rsaPublicKey = $this->fixture('easypay-rsa-public-pkcs1.pem');
        $variants = [
            $publicKey,
            $rsaPublicKey,
            $this->pemBody($publicKey),
            str_replace("\n", '\\n', trim($rsaPublicKey)),
        ];

        foreach ($variants as $variant) {
            $key = EasyPayRsaKey::publicKey($variant);

            $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
            $this->assertSame(OPENSSL_KEYTYPE_RSA, openssl_pkey_get_details($key)['type'] ?? null);
        }
    }

    public function test_installer_and_runtime_accept_the_same_key_formats(): void
    {
        $privateKey = $this->pemBody($this->fixture('easypay-rsa-private-pkcs1.pem'));
        $publicKey = $this->pemBody($this->fixture('easypay-rsa-public.pem'));
        $validator = new EasyPayConfigurationValidator();

        $validator->validate([
            'base_url' => 'https://pay.example.com',
            'merchant_private_key' => $privateKey,
            'platform_public_key' => $publicKey,
        ]);

        $payload = [
            'pid' => '10001',
            'money' => '1.00',
            'timestamp' => '1784304000',
        ];
        $payload['sign'] = EasyPaySignature::signV2($payload, $privateKey);

        EasyPaySignature::verifyV2($payload, $publicKey);

        $this->addToAssertionCount(1);
    }

    public function test_installer_rejects_encrypted_private_key_with_actionable_message(): void
    {
        $privateKey = EasyPayRsaKey::privateKey($this->fixture('easypay-rsa-private-pkcs8.pem'));
        $encryptedPrivateKey = '';
        $exported = openssl_pkey_export($privateKey, $encryptedPrivateKey, 'test-passphrase');

        $this->assertTrue($exported);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('私钥已加密');

        (new EasyPayConfigurationValidator())->validate([
            'base_url' => 'https://pay.example.com',
            'merchant_private_key' => $encryptedPrivateKey,
            'platform_public_key' => $this->fixture('easypay-rsa-public.pem'),
        ]);
    }

    public function test_installer_rejects_non_rsa_private_key_with_actionable_message(): void
    {
        $ecKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $ecPrivateKey = '';

        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $ecKey);
        $this->assertTrue(openssl_pkey_export($ecKey, $ecPrivateKey));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('必须使用 RSA 密钥');

        (new EasyPayConfigurationValidator())->validate([
            'base_url' => 'https://pay.example.com',
            'merchant_private_key' => $ecPrivateKey,
            'platform_public_key' => $this->fixture('easypay-rsa-public.pem'),
        ]);
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(base_path('tests/Fixtures/'.$name));

        $this->assertIsString($contents);

        return $contents;
    }

    private function pemBody(string $pem): string
    {
        return preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----|\s+/', '', $pem) ?? '';
    }
}
