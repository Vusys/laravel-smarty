<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Illuminate\View\Engines\EngineResolver;
use Vusys\LaravelSmarty\SmartyEngine;

class ClearCompiledCommand extends Command
{
    use ResolvesTemplateNames;

    protected $signature = 'smarty:clear-compiled
        {--file= : Clear compiled output for a specific template}
        {--compile-id= : Restrict to a compile_id}
        {--expire= : Only clear entries older than N seconds}';

    protected $description = 'Remove compiled Smarty templates.';

    public function handle(EngineResolver $resolver): int
    {
        /** @var SmartyEngine $engine */
        $engine = $resolver->resolve('smarty');
        $smarty = $engine->smarty();

        // Same guard as smarty:clear-cache — `--expire=abc` casting to 0
        // would clear everything instead of nothing.
        $expireOption = $this->option('expire');
        if (is_string($expireOption) && ! ctype_digit($expireOption)) {
            $this->error("Invalid --expire value [{$expireOption}]; expected a non-negative number of seconds.");

            return self::INVALID;
        }
        $expire = $expireOption !== null ? (int) $expireOption : null;
        $file = $this->option('file');
        $compileId = $this->option('compile-id');

        $count = $smarty->clearCompiledTemplate(
            is_string($file) && $file !== '' ? $this->resolveTemplateName($smarty, $file) : null,
            is_string($compileId) ? $compileId : null,
            $expire,
        );

        $this->info("Removed {$count} compiled Smarty file(s).");

        return self::SUCCESS;
    }
}
