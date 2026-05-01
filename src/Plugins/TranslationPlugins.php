<?php

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

class TranslationPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'lang', static function (array $params): string {
            $key = $params['key'] ?? '';
            unset($params['key']);

            return (string) __($key, $params);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'trans', static fn (string $key, array $replace = []): string => (string) __($key, $replace));
    }
}
