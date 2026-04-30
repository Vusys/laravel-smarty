<?php

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;
use Smarty\Template;

class FormPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field', static function (): string {
            return (string) csrf_field();
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'method_field', static function (array $params): string {
            return (string) method_field($params['method'] ?? '');
        });

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'old', static function (array $params) {
            return old($params['field'] ?? null, $params['default'] ?? null);
        });

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'error', static function ($params, $content, Template $template, &$repeat): string {
            static $stack = [];

            $field = $params['field'] ?? '';
            $errors = session('errors');
            $hasError = $errors && $errors->has($field);

            if ($repeat) {
                if ($hasError) {
                    $stack[] = $template->getTemplateVars('message');
                    $template->assign('message', $errors->first($field));
                }

                return '';
            }

            if ($hasError) {
                $template->assign('message', array_pop($stack));

                return (string) $content;
            }

            return '';
        });
    }
}
