<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Facades\Gate;
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

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'lang', static function (array $params): string {
            $key = $params['key'] ?? '';
            unset($params['key']);

            return (string) __($key, $params);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'trans', static function (string $key, array $replace = []): string {
            return (string) __($key, $replace);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'old', static function (array $params) {
            return old($params['field'] ?? null, $params['default'] ?? null);
        });

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
