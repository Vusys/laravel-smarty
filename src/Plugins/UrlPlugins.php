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

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'url', static function (array $params): string {
            return url($params['path'] ?? '');
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'asset', static function (array $params): string {
            return asset($params['path'] ?? '');
        });
    }
}
