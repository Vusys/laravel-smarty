<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Facades\Gate;
use Smarty\Smarty;

class AuthPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'auth', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                if (! auth()->guard($params['guard'] ?? null)->check()) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'guest', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                if (! auth()->guard($params['guard'] ?? null)->guest()) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'can', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = array_key_exists('model', $params) ? [$params['model']] : [];

                if (! Gate::check($params['ability'] ?? '', $arguments)) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'cannot', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = array_key_exists('model', $params) ? [$params['model']] : [];

                if (! Gate::denies($params['ability'] ?? '', $arguments)) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'canany', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = array_key_exists('model', $params) ? [$params['model']] : [];
                $abilities = (array) ($params['abilities'] ?? []);

                if (! Gate::any($abilities, $arguments)) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'canall', static function ($params, $content, $template, &$repeat): string {
            if ($content === null) {
                $arguments = array_key_exists('model', $params) ? [$params['model']] : [];
                $abilities = (array) ($params['abilities'] ?? []);

                if ($abilities === [] || ! Gate::check($abilities, $arguments)) {
                    $repeat = false;
                }

                return '';
            }

            return (string) $content;
        });
    }
}
