<?php

declare(strict_types=1);

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class MySqlMigrationCompatibilityTest extends TestCase
{
    public function test_required_application_timestamps_use_mysql_56_compatible_columns(): void
    {
        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(database_path('migrations')));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) ?: [] as $lineNumber => $line) {
                $isRequiredTimestamp = preg_match("/->timestamp\('[^']+'\)/", $line) === 1
                    && ! str_contains($line, '->nullable()')
                    && ! str_contains($line, '->useCurrent()')
                    && ! str_contains($line, '->default(');

                if ($isRequiredTimestamp) {
                    $violations[] = $file->getFilename().':'.($lineNumber + 1);
                }
            }
        }

        $this->assertSame([], $violations, 'Required TIMESTAMP columns are incompatible with supported MySQL 5.6 strict-mode installations.');
    }

    public function test_models_avoid_runtime_schema_discovery_for_mass_assignment(): void
    {
        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Models')));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (is_string($contents) && str_contains($contents, "protected \$guarded = ['id'];")) {
                $violations[] = $file->getFilename();
            }
        }

        $this->assertSame([], $violations, 'Guarding only the id column makes Laravel query generation_expression, which is unavailable on MySQL 5.6.');
    }

    public function test_developer_application_page_avoids_limited_eager_loading(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Developer/DeveloperApplicationController.php'));

        $this->assertIsString($controller);
        $this->assertStringNotContainsString(
            "'orders' => static fn (\$query) => \$query->latest('id')->limit(20)",
            $controller,
            'Limited eager loading uses window functions that are unavailable on MySQL 5.6.',
        );
        $this->assertStringContainsString(
            "\$application->orders()->latest('id')->limit(20)->get()",
            $controller,
        );
    }
}
