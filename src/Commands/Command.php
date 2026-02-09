<?php

namespace App\Commands;

abstract class Command
{
    protected string $name = '';
    protected string $description = '';

    /**
     * Execute the command.
     *
     * @param array $args Command-line arguments (after the command name)
     * @return int Exit code: 0 = success, 1+ = failure
     */
    abstract public function execute(array $args): int;

    /**
     * Get the command name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the command description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Print text to STDOUT.
     */
    protected function output(string $text): void
    {
        echo $text . PHP_EOL;
    }

    /**
     * Print text to STDERR.
     */
    protected function error(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }

    /**
     * Print a green success message to STDOUT.
     */
    protected function success(string $text): void
    {
        echo "\033[32m" . $text . "\033[0m" . PHP_EOL;
    }

    /**
     * Print a yellow warning message to STDOUT.
     */
    protected function warning(string $text): void
    {
        echo "\033[33m" . $text . "\033[0m" . PHP_EOL;
    }

    /**
     * Ask a yes/no confirmation question. Reads from STDIN.
     *
     * @return bool True if user answers 'y' or 'yes'
     */
    protected function confirm(string $question): bool
    {
        echo $question . ' [y/n] ';
        $answer = trim(fgets(STDIN));
        return in_array(strtolower($answer), ['y', 'yes'], true);
    }
}
