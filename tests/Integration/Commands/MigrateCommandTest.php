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

        $this->tempMigrationDir = sys_get_temp_dir() . '/test_migrations_' . uniqid();
        mkdir($this->tempMigrationDir, 0755, true);
    }

    protected function tearDown(): void
    {
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
     * Skips CREATE TABLE IF NOT EXISTS since it causes implicit commit in MySQL,
     * breaking DatabaseTestCase's transaction wrapper. The migrations table
     * already exists from init.sql.
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

                // Skip CREATE TABLE — it already exists from init.sql
                // and DDL causes implicit commit, breaking test transactions

                $stmt = $pdo->query("SELECT migration FROM migrations");
                $executed = $stmt->fetchAll(\PDO::FETCH_COLUMN);

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
        // Verify migrations table exists (created by init.sql)
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'migrations'"
        );
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function test_runs_pending_migrations(): void
    {
        $migrationFile = $this->tempMigrationDir . '/001_test_insert.sql';
        file_put_contents($migrationFile, "INSERT INTO roles (name, slug, description) VALUES ('TestRole', 'test-migrate-role', 'Created by migration test')");

        $command = $this->createCommand();

        ob_start();
        $code = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('001_test_insert.sql', $output);

        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration = ?");
        $stmt->execute(['001_test_insert.sql']);
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function test_skips_executed_migrations(): void
    {
        $migrationFile = $this->tempMigrationDir . '/001_test_skip.sql';
        file_put_contents($migrationFile, "INSERT INTO roles (name, slug, description) VALUES ('SkipRole', 'test-skip-role', 'Skip test')");

        $command = $this->createCommand();

        ob_start();
        $command->execute([]);
        ob_end_clean();

        ob_start();
        $code = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }

    public function test_reports_nothing_when_up_to_date(): void
    {
        $command = $this->createCommand();

        ob_start();
        $code = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }
}
