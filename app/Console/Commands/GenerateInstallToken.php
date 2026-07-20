<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Install\InstallState;
use Illuminate\Console\Command;

final class GenerateInstallToken extends Command
{
    protected $signature = 'install:token {--rotate : Replace an existing installation token}';

    protected $description = 'Generate the private token required to access the web installer';

    public function handle(InstallState $state): int
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->components->warn('Run this command as the PHP-FPM operating-system user so the web installer can read its private state.');
        }

        try {
            $token = $state->generateAccessToken((bool) $this->option('rotate'));
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Installation token generated. It will only be shown once:');
        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->components->warn('Keep this token private and enter it only on your own /install page.');

        return self::SUCCESS;
    }
}
