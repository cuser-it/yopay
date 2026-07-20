<?php

declare(strict_types=1);

namespace App\Domain\Install;

use RuntimeException;

final class InstallSessionStore
{
    public function __construct(private readonly InstallState $state) {}

    public function read(string $sessionId): array
    {
        $path = $this->path($sessionId);

        if (! is_file($path)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            return [];
        }

        $tokenHash = $this->state->accessTokenHash();
        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $ciphertext = base64_decode((string) ($payload['ciphertext'] ?? ''), true);

        if ($tokenHash === null || $iv === false || $tag === false || $ciphertext === false) {
            return [];
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey($tokenHash),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $sessionId,
        );

        if (! is_string($plaintext)) {
            return [];
        }

        $data = json_decode($plaintext, true);

        return is_array($data) ? $data : [];
    }

    public function write(string $sessionId, array $data): void
    {
        $tokenHash = $this->state->accessTokenHash();

        if ($tokenHash === null) {
            throw new RuntimeException('The installation token is unavailable.');
        }

        if (! is_dir($this->state->privateDirectory()) && ! mkdir($this->state->privateDirectory(), 0700, true) && ! is_dir($this->state->privateDirectory())) {
            throw new RuntimeException('Unable to create the private installation directory.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($data, JSON_THROW_ON_ERROR),
            'aes-256-gcm',
            $this->encryptionKey($tokenHash),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $sessionId,
        );

        if (! is_string($ciphertext)) {
            throw new RuntimeException('Unable to protect the installation draft.');
        }

        $payload = json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR);
        $path = $this->path($sessionId);
        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(4));

        if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false || ! $this->replaceFile($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to persist the installation draft.');
        }

        @chmod($path, 0600);
    }

    public function delete(string $sessionId): void
    {
        $path = $this->path($sessionId);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $sessionId): string
    {
        if (preg_match('/\A[a-f0-9]{32}\z/', $sessionId) !== 1) {
            throw new RuntimeException('Invalid installation session.');
        }

        return $this->state->privateDirectory().DIRECTORY_SEPARATOR.'install-session-'.$sessionId.'.json';
    }

    private function encryptionKey(string $tokenHash): string
    {
        return hash('sha256', 'payment-installer-draft:'.$tokenHash, true);
    }

    private function replaceFile(string $temporaryPath, string $path): bool
    {
        if (DIRECTORY_SEPARATOR !== '\\' || ! is_file($path)) {
            return rename($temporaryPath, $path);
        }

        $backupPath = $path.'.bak.'.bin2hex(random_bytes(4));

        if (! rename($path, $backupPath)) {
            return false;
        }

        if (! rename($temporaryPath, $path)) {
            rename($backupPath, $path);

            return false;
        }

        @unlink($backupPath);

        return true;
    }
}
