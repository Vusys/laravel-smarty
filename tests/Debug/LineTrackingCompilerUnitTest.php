<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Debug;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Smarty\Compiler\Template as SmartyTemplateCompiler;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Debug\LineTrackingCompiler;

/**
 * Unit tests for LineTrackingCompiler's defensive branches: the
 * peekLineNumber() closure that reaches into Smarty's private $parser
 * field to grab the lexer line, and compileTemplate's empty-path skip.
 *
 * Each branch represents a state the parent compiler can transiently be
 * in (no parser bound, no lex bound, lexer counters not yet populated)
 * during edge compiles like string resources. Driving these via a real
 * compile is racy because the parser/lex are wired up before any tag
 * actually compiles, so we exercise them directly via reflection.
 */
class LineTrackingCompilerUnitTest extends TestCase
{
    public function test_peek_line_number_returns_null_when_parser_is_unset(): void
    {
        $compiler = $this->makeCompiler();
        $this->setParser($compiler, null);

        $this->assertNull($this->invokePeek($compiler));
    }

    public function test_peek_line_number_returns_null_when_lex_is_unset(): void
    {
        $compiler = $this->makeCompiler();
        $this->setParser($compiler, new \stdClass);

        $this->assertNull($this->invokePeek($compiler));
    }

    public function test_peek_line_number_returns_null_when_neither_taglineno_nor_line_is_a_positive_int(): void
    {
        $compiler = $this->makeCompiler();
        $parser = new \stdClass;
        $parser->lex = new \stdClass;
        $parser->lex->taglineno = 0;
        $parser->lex->line = null;
        $this->setParser($compiler, $parser);

        $this->assertNull($this->invokePeek($compiler));
    }

    public function test_peek_line_number_falls_back_to_lex_line_when_taglineno_is_zero(): void
    {
        // Some early-compile states set $lex->line but not $taglineno (e.g.
        // before any tag has fully closed). The fallback keeps the marker
        // pointing somewhere useful in the source rather than nothing.
        $compiler = $this->makeCompiler();
        $parser = new \stdClass;
        $parser->lex = new \stdClass;
        $parser->lex->taglineno = 0;
        $parser->lex->line = 42;
        $this->setParser($compiler, $parser);

        $this->assertSame(42, $this->invokePeek($compiler));
    }

    public function test_peek_line_number_prefers_taglineno_when_available(): void
    {
        // taglineno is the line the *opening* of the tag was on, which
        // is what we actually want as the marker — line is wherever the
        // lexer cursor has advanced to.
        $compiler = $this->makeCompiler();
        $parser = new \stdClass;
        $parser->lex = new \stdClass;
        $parser->lex->taglineno = 7;
        $parser->lex->line = 99;
        $this->setParser($compiler, $parser);

        $this->assertSame(7, $this->invokePeek($compiler));
    }

    public function test_compile_print_expression_returns_parent_output_unchanged_when_no_line_is_known(): void
    {
        // peekLineNumber returns null when parser hasn't been wired up
        // (e.g. the helper is invoked outside an active parse). In that
        // case the marker prefix would be meaningless, so we fall through
        // to whatever parent::compilePrintExpression produced.
        $smarty = new Smarty;
        $compiler = new LineTrackingCompiler($smarty);
        $template = $smarty->createTemplate('string:irrelevant');
        $compiler->setTemplate($template);
        $this->setParser($compiler, null);

        $result = $compiler->compilePrintExpression('"hi"');

        $this->assertIsString($result);
        $this->assertStringNotContainsString('__SLM:', $result);
    }

    public function test_compile_template_skips_slf_header_when_source_path_is_empty(): void
    {
        // String resources have no file path, so the __SLF header would
        // be empty and useless. compileTemplate must short-circuit and
        // pass the parent's compiled output through unmodified.
        $smarty = new Smarty;
        $smarty->setCompileDir(sys_get_temp_dir().'/laravel-smarty-tests/lt-compile');
        $template = $smarty->createTemplate('string:hello {$x}');

        $compiler = new LineTrackingCompiler($smarty);
        $compiled = $compiler->compileTemplate($template);

        $this->assertIsString($compiled);
        $this->assertStringNotContainsString('__SLF:', $compiled);
    }

    private function makeCompiler(): LineTrackingCompiler
    {
        return new LineTrackingCompiler(new Smarty);
    }

    private function setParser(SmartyTemplateCompiler $compiler, mixed $parser): void
    {
        $prop = new ReflectionProperty(SmartyTemplateCompiler::class, 'parser');
        $prop->setValue($compiler, $parser);
    }

    private function invokePeek(LineTrackingCompiler $compiler): ?int
    {
        $method = new ReflectionMethod($compiler, 'peekLineNumber');

        return $method->invoke($compiler, $compiler);
    }
}
