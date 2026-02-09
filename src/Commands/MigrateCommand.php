<?php

namespace App\Commands;

use App\Core\Database;

class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run pending database migrations';

    public function execute(array $args): int
    {
        $pdo = Database::getConnection();

        // Ensure the migrations table exists
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Get list of already-executed migrations
        $stmt = $pdo->query("SELECT migration FROM migrations");
        $executed = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Scan migration files
        $migrationsDir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($migrationsDir . '/*.sql');

        if ($files === false) {
            $files = [];
        }

        sort($files);

        $pending = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (!in_array($filename, $executed, true)) {
                $pending[] = $file;
            }
        }

        if (empty($pending)) {
            $this->output('Nothing to migrate.');
            return 0;
        }

        foreach ($pending as $file) {
            $filename = basename($file);
            $sql = file_get_contents($file);

            $this->output("Migrating: {$filename}");
            $pdo->exec($sql);

            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$filename]);

            $this->success("Migrated:  {$filename}");
        }

        return 0;
    }
}
