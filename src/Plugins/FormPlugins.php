<?php

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\ViewErrorBag;
use Smarty\Smarty;
use Smarty\Template;

class FormPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field', static fn (): string => (string) csrf_field(), false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'method_field', static fn (array $params): string => (string) method_field($params['method'] ?? ''));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'old', static fn (array $params) => old($params['field'] ?? null, $params['default'] ?? null), false);

        $smarty->registerPlugin(Smarty::PLUGIN_BLOCK, 'error', static function ($params, $content, Template $template, &$repeat): string {
            $field = $params['field'] ?? '';
            $errors = session('errors');
            $bag = $errors instanceof ViewErrorBag ? $errors->getBag('default') : null;
            $hasError = $bag !== null && $bag->has($field);

            if ($content === null) {
                if (! $hasError) {
                    $repeat = false;

                    return '';
                }

                BlockState::push('error.message', $template->getTemplateVars('message'));
                $template->assign('message', $bag->first($field));

                return '';
            }

            if (BlockState::hasEntries('error.message')) {
                $template->assign('message', BlockState::pop('error.message'));
            }

            return (string) $content;
        }, false);
    }
}
