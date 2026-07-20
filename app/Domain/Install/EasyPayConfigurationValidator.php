<?php

declare(strict_types=1);

namespace App\Domain\Install;

use App\Domain\Gateway\EasyPay\EasyPayRsaKey;
use App\Domain\Gateway\EasyPay\EasyPayRsaKeyException;
use RuntimeException;

final class EasyPayConfigurationValidator
{
    public function validate(array $configuration): void
    {
        $baseUrl = rtrim((string) ($configuration['base_url'] ?? ''), '/');

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('EasyPay V2 地址必须是有效的 HTTPS 地址。');
        }

        $this->assertRsaKey((string) ($configuration['merchant_private_key'] ?? ''), true);
        $this->assertRsaKey((string) ($configuration['platform_public_key'] ?? ''), false);
    }

    private function assertRsaKey(string $value, bool $private): void
    {
        try {
            $private ? EasyPayRsaKey::privateKey($value) : EasyPayRsaKey::publicKey($value);
        } catch (EasyPayRsaKeyException $exception) {
            throw new RuntimeException($this->keyError($exception, $private), previous: $exception);
        }
    }

    private function keyError(EasyPayRsaKeyException $exception, bool $private): string
    {
        return match ($exception->reason) {
            EasyPayRsaKeyException::MISSING => $private ? '请填写商户 RSA 私钥。' : '请填写 EasyPay 平台 RSA 公钥。',
            EasyPayRsaKeyException::ENCRYPTED => '商户 RSA 私钥已加密，安装器无法使用密码保护的私钥。请生成无密码的 PKCS#1 或 PKCS#8 私钥。',
            EasyPayRsaKeyException::NOT_RSA => 'EasyPay V2 必须使用 RSA 密钥，不能使用 EC 或其他类型密钥。',
            default => $private
                ? '商户 RSA 私钥无效。支持完整 PEM、仅 Base64 内容，以及 PKCS#1/PKCS#8 格式。'
                : 'EasyPay 平台 RSA 公钥无效。支持完整 PEM、仅 Base64 内容，以及 PUBLIC KEY/RSA PUBLIC KEY 格式。',
        };
    }
}
