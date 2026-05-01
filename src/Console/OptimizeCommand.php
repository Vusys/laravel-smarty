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

        $extension = '.'.ltrim(
            (string) ($this->option('extension') ?? $config->get('smarty.extension', 'tpl')),
            '.',
        );

        // Smarty writes a per-file progress trail to stdout. Capture so we can
        // route it through the Symfony console output instead of leaking past it.
        ob_start();
        $count = $smarty->compileAllTemplates($extension, (bool) $this->option('force'));
        $trail = trim((string) ob_get_clean());

        if ($trail !== '') {
            $this->line(strip_tags(str_replace('<br>', "\n", $trail)));
        }

        $this->info("Compiled {$count} template(s).");

        return self::SUCCESS;
    }
}
