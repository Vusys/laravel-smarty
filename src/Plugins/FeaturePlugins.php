<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Laravel\Pennant\Feature;
use Smarty\Smarty;

class FeaturePlugins
{
    public static function register(Smarty $smarty): void
    {
        // Pennant is an optional first-party Laravel package. Stay quiet
        // when it isn't installed so apps that don't use feature flags
        // aren't forced to pull it in — same shape as NumberPlugins'
        // Laravel-10 guard.
        if (! static::pennantInstalled()) {
            return;
        }

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'feature', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $name = $params['name'] ?? '';
                $active = array_key_exists('for', $params)
                    ? Feature::for($params['for'])->active($name)
                    : Feature::active($name);

                $inverse = (bool) ($params['inverse'] ?? false);

                if ($active === $inverse) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'feature_active', static fn (string $name, $for = null): bool => func_num_args() >= 2
            ? Feature::for($for)->active($name)
            : Feature::active($name), false);
    }

    protected static function pennantInstalled(): bool
    {
        return class_exists(Feature::class);
    }
}
