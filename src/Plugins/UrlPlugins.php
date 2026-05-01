<?php

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

class UrlPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'route', static function (array $params): string {
            $name = $params['name'] ?? '';
            unset($params['name']);

            return route($name, $params);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'url', static fn (array $params): string => url($params['path'] ?? ''));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'asset', static fn (array $params): string => asset($params['path'] ?? ''));
    }
}
