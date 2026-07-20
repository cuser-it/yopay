<?php

declare(strict_types=1);

namespace App\Domain\Install;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class InstallationService
{
    public function __construct(
        private readonly InstallState $state,
        private readonly SystemRequirementChecker $requirements,
        private readonly DatabaseProbe $databaseProbe,
        private readonly EnvironmentFileWriter $environment,
    ) {}

    public function install(array $draft, array $administrator): void
    {
        $mutex = $this->state->acquireInstallationLock();
        $environmentSnapshot = $this->environment->snapshot();
        try {
            if ($this->state->isInstalled()) {
                throw new RuntimeException('系统已经安装，安装入口已永久关闭。');
            }

            if (! $this->requirements->passes()) {
                throw new RuntimeException('服务器环境检查未通过。');
            }

            $site = $this->requiredArray($draft, 'site');
            $database = $this->requiredArray($draft, 'database');
            $easypay = $this->requiredArray($draft, 'easypay');

            $this->databaseProbe->assertConnectableAndEmpty($database);

            $applicationKey = 'base64:'.base64_encode(random_bytes(32));
            $this->environment->update($this->environmentValues($site, $database, $easypay, $applicationKey));
            $this->configureRuntime($site, $database, $easypay, $applicationKey);

            $migrationExitCode = Artisan::call('migrate', ['--force' => true]);

            if ($migrationExitCode !== 0) {
                throw new RuntimeException('数据库迁移失败。');
            }

            DB::connection('mysql')->transaction(function () use ($administrator): void {
                if (User::query()->exists()) {
                    throw new RuntimeException('用户表已存在数据，安装器拒绝覆盖。');
                }

                User::query()->create([
                    'name' => $administrator['name'],
                    'email' => $administrator['email'],
                    'password' => $administrator['password'],
                    'is_admin' => true,
                ]);
            });

            $seederExitCode = Artisan::call('db:seed', ['--force' => true]);

            if ($seederExitCode !== 0) {
                throw new RuntimeException('初始化数据写入失败。');
            }

            $this->state->writeInstalledLock([
                'app_url' => $site['app_url'],
                'gateway' => 'easypay_v2',
            ]);

            try {
                $this->state->purgeTemporaryInstallerFiles();
                $this->environment->update(['PAYMENT_INSTALL_TOKEN' => '']);
            } catch (\Throwable $cleanupException) {
                Log::warning('Installation completed but temporary installer cleanup needs attention.', [
                    'exception' => $cleanupException::class,
                ]);
            }
        } catch (\Throwable $exception) {
            $this->environment->restore($environmentSnapshot);

            throw $exception;
        } finally {
            $this->state->releaseInstallationLock($mutex);
        }
    }

    private function configureRuntime(array $site, array $database, array $easypay, string $applicationKey): void
    {
        Config::set('app.name', $site['name']);
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.url', $site['app_url']);
        Config::set('app.key', $applicationKey);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.host', $database['host']);
        Config::set('database.connections.mysql.port', $database['port']);
        Config::set('database.connections.mysql.database', $database['database']);
        Config::set('database.connections.mysql.username', $database['username']);
        Config::set('database.connections.mysql.password', $database['password']);
        Config::set('payment.gateway.default', 'v2');
        Config::set('payment.gateway.easypay.merchant_id', $easypay['merchant_id']);
        Config::set('payment.gateway.easypay.v2.base_url', $easypay['base_url']);
        Config::set('payment.gateway.easypay.v2.merchant_private_key', $easypay['merchant_private_key']);
        Config::set('payment.gateway.easypay.v2.platform_public_key', $easypay['platform_public_key']);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
    }

    private function environmentValues(array $site, array $database, array $easypay, string $applicationKey): array
    {
        return [
            'APP_NAME' => $site['name'],
            'APP_ENV' => 'production',
            'APP_KEY' => $applicationKey,
            'APP_DEBUG' => false,
            'APP_URL' => $site['app_url'],
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $database['host'],
            'DB_PORT' => (int) $database['port'],
            'DB_DATABASE' => $database['database'],
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'database',
            'PAYMENT_GATEWAY_VERSION' => 'v2',
            'EASYPAY_MERCHANT_ID' => $easypay['merchant_id'],
            'EASYPAY_V2_BASE_URL' => $easypay['base_url'],
            'EASYPAY_V2_MERCHANT_PRIVATE_KEY' => $easypay['merchant_private_key'],
            'EASYPAY_V2_PLATFORM_PUBLIC_KEY' => $easypay['platform_public_key'],
        ];
    }

    private function requiredArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new RuntimeException('安装配置不完整，请重新执行向导。');
        }

        return $value;
    }
}
