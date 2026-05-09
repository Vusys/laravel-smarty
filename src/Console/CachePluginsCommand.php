<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Vusys\LaravelSmarty\LaravelSmarty;

class CachePluginsCommand extends Command
{
    protected $signature = 'smarty:plugins:cache';

    protected $description = 'Discover class-backed Smarty plugins and write the result to bootstrap/cache.';

    public function handle(): int
    {
        $descriptors = LaravelSmarty::rebuildDiscoveryCache();

        $count = count($descriptors);
        $this->info("Cached {$count} class-backed Smarty plugin(s).");

        return self::SUCCESS;
    }
}
