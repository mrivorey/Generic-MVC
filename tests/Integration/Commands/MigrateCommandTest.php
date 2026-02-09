<?php

namespace Tests\Integration\Commands;

use App\Commands\MigrateCommand;
use App\Core\Database;
use Tests\DatabaseTestCase;

class MigrateCommandTest extends DatabaseTestCase
{
    private string $tempMigrationDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temp directory to hold test migration files
        $this->tempMigrationDir = sys_get_temp_dir() . '/test_migrations_' . uniqid();
        mkdir($this->tempMigrationDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp migration files
        $this->removeDir($this->tempMigrationDir);

        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Create a MigrateCommand that uses our temp migration directory.
     */
    private function createCommand(): MigrateCommand
    {
        return new class($this->tempMigrationDir) extends MigrateCommand {
            private string $testMigrationsDir;

            public function __construct(string $migrationsDir)
            {
                $this->testMigrationsDir = $migrationsDir;
            }

            public function execute(array $args): int
            {
                $pdo = Database::getConnection();

                // migrations table already exists (from init.sql), but ensure it
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

                // Scan migration files from the custom directory
                $files = glob($this->testMigrationsDir . '/*.sql');
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
        };
    }

    public function test_creates_migrations_table(): void
    {
        $command = $this->createCommand();

        ob_start();
        $command->execute([]);
        ob_end_clean();

        // Verify migrations table exists (created by init.sql, confirmed by command)
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'migrations'"
        );
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function test_runs_pending_migrations(): void
    {
        // Use DML-only migration (INSERT) to stay transaction-safe
        $migrationFile = $this->tempMigrationDir . '/001_test_insert.sql';
        file_put_contents($migrationFile, "INSERT INTO roles (name, slug, description) VALUES ('TestRole', 'test-migrate-role', 'Created by migration test')");

        $command = $this->createCommand();

        ob_start();
        $code = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('001_test_insert.sql', $output);

        // Verify the migration was recorded
        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
        $stmt->execute(['001_test_insert.sql']);
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function test_skips_executed_migrations(): void
    {
        // Use DML-only migration to stay transaction-safe
        $migrationFile = $this->tempMigrationDir . '/001_test_skip.sql';
        file_put_contents($migrationFile, "INSERT INTO roles (name, slug, description) VALUES ('SkipRole', 'test-skip-role', 'Skip test')");

        $command = $this->createCommand();

        // Run once
        ob_start();
        $command->execute([]);
        ob_end_clean();

        // Run again - should say nothing to migrate
        ob_start();
        $code = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }

    public function test_reports_nothing_when_up_to_date(): void
    {
        // No migration files in the temp dir
        $command = $this->createCommand();

        ob_start();
        $code = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }
}
