<?php

declare(strict_types=1);

namespace App\Domain\Install;

use PDO;
use RuntimeException;

final class DatabaseProbe
{
    public function assertConnectableAndEmpty(array $configuration): void
    {
        $database = (string) ($configuration['database'] ?? '');

        if (in_array(strtolower($database), ['information_schema', 'mysql', 'performance_schema', 'sys'], true)) {
            throw new RuntimeException('不能使用 MySQL 系统数据库。');
        }

        $pdo = $this->connect($configuration);
        $selectedDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();

        if (! is_string($selectedDatabase) || ! hash_equals($database, $selectedDatabase)) {
            throw new RuntimeException('数据库连接未选择预期的数据库。');
        }

        $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);

        if ($tables !== []) {
            throw new RuntimeException('目标数据库不是空库。安装器不会覆盖、删除或复用现有表。');
        }
    }

    public function connect(array $configuration): PDO
    {
        $host = (string) ($configuration['host'] ?? '');
        $port = (int) ($configuration['port'] ?? 3306);
        $database = (string) ($configuration['database'] ?? '');
        $username = (string) ($configuration['username'] ?? '');
        $password = (string) ($configuration['password'] ?? '');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('无法连接目标数据库，请检查地址、端口、库名和账号权限。', 0, $exception);
        }
    }
}
