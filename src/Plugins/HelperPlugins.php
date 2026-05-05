<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Smarty\Smarty;
use Smarty\Template;

class HelperPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'json', static fn ($value): string => (string) Js::from($value));

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'markdown', static fn ($value): string => (string) Str::markdown((string) $value));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'config', static fn (array $params) => config($params['key'] ?? '', $params['default'] ?? null));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'session', static fn (array $params) => session($params['key'] ?? null, $params['default'] ?? null));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'service', static function (array $params, Template $template): string {
            $template->assign($params['assign'] ?? '', resolve($params['name'] ?? ''));

            return '';
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'dump', static function (array $params): string {
            foreach ($params as $value) {
                dump($value);
            }

            return '';
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'dd', static function (array $params): string {
            dd(...array_values($params));
        });
    }
}
