<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Compile;

use Smarty\Compile\Modifier\Base;
use Smarty\Compiler\Template;

/**
 * Marks a modifier's surrounding expression nocache at compile time.
 *
 * Smarty honours the `cacheable` flag for registered *function* and
 * *block* plugins (via BCPluginsAdapter's handler wrappers) but silently
 * drops it for modifiers — getModifierCallback() returns the bare
 * callback, and ModifierCompiler compiles it inline with no nocache
 * handling. So a request- or locale-coupled modifier like |trans or
 * |currency would get its first render's output baked into the page
 * cache no matter what flag it was registered with.
 *
 * This compiler produces the exact same runtime call the default
 * dispatch would, but flips the compiler's tag_nocache flag first, which
 * wraps the expression in a {nocache} region — re-evaluated on every
 * cache hit, exactly like the {lang}/{route} function tags.
 */
class NocacheModifierCompiler extends Base
{
    public function __construct(private readonly string $modifier) {}

    /**
     * @param  array<int, string>  $params
     */
    public function compile($params, Template $compiler): string
    {
        $compiler->tag_nocache = true;

        return sprintf(
            '$_smarty_tpl->getSmarty()->getModifierCallback(%s)(%s)',
            var_export($this->modifier, true),
            implode(',', $params),
        );
    }
}
