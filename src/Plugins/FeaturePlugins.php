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
        if (! class_exists(Feature::class)) {
            return;
        }

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'feature', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $name = $params['name'] ?? '';
                $active = array_key_exists('for', $params)
                    ? Feature::for($params['for'])->active($name)
                    : Feature::active($name);

                if (! $active) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        }, false);
    }
}
