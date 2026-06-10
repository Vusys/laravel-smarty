<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Smarty\Smarty;

/**
 * Escaping helper for function-plugin output.
 *
 * Smarty's `escape_html` pass only wraps print expressions — registered
 * function-plugin output is echoed verbatim (see vendor's
 * PrintExpressionCompiler). Plugins whose output can carry user-supplied
 * content ({old}, {lang}, {lang_choice}) therefore escape here, using the
 * exact same call PrintExpressionCompiler compiles in, so `{lang key=$k}`
 * and `{$k|trans}` produce identical bytes.
 *
 * @internal
 */
final class PluginOutput
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, Smarty::$_CHARSET);
    }
}
