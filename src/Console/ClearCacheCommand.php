<?php

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Illuminate\View\Engines\EngineResolver;
use Vusys\LaravelSmarty\SmartyEngine;

class ClearCacheCommand extends Command
{
    protected $signature = 'smarty:clear-cache
        {--file= : Clear cache for a specific template file}
        {--cache-id= : Restrict to a cache_id group}
        {--compile-id= : Restrict to a compile_id}
        {--expire= : Only clear entries older than N seconds}';

    protected $description = 'Clear Smarty rendered output cache.';

    public function handle(EngineResolver $resolver): int
    {
        /** @var SmartyEngine $engine */
        $engine = $resolver->resolve('smarty');
        $smarty = $engine->smarty();

        $expire = $this->option('expire') !== null ? (int) $this->option('expire') : null;
        $file = $this->option('file');

        $count = $file
            ? $smarty->clearCache($file, $this->option('cache-id'), $this->option('compile-id'), $expire)
            : $smarty->clearAllCache($expire);

        $this->info("Cleared {$count} Smarty cache file(s).");

        return self::SUCCESS;
    }
}
