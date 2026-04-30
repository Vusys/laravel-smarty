<?php

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

class LaravelPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field', static function (): string {
            return (string) csrf_field();
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'method_field', static function (array $params): string {
            $method = $params['method'] ?? '';

            return (string) method_field($method);
        });
    }
}
