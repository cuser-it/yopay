<?php

declare(strict_types=1);

namespace App\Domain\Install;

use RuntimeException;

final class InstallState
{
    private readonly string $privateDirectory;

    public function __construct(?string $privateDirectory = null)
    {
        $this->privateDirectory = $privateDirectory ?? storage_path('app/private');
    }

    public function isInstalled(): bool
    {
        return is_file($this->installedLockPath());
    }

    public function hasAccessToken(): bool
    {
        return $this->accessTokenHash() !== null;
    }

    public function accessTokenHash(): ?string
    {
        $tokenFile = $this->tokenPath();

        if (is_file($tokenFile)) {
            $contents = @file_get_contents($tokenFile);

            if ($contents === false) {
                return null;
            }

            $hash = trim($contents);

            return preg_match('/\A[a-f0-9]{64}\z/', $hash) === 1 ? $hash : null;
        }

        $environmentToken = env('PAYMENT_INSTALL_TOKEN');

        if (! is_string($environmentToken) || trim($environmentToken) === '') {
            return null;
        }

        return hash('sha256', trim($environmentToken));
    }

    public function verifyAccessToken(string $token): bool
    {
        $expectedHash = $this->accessTokenHash();

        return $expectedHash !== null
            && $token !== ''
            && hash_equals($expectedHash, hash('sha256', $token));
    }

    public function generateAccessToken(bool $rotate = false): string
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('The application is already installed.');
        }

        if ($this->hasAccessToken() && ! $rotate) {
            throw new RuntimeException('An installation token already exists. Use --rotate to replace it.');
        }

        $token = $this->base64UrlEncode(random_bytes(36));
        $this->writePrivateFile($this->tokenPath(), hash('sha256', $token).PHP_EOL);

        return $token;
    }

    public function removeAccessToken(): void
    {
        $path = $this->tokenPath();

        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('Unable to remove the installation token.');
        }
    }

    public function purgeTemporaryInstallerFiles(): void
    {
        $this->removeAccessToken();

        foreach (['install-session-*.json', 'install-rate-*.json'] as $pattern) {
            foreach (glob($this->privateDirectory.DIRECTORY_SEPARATOR.$pattern) ?: [] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    public function writeInstalledLock(array $metadata): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('The application is already installed.');
        }

        $payload = json_encode([
            'installed_at' => now()->toIso8601String(),
            ...$metadata,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->writePrivateFile($this->installedLockPath(), $payload.PHP_EOL);
    }

    /** @return resource */
    public function acquireInstallationLock()
    {
        $this->ensurePrivateDirectory();
        $handle = fopen($this->installationMutexPath(), 'c+');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new RuntimeException('Another installation process is currently running.');
        }

        return $handle;
    }

    /** @param resource $handle */
    public function releaseInstallationLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function tooManyAuthenticationAttempts(string $clientIdentifier): bool
    {
        $record = $this->readRateLimitRecord($clientIdentifier);

        return $record['attempts'] >= 8 && $record['reset_at'] > time();
    }

    public function recordAuthenticationFailure(string $clientIdentifier): void
    {
        $record = $this->readRateLimitRecord($clientIdentifier);

        if ($record['reset_at'] <= time()) {
            $record = ['attempts' => 0, 'reset_at' => time() + 600];
        }

        $record['attempts']++;
        $this->writePrivateFile(
            $this->rateLimitPath($clientIdentifier),
            json_encode($record, JSON_THROW_ON_ERROR),
        );
    }

    public function clearAuthenticationFailures(string $clientIdentifier): void
    {
        $path = $this->rateLimitPath($clientIdentifier);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function privateDirectory(): string
    {
        return $this->privateDirectory;
    }

    private function installedLockPath(): string
    {
        return $this->privateDirectory.DIRECTORY_SEPARATOR.'installed.lock';
    }

    private function installationMutexPath(): string
    {
        return $this->privateDirectory.DIRECTORY_SEPARATOR.'installing.lock';
    }

    private function tokenPath(): string
    {
        return $this->privateDirectory.DIRECTORY_SEPARATOR.'install-token';
    }

    private function rateLimitPath(string $clientIdentifier): string
    {
        return $this->privateDirectory.DIRECTORY_SEPARATOR.'install-rate-'.hash('sha256', $clientIdentifier).'.json';
    }

    /** @return array{attempts: int, reset_at: int} */
    private function readRateLimitRecord(string $clientIdentifier): array
    {
        $path = $this->rateLimitPath($clientIdentifier);

        if (! is_file($path)) {
            return ['attempts' => 0, 'reset_at' => time() + 600];
        }

        $record = json_decode((string) file_get_contents($path), true);

        if (! is_array($record)) {
            return ['attempts' => 0, 'reset_at' => time() + 600];
        }

        return [
            'attempts' => max(0, (int) ($record['attempts'] ?? 0)),
            'reset_at' => (int) ($record['reset_at'] ?? 0),
        ];
    }

    private function writePrivateFile(string $path, string $contents): void
    {
        $this->ensurePrivateDirectory();
        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(6));

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write private installation state.');
        }

        @chmod($temporaryPath, 0600);

        if (! $this->replaceFile($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to publish private installation state.');
        }

        @chmod($path, 0600);
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

    private function ensurePrivateDirectory(): void
    {
        if (! is_dir($this->privateDirectory) && ! mkdir($this->privateDirectory, 0700, true) && ! is_dir($this->privateDirectory)) {
            throw new RuntimeException('Unable to create the private installation directory.');
        }

        @chmod($this->privateDirectory, 0700);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
