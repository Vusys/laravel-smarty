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
        }, false);

        $smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'vite_react_refresh',
            static fn (): string => (string) resolve(Vite::class)->reactRefresh(),
            false,
        );

        // Per-request value under strict CSP — must re-resolve on every
        // render so warm-cache output doesn't ship a stale nonce.
        $smarty->registerPlugin(
            Smarty::PLUGIN_FUNCTION,
            'csp_nonce',
            static fn (): string => (string) resolve(Vite::class)->cspNonce(),
            false,
        );

        // Vite's manifest is build-time, but the resolved URL still
        // varies between hot mode and a built deployment. Stay non-
        // cacheable for the same reason {vite} is.
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'vite_asset', static function (array $params): string {
            $path = $params['path'] ?? '';
            $buildDirectory = $params['build_directory'] ?? null;

            return resolve(Vite::class)->asset($path, $buildDirectory);
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'vite_content', static function (array $params): string {
            $path = $params['path'] ?? '';
            $buildDirectory = $params['build_directory'] ?? null;

            return resolve(Vite::class)->content($path, $buildDirectory);
        }, false);
    }
}
