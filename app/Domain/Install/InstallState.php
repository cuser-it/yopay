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

    public function installerSessionSecret(): string
    {
        $existingSecret = $this->readInstallerSessionSecret();

        if ($existingSecret !== null) {
            return $existingSecret;
        }

        if ($this->isInstalled()) {
            throw new RuntimeException('The application is already installed.');
        }

        $this->ensurePrivateDirectory();
        $lock = fopen($this->installerSecretMutexPath(), 'c+');

        if ($lock === false) {
            throw new RuntimeException('Unable to lock the private installer secret.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the private installer secret.');
            }

            $existingSecret = $this->readInstallerSessionSecret();

            if ($existingSecret !== null) {
                return $existingSecret;
            }

            if ($this->isInstalled()) {
                throw new RuntimeException('The application is already installed.');
            }

            $secret = bin2hex(random_bytes(32));
            $this->writePrivateFile($this->installerSecretPath(), $secret.PHP_EOL);

            return $secret;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function purgeTemporaryInstallerFiles(): void
    {
        $secretPath = $this->installerSecretPath();

        if (is_file($secretPath) && ! @unlink($secretPath)) {
            throw new RuntimeException('Unable to remove temporary installer state.');
        }

        foreach (['install-session-*.json', 'install-rate-*.json', 'install-token'] as $pattern) {
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

    private function installerSecretPath(): string
    {
        return $this->privateDirectory.DIRECTORY_SEPARATOR.'installer-secret';
    }

    private function installerSecretMutexPath(): string
    {
        return $this->privateDirectory.DIRECTORY_SEPARATOR.'installer-secret.lock';
    }

    private function readInstallerSessionSecret(): ?string
    {
        $path = $this->installerSecretPath();

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the private installer secret.');
        }

        $secret = trim($contents);

        if (preg_match('/\A[a-f0-9]{64}\z/', $secret) !== 1) {
            throw new RuntimeException('The private installer secret is invalid.');
        }

        return $secret;
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
}
