<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Compile;

use Smarty\Compile\Modifier\Base;
use Smarty\Compiler\Template;
use Vusys\LaravelSmarty\Plugins\HelperPlugins;

/**
 * Compiles `{$x|markdown}` to a HelperPlugins::markdown() call and marks
 * the expression raw. The produced HTML is safe to emit verbatim because
 * markdown() renders with html_input=escape + allow_unsafe_links=false —
 * the markup in the output comes from CommonMark, never from the input.
 */
class MarkdownModifierCompiler extends Base
{
    /**
     * @param  array<int, string>  $params
     */
    public function compile($params, Template $compiler): string
    {
        $compiler->setRawOutput(true);

        return '\\'.HelperPlugins::class.'::markdown('.$params[0].')';
    }
}
