<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Foundation\Vite;
use Smarty\Smarty;

class VitePlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'vite', static function (array $params): string {
            $entrypoints = $params['entrypoints'] ?? [];
            $buildDirectory = $params['build_directory'] ?? null;

            return (string) resolve(Vite::class)($entrypoints, $buildDirectory);
        });

        $smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'vite_react_refresh',
            static fn (): string => (string) resolve(Vite::class)->reactRefresh(),
        );
    }
}
