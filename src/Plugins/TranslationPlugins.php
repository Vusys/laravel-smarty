<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

class TranslationPlugins
{
    public static function register(Smarty $smarty): void
    {
        // Escaped by default: replacement values ({lang key=... name=$user})
        // are interpolated by __() and function-plugin output bypasses
        // escape_html, while the equivalent {$key|trans} *is* auto-escaped
        // as a print expression. Escaping here keeps the two channels
        // byte-identical; `raw=true` opts out for trusted HTML lines.
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'lang', static function (array $params): string {
            $key = $params['key'] ?? '';
            $raw = (bool) ($params['raw'] ?? false);
            unset($params['key'], $params['raw']);

            $value = (string) __($key, $params);

            return $raw ? $value : PluginOutput::escape($value);
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'trans', static fn (string $key, array $replace = []): string => (string) __($key, $replace));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'lang_choice', static function (array $params): string {
            $key = $params['key'] ?? '';
            $count = (int) ($params['count'] ?? 0);
            $raw = (bool) ($params['raw'] ?? false);
            unset($params['key'], $params['count'], $params['raw']);

            $value = trans_choice($key, $count, $params);

            return $raw ? $value : PluginOutput::escape($value);
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'trans_choice', static fn (string $key, int $count, array $replace = []): string => trans_choice($key, $count, $replace));
    }
}
