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
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'trans', static fn (string $key, array $replace = []): string => (string) __($key, $replace));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'lang_choice', static function (array $params): string {
            $key = $params['key'] ?? '';
            $count = (int) ($params['count'] ?? 0);
            unset($params['key'], $params['count']);

            return trans_choice($key, $count, $params);
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'trans_choice', static fn (string $key, int $count, array $replace = []): string => trans_choice($key, $count, $replace));
    }
}
