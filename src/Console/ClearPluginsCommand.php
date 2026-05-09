<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;

class ClearPluginsCommand extends Command
{
    protected $signature = 'smarty:plugins:clear';

    protected $description = 'Delete the cached class-backed Smarty plugin map.';

    public function handle(): int
    {
        PluginCacheStore::clear();

        $this->info('Smarty plugin discovery cache cleared.');

        return self::SUCCESS;
    }
}
