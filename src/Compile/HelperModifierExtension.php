<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Compile;

use Smarty\Compile\Modifier\ModifierCompilerInterface;
use Smarty\Extension\Base;

/**
 * Compile-time handlers for the package's `json` and `markdown` modifiers.
 *
 * Both produce output that must land in the page unescaped — Js::from()
 * is already script-safe, and markdown() sanitizes via html_input=escape
 * (see HelperPlugins). A modifier *compiler*, unlike the runtime callback
 * registered via registerPlugin(), can call setRawOutput(true) on the
 * template compiler, which tells the surrounding print expression to skip
 * the escape_html pass. Without it, `{$x|json}` double-escapes and users
 * are pushed to `nofilter` — which turns off all protection, not just the
 * redundant layer.
 *
 * Extension-provided modifier compilers win over registered callbacks in
 * Smarty's dispatch order (Compile\ModifierCompiler), and the security
 * policy's isTrustedModifier() check still runs first, so the Strict
 * allow-list applies unchanged. The runtime callbacks stay registered for
 * introspection and dynamic invocation.
 */
class HelperModifierExtension extends Base
{
    public function getModifierCompiler(string $modifier): ?ModifierCompilerInterface
    {
        return match ($modifier) {
            'json' => new JsonModifierCompiler,
            'markdown' => new MarkdownModifierCompiler,
            default => null,
        };
    }
}
