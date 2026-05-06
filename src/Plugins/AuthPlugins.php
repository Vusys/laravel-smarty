<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Facades\Gate;
use Smarty\Smarty;
use Smarty\Template;

class AuthPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'auth', static function ($params, $content, Template $template, &$repeat): string {
            static $stack = [];

            if ($content === null) {
                $user = auth()->guard($params['guard'] ?? null)->user();

                if ($user === null) {
                    $repeat = false;

                    return '';
                }

                $stack[] = $template->getTemplateVars('user');
                $template->assign('user', $user);

                return '';
            }

            if ($stack !== []) {
                $template->assign('user', array_pop($stack));
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
