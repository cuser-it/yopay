<?php

declare(strict_types=1);

namespace App\Domain\Install;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class InstallAccessService
{
    public const COOKIE_NAME = 'payment_install_access';

    public function __construct(private readonly InstallState $state) {}

    /** @return array{session_id: string, csrf: string, expires_at: int}|null */
    public function accessContext(Request $request): ?array
    {
        $tokenHash = $this->state->accessTokenHash();
        $cookie = $request->cookies->get(self::COOKIE_NAME);

        if ($tokenHash === null || ! is_string($cookie)) {
            return null;
        }

        [$encodedPayload, $signature] = array_pad(explode('.', $cookie, 2), 2, '');
        $expectedSignature = hash_hmac('sha256', $encodedPayload, hex2bin($tokenHash) ?: $tokenHash);

        if ($signature === '' || ! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (
            ! is_array($payload)
            || ! is_string($payload['session_id'] ?? null)
            || preg_match('/\A[a-f0-9]{32}\z/', $payload['session_id']) !== 1
            || ! is_string($payload['csrf'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/', $payload['csrf']) !== 1
            || (int) ($payload['expires_at'] ?? 0) <= time()
        ) {
            return null;
        }

        return [
            'session_id' => $payload['session_id'],
            'csrf' => $payload['csrf'],
            'expires_at' => (int) $payload['expires_at'],
        ];
    }

    public function issueCookie(Request $request): Cookie
    {
        $tokenHash = $this->state->accessTokenHash();

        if ($tokenHash === null) {
            throw new \RuntimeException('The installation token is unavailable.');
        }

        $payload = $this->base64UrlEncode(json_encode([
            'session_id' => bin2hex(random_bytes(16)),
            'csrf' => bin2hex(random_bytes(32)),
            'expires_at' => time() + 1800,
        ], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, hex2bin($tokenHash) ?: $tokenHash);

        return new Cookie(
            self::COOKIE_NAME,
            $payload.'.'.$signature,
            time() + 1800,
            '/install',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }

    public function expireCookie(Request $request): Cookie
    {
        return new Cookie(
            self::COOKIE_NAME,
            '',
            1,
            '/install',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
