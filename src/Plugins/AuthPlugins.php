<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Facades\Gate;
use Smarty\Smarty;

class AuthPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'auth', static function ($params, $content, $template, &$repeat): string {
            if ($repeat) {
                return '';
            }

            return auth()->guard($params['guard'] ?? null)->check() ? (string) $content : '';
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'guest', static function ($params, $content, $template, &$repeat): string {
            if ($repeat) {
                return '';
            }

            return auth()->guard($params['guard'] ?? null)->guest() ? (string) $content : '';
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'can', static function ($params, $content, $template, &$repeat): string {
            if ($repeat) {
                return '';
            }

            $arguments = array_key_exists('model', $params) ? [$params['model']] : [];

            return Gate::check($params['ability'] ?? '', $arguments) ? (string) $content : '';
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'cannot', static function ($params, $content, $template, &$repeat): string {
            if ($repeat) {
                return '';
            }

            $arguments = array_key_exists('model', $params) ? [$params['model']] : [];

            return Gate::denies($params['ability'] ?? '', $arguments) ? (string) $content : '';
        });
    }
}
