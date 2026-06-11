<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Smarty\Smarty;
use Smarty\Template;
use Vusys\LaravelSmarty\Compile\HelperModifierExtension;

class HelperPlugins
{
    public static function register(Smarty $smarty): void
    {
        // Compile-time handlers for json/markdown mark their print
        // expressions raw so the (already safe) output isn't escaped a
        // second time. The runtime callbacks below stay registered for
        // introspection and dynamic invocation; the extension wins at
        // compile time.
        $smarty->addExtension(new HelperModifierExtension);

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'json', self::js(...));

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'markdown', self::markdown(...));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'config', static fn (array $params, Template $template): string => self::assignOrStringify(
            config($params['key'] ?? '', $params['default'] ?? null),
            $params,
            $template,
        ));

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'session', static fn (array $params, Template $template): string => self::assignOrStringify(
            session($params['key'] ?? null, $params['default'] ?? null),
            $params,
            $template,
        ), false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'service', static function (array $params, Template $template): string {
            $template->assign($params['assign'] ?? '', resolve($params['name'] ?? ''));

            return '';
        }, false);

        // `dump` and `dd` are debugging aids — gated to local/testing so a
        // stray `{dump}` left in a template can't leak internals or halt a
        // production page. Outside those envs they're silent no-ops.
        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'dump', static function (array $params): string {
            if (! app()->environment('local', 'testing')) {
                return '';
            }

            foreach ($params as $value) {
                dump($value);
            }

            return '';
        }, false);

        $smarty->registerPlugin(Smarty::PLUGIN_FUNCTION, 'dd', static function (array $params): string {
            if (! app()->environment('local', 'testing')) {
                return '';
            }

            dd(...array_values($params));
        }, false);
    }

    /**
     * Shared tail for the `{config}`/`{session}` function tags. When an
     * `assign=` param is given the value is bound to that template
     * variable (returning '' so nothing prints), which is the only way to
     * surface a non-scalar config/session value — an array reached the
     * tag as inline output would otherwise echo a useless "Array" and
     * raise a PHP "Array to string conversion" warning. Inline, only
     * stringable values render; anything else (array, object, null)
     * returns ''.
     *
     * @param  array<string, mixed>  $params
     */
    private static function assignOrStringify(mixed $value, array $params, Template $template): string
    {
        $assign = $params['assign'] ?? null;

        if (is_string($assign) && $assign !== '') {
            $template->assign($assign, $value);

            return '';
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * `|json` — Js::from() output: JSON encoded then escaped for embedding
     * in JS string and HTML-attribute contexts. Blade's `@js`, not `@json`.
     * Called from compiled templates (see JsonModifierCompiler).
     */
    public static function js(mixed $value): string
    {
        return (string) Js::from($value);
    }

    /**
     * `|markdown` — CommonMark with hardened options. The defaults
     * (`html_input: allow`) pass author HTML through verbatim; since this
     * modifier's output is emitted raw (see MarkdownModifierCompiler),
     * embedded HTML is escaped and `javascript:`/`data:` links are
     * stripped so the result is safe even on user-supplied content.
     * Called from compiled templates.
     */
    public static function markdown(mixed $value): string
    {
        if (! is_scalar($value) && ! $value instanceof \Stringable) {
            return '';
        }

        return (string) Str::markdown((string) $value, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
