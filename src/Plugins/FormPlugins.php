<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\ViewErrorBag;
use Smarty\Smarty;
use Smarty\Template;

class FormPlugins
{
    public static function register(Smarty $smarty): void
    {
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field', static fn (): string => (string) csrf_field(), false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_token', static fn (): string => (string) csrf_token(), false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'method_field', static fn (array $params): string => (string) method_field($params['method'] ?? ''));

        // Escaped by default: old() round-trips the user's previous
        // submission, and function-plugin output bypasses escape_html —
        // without this a failed validation reflects `"><script>` straight
        // back into the form. `raw=true` opts out (Blade's {!! !!} moment).
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'old', static function (array $params): string {
            $value = old($params['field'] ?? null, $params['default'] ?? null);

            // Array old-input (array form fields like `emails[]`) has no
            // single printable value — render nothing instead of "Array"
            // plus a conversion warning.
            if (! is_scalar($value)) {
                return '';
            }

            $value = (string) $value;

            return ($params['raw'] ?? false) ? $value : PluginOutput::escape($value);
        }, false);

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
