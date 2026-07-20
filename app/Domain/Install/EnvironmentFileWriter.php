<?php

declare(strict_types=1);

namespace App\Domain\Install;

use RuntimeException;

final class EnvironmentFileWriter
{
    private readonly string $environmentPath;

    private readonly string $examplePath;

    public function __construct(?string $environmentPath = null, ?string $examplePath = null)
    {
        $this->environmentPath = $environmentPath ?? base_path('.env');
        $this->examplePath = $examplePath ?? base_path('.env.example');
    }

    public function snapshot(): ?string
    {
        return is_file($this->environmentPath) ? (string) file_get_contents($this->environmentPath) : null;
    }

    public function restore(?string $snapshot): void
    {
        if ($snapshot === null) {
            if (is_file($this->environmentPath)) {
                unlink($this->environmentPath);
            }

            return;
        }

        $this->atomicWrite($this->environmentPath, $snapshot);
    }

    public function update(array $values): void
    {
        if (! is_file($this->environmentPath)) {
            if (! is_file($this->examplePath)) {
                throw new RuntimeException('Missing .env.example template.');
            }

            $contents = (string) file_get_contents($this->examplePath);
        } else {
            $contents = (string) file_get_contents($this->environmentPath);
        }

        $lines = preg_split('/\R/', $contents) ?: [];
        $remaining = $values;

        foreach ($lines as $index => $line) {
            if (preg_match('/\A([A-Z][A-Z0-9_]*)=/', $line, $matches) !== 1) {
                continue;
            }

            $key = $matches[1];

            if (! array_key_exists($key, $remaining)) {
                continue;
            }

            $lines[$index] = $key.'='.$this->serialize($remaining[$key]);
            unset($remaining[$key]);
        }

        if ($remaining !== []) {
            $lines[] = '';

            foreach ($remaining as $key => $value) {
                $lines[] = $key.'='.$this->serialize($value);
            }
        }

        $this->atomicWrite($this->environmentPath, rtrim(implode(PHP_EOL, $lines)).PHP_EOL);
    }

    private function serialize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        $escaped = str_replace(
            ["\\", "\r", "\n", '"', '$'],
            ["\\\\", '', '\\n', '\\"', '\\$'],
            $string,
        );

        return '"'.$escaped.'"';
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(6));

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the environment file.');
        }

        @chmod($temporaryPath, 0600);

        if (! $this->replaceFile($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to publish the environment file.');
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
}
