<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\View\Engines\EngineResolver;
use Vusys\LaravelSmarty\SmartyEngine;

class OptimizeCommand extends Command
{
    protected $signature = 'smarty:optimize
        {--extension= : Template extension to scan (defaults to smarty.extension)}
        {--force : Recompile templates even if compiled output is up to date}';

    protected $description = 'Pre-compile every Smarty template under the configured view paths.';

    public function handle(EngineResolver $resolver, ConfigRepository $config): int
    {
        /** @var SmartyEngine $engine */
        $engine = $resolver->resolve('smarty');
        $smarty = $engine->smarty();

        $ext = $this->option('extension') ?? $config->get('smarty.extension', 'tpl');
        $extension = '.'.ltrim(is_string($ext) ? $ext : 'tpl', '.');

        // Vendor bug: compileAll() copies its $force_compile argument onto a
        // *clone* it never uses — templates are created from the original
        // instance, so the argument is dead and --force silently no-ops
        // whenever smarty.force_compile is off (production, exactly where a
        // deploy hook runs this). Toggle the real instance around the call.
        $force = (bool) $this->option('force');
        $originalForceCompile = $smarty->force_compile;
        if ($force) {
            $smarty->setForceCompile(true);
        }

        // Smarty writes a per-file progress trail to stdout. Capture so we can
        // route it through the Symfony console output instead of leaking past it.
        ob_start();
        try {
            $count = $smarty->compileAllTemplates($extension, $force);
        } finally {
            $trail = trim((string) ob_get_clean());
            $smarty->setForceCompile($originalForceCompile);
        }

        if ($trail !== '') {
            $this->line($trail);
        }

        // Vendor compileAll() swallows per-template exceptions, echoes an
        // error marker into the trail, and reports only the success count
        // — the sentinel scan is the sole error channel it exposes. Without
        // it the command exits 0 on broken templates and deploy pipelines
        // can't gate on pre-compilation.
        $errors = substr_count($trail, '------>Error:');
        if ($errors > 0) {
            $this->error("{$errors} template(s) failed to compile.");

            return self::FAILURE;
        }

        $this->info("Compiled {$count} template(s).");

        return self::SUCCESS;
    }
}
