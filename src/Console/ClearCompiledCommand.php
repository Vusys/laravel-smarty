<?php

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Illuminate\View\Engines\EngineResolver;
use Vusys\LaravelSmarty\SmartyEngine;

class ClearCompiledCommand extends Command
{
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

        $expire = $this->option('expire') !== null ? (int) $this->option('expire') : null;

        $count = $smarty->clearCompiledTemplate(
            $this->option('file'),
            $this->option('compile-id'),
            $expire,
        );

        $this->info("Removed {$count} compiled Smarty file(s).");

        return self::SUCCESS;
    }
}
