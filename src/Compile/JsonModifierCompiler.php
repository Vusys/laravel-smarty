<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Compile;

use Smarty\Compile\Modifier\Base;
use Smarty\Compiler\Template;
use Vusys\LaravelSmarty\Plugins\HelperPlugins;

/**
 * Compiles `{$x|json}` to a HelperPlugins::js() call and marks the
 * expression raw — Js::from() output is escaped for JS string/HTML-attr
 * contexts already, so the escape_html pass on top of it corrupts the
 * payload instead of protecting anything.
 */
class JsonModifierCompiler extends Base
{
    /**
     * @param  array<int, string>  $params
     */
    public function compile($params, Template $compiler): string
    {
        $compiler->setRawOutput(true);

        return '\\'.HelperPlugins::class.'::js('.$params[0].')';
    }
}
