<?php

declare(strict_types=1);

namespace App\Domain\Install;

final class SystemRequirementChecker
{
    /** @return list<array{label: string, passed: bool, detail: string}> */
    public function checks(): array
    {
        $checks = [[
            'label' => 'PHP 8.4 或更高版本',
            'passed' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'detail' => '当前版本：'.PHP_VERSION,
        ]];

        foreach (['curl', 'openssl', 'pdo', 'pdo_mysql', 'mbstring', 'tokenizer', 'ctype', 'fileinfo', 'filter', 'hash', 'json'] as $extension) {
            $checks[] = [
                'label' => 'PHP 扩展：'.$extension,
                'passed' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? '已加载' : '未加载',
            ];
        }

        foreach ($this->writablePaths() as $label => $path) {
            $checks[] = [
                'label' => $label,
                'passed' => $this->isWritablePath($path),
                'detail' => $path,
            ];
        }

        $checks[] = [
            'label' => '前端生产资源',
            'passed' => is_file(public_path('build/manifest.json')),
            'detail' => '需要先执行 npm run build',
        ];
        $checks[] = [
            'label' => 'Web 根目录指向 public',
            'passed' => $this->documentRootIsPublic(),
            'detail' => '禁止把项目根目录暴露给公网，避免 .env 和私有文件被下载',
        ];
        $checks[] = [
            'label' => '配置缓存未锁定旧配置',
            'passed' => ! is_file(base_path('bootstrap/cache/config.php')),
            'detail' => '如失败，请先执行 php artisan config:clear',
        ];

        return $checks;
    }

    public function passes(): bool
    {
        return ! in_array(false, array_column($this->checks(), 'passed'), true);
    }

    private function writablePaths(): array
    {
        return [
            'storage 目录可写' => storage_path(),
            'bootstrap/cache 目录可写' => base_path('bootstrap/cache'),
            '.env 配置可写' => is_file(base_path('.env')) ? base_path('.env') : base_path(),
        ];
    }

    private function isWritablePath(string $path): bool
    {
        return file_exists($path) && is_writable($path);
    }

    private function documentRootIsPublic(): bool
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $resolvedPublicPath = realpath(public_path());

        if (! is_string($documentRoot) || $documentRoot === '' || $resolvedPublicPath === false) {
            return false;
        }

        $resolvedDocumentRoot = realpath($documentRoot);

        return $resolvedDocumentRoot !== false
            && strcasecmp(rtrim($resolvedDocumentRoot, DIRECTORY_SEPARATOR), rtrim($resolvedPublicPath, DIRECTORY_SEPARATOR)) === 0;
    }
}
