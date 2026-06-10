<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

/**
 * Blade's @env / @production as lazy-body block plugins.
 *
 * These are deliberately the only channel for templates to read the app
 * environment: {config key='app.env'} is banned under the Strict policy
 * (it reads *any* config key), while a yes/no environment gate leaks
 * nothing sensitive. Both blocks are nocache — environment is deploy
 * state, but a page cache can outlive a deploy or be shared across
 * differently-configured nodes.
 *
 * Like {auth}/{feature}, the hidden arm never evaluates its body, and
 * `inverse=true` renders the body when the check fails ({env
 * names='production' inverse=true} ≡ Blade's "not production").
 */
class EnvironmentPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'env', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $names = $params['names'] ?? [];
                if (is_string($names)) {
                    $names = array_filter(array_map(trim(...), explode(',', $names)), static fn (string $name): bool => $name !== '');
                }
                $names = array_values((array) $names);
                $inverse = (bool) ($params['inverse'] ?? false);

                // No names is a programming mistake and fails closed in
                // both arms — same policy as {canany}'s empty abilities.
                if ($names === []) {
                    $repeat = false;

                    return '';
                }

                if (app()->environment($names) === $inverse) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'production', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $inverse = (bool) ($params['inverse'] ?? false);

                if (app()->environment('production') === $inverse) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);
    }
}
