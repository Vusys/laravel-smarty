<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Debug;

use Closure;
use Smarty\Compiler\Template as SmartyTemplateCompiler;
use Smarty\Template;

/**
 * Drop-in replacement for Smarty's template compiler that emits source
 * line markers into the compiled PHP output.
 *
 * Smarty's compiler does not annotate its compiled output with the
 * source line numbers it knows during parsing, so a runtime error
 * inside a template body lands on a line of compiled PHP with no easy
 * way back to the .tpl. By overriding compileTag() and
 * compilePrintExpression() and reading the lexer's taglineno (the
 * line the tag opened on), we prefix each tag's compiled chunk with a
 * /\*__SLM:N*\/ marker. compileTemplate() also prepends a
 * /\*__SLF:/abs/path*\/ header. SourceMap::lookup() reads both at
 * error time and rewrites the stack frame back to the source.
 *
 * This compiler is installed by rewriting one line of Smarty's
 * Template/Source.php on autoload — see CompilerOverride. The
 * Smarty parent class declares `private $parser`, which is invisible
 * from a subclass; the bound closure in peekLineNumber() is how we
 * reach into it without forking the vendor file.
 */
class LineTrackingCompiler extends SmartyTemplateCompiler
{
    /**
     * @param  string  $tag
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $parameter
     * @return string|false
     */
    public function compileTag($tag, $args, $parameter = [])
    {
        // Capture lexer line BEFORE descending into the tag's compile —
        // nested compileTag calls advance the lexer cursor. Smarty's
        // $parser property is private on the parent class so we have to
        // reach in via a bound closure rather than $this->parser.
        $line = self::peekLineNumber($this);

        $result = parent::compileTag($tag, $args, $parameter);

        if ($result === '' || $line === null) {
            return $result;
        }

        return '<?php /*__SLM:'.$line.'*/ ?>'.$result;
    }

    /**
     * Print expressions like {$x->method()} go through compilePrintExpression,
     * not compileTag, so cover that path with the same marker treatment.
     *
     * @param  string  $value
     * @param  array<string, mixed>  $attributes
     * @param  array<int, mixed>|null  $modifiers
     */
    public function compilePrintExpression($value, $attributes = [], $modifiers = null): string
    {
        $line = self::peekLineNumber($this);

        $result = parent::compilePrintExpression($value, $attributes, $modifiers);

        if ($result === '' || $line === null) {
            return $result;
        }

        return '<?php /*__SLM:'.$line.'*/ ?>'.$result;
    }

    private static ?Closure $linePeeker = null;

    private static function peekLineNumber(SmartyTemplateCompiler $compiler): ?int
    {
        if (self::$linePeeker === null) {
            self::$linePeeker = Closure::bind(
                static function (SmartyTemplateCompiler $self): ?int {
                    // $parser is private on the parent — accessible only
                    // through this rebound closure. It is also nullable
                    // at points outside an active compile.
                    /** @var object|null $parser */
                    $parser = $self->parser;
                    if (! is_object($parser)) {
                        return null;
                    }
                    /** @var object|null $lex */
                    $lex = $parser->lex ?? null;
                    if (! is_object($lex)) {
                        return null;
                    }
                    $taglineno = $lex->taglineno ?? null;
                    $line = $lex->line ?? null;

                    if (is_int($taglineno) && $taglineno > 0) {
                        return $taglineno;
                    }
                    if (is_int($line) && $line > 0) {
                        return $line;
                    }

                    return null;
                },
                null,
                SmartyTemplateCompiler::class,
            );
        }

        return (self::$linePeeker)($compiler);
    }

    public function compileTemplate(Template $template)
    {
        $compiled = parent::compileTemplate($template);
        $path = (string) ($template->getSource()?->getFilepath() ?? '');

        if ($path === '') {
            return $compiled;
        }

        $safe = str_replace(['*/', "\n", "\r"], ['*\\/', ' ', ''], $path);

        return '<?php /*__SLF:'.$safe.'*/ ?>'.$compiled;
    }
}
