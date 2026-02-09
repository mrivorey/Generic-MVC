<?php

namespace App\Commands;

use App\Core\Cache;

class CacheClearCommand extends Command
{
    protected string $name = 'cache:clear';
    protected string $description = 'Clear the application cache';

    public function execute(array $args): int
    {
        Cache::clear();
        $this->success('Application cache cleared successfully.');
        return 0;
    }
}
