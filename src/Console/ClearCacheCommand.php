<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Illuminate\View\Engines\EngineResolver;
use Vusys\LaravelSmarty\SmartyEngine;

class ClearCacheCommand extends Command
{
    use ResolvesTemplateNames;

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

        // `--expire=abc` would otherwise cast to 0 — which means "clear
        // everything", the opposite of what a typo'd narrow clear wants.
        $expireOption = $this->option('expire');
        if (is_string($expireOption) && ! ctype_digit($expireOption)) {
            $this->error("Invalid --expire value [{$expireOption}]; expected a non-negative number of seconds.");

            return self::INVALID;
        }
        $expire = $expireOption !== null ? (int) $expireOption : null;
        $file = $this->option('file');
        $cacheId = $this->option('cache-id');
        $compileId = $this->option('compile-id');

        $count = is_string($file) && $file !== ''
            ? $smarty->clearCache($this->resolveTemplateName($smarty, $file), is_string($cacheId) ? $cacheId : null, is_string($compileId) ? $compileId : null, $expire)
            : $smarty->clearAllCache($expire);

        $this->info("Cleared {$count} Smarty cache file(s).");

        return self::SUCCESS;
    }
}
