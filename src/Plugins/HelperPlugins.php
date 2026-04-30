<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Js;
use Smarty\Smarty;
use Smarty\Template;

class HelperPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'json', static function ($value): string {
            return (string) Js::from($value);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'service', static function (array $params, Template $template): string {
            $template->assign($params['assign'] ?? '', app($params['name'] ?? ''));

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
