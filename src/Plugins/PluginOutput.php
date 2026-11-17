<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Contracts\Support\Htmlable;
use Smarty\Smarty;
use Stringable;
use TypeError;

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

    /**
     * What an escaped print expression (`{$value}` under escape_html) emits.
     * LineTrackingCompiler compiles every such tag to call this in place of
     * Smarty's inline htmlspecialchars(), with the same arguments. An
     * Htmlable is already HTML, as in Blade's e(): `{$paginator->links()}`,
     * an HtmlString, or a custom modifier's Htmlable return is printed as is.
     * Everything else is escaped exactly as before (#68).
     */
    public static function printed(mixed $value, int $flags = ENT_QUOTES, ?string $charset = null): string
    {
        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        $string = match (true) {
            is_string($value) => $value,
            is_scalar($value), $value === null, $value instanceof Stringable => (string) $value,
            // what the (string) cast Smarty compiles in printed for an array
            is_array($value) => 'Array',
            default => throw new TypeError('Cannot print a '.get_debug_type($value).' in a template.'),
        };

        return htmlspecialchars($string, $flags, $charset ?? Smarty::$_CHARSET);
    }
}
