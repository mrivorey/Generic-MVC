<?php

namespace App\Commands;

class CommandRunner
{
    private array $commands = [];

    public function __construct()
    {
        $this->discoverCommands();
    }

    /**
     * Run a command by name, or show help if no arguments given.
     *
     * @param array $args CLI arguments (command name + its args)
     * @return int Exit code
     */
    public function run(array $args): int
    {
        if (empty($args)) {
            return $this->showHelp();
        }

        $commandName = $args[0];
        $commandArgs = array_slice($args, 1);

        if (!isset($this->commands[$commandName])) {
            fwrite(STDERR, "Unknown command: {$commandName}" . PHP_EOL);
            return 1;
        }

        return $this->commands[$commandName]->execute($commandArgs);
    }

    /**
     * Display all available commands sorted alphabetically.
     */
    public function showHelp(): int
    {
        echo "Available commands:" . PHP_EOL;

        $names = array_keys($this->commands);
        sort($names);

        // Find the longest command name for alignment
        $maxLen = 0;
        foreach ($names as $name) {
            $maxLen = max($maxLen, strlen($name));
        }

        foreach ($names as $name) {
            $description = $this->commands[$name]->getDescription();
            $padding = str_repeat(' ', $maxLen - strlen($name) + 2);
            echo "  {$name}{$padding}{$description}" . PHP_EOL;
        }

        return 0;
    }

    /**
     * Get the registered commands (for testing).
     *
     * @return array<string, Command>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Auto-discover all *Command.php files in the Commands directory.
     */
    private function discoverCommands(): void
    {
        $dir = __DIR__;
        $files = glob($dir . '/*Command.php');

        foreach ($files as $file) {
            $className = 'App\\Commands\\' . basename($file, '.php');

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (!$reflection->isSubclassOf(Command::class)) {
                continue;
            }

            $instance = new $className();
            $name = $instance->getName();

            if ($name !== '') {
                $this->commands[$name] = $instance;
            }
        }
    }
}
