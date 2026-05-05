<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Debug;

use Illuminate\View\ViewException;
use Smarty\CompilerException;
use Vusys\LaravelSmarty\Debug\LineTrackingCompiler;
use Vusys\LaravelSmarty\SmartyFactory;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * End-to-end coverage of the runtime-error → .tpl source-line mapping
 * built across LineTrackingCompiler (compile-time marker injection),
 * SourceMap (runtime lookup), SmartyEngine (try/catch wrap), and
 * SmartyExceptionMapper (Laravel renderer trace rewrite).
 *
 * Each test renders a fixture template that throws and asserts that
 * the resulting ViewException's file/line points at the .tpl source
 * exactly where the offending Smarty tag is — not the compiled file.
 */
class SourceMapTest extends TestCase
{
    public function test_compiler_injection_is_active_on_every_template(): void
    {
        // Every Template returned by doCreateTemplate must arrive with our
        // LineTrackingCompiler already wired onto its private $compiler
        // field, so getCompiler()'s lazy init never falls through to
        // Source::createCompiler() (which is hard-coded to vanilla Smarty).
        $smarty = $this->app->make(SmartyFactory::class)->make([$this->viewsPath]);
        $template = $smarty->createTemplate('hello.tpl', null, null, $smarty);

        $this->assertInstanceOf(LineTrackingCompiler::class, $template->getCompiler());
    }

    public function test_error_at_top_level_maps_to_tpl_line(): void
    {
        $exception = $this->captureRender('errors.runtime_simple', ['user' => null]);

        $this->assertInstanceOf(ViewException::class, $exception);
        $this->assertSame($this->fixturePath('errors/runtime_simple.tpl'), $exception->getFile());
        $this->assertSame(2, $exception->getLine());
        $this->assertStringContainsString('(View: '.$this->fixturePath('errors/runtime_simple.tpl').')', $exception->getMessage());
    }

    public function test_error_inside_foreach_maps_to_tpl_line(): void
    {
        $exception = $this->captureRender('errors.runtime_loop', ['items' => [(object) ['x' => 1]]]);

        $this->assertSame($this->fixturePath('errors/runtime_loop.tpl'), $exception->getFile());
        $this->assertSame(3, $exception->getLine());
    }

    public function test_error_in_dense_flush_left_template_maps_to_tpl_line(): void
    {
        // The previous postfilter-based mapping degraded to "line 1" for
        // sequences of consecutive Smarty tags with no whitespace between
        // them — Smarty's compiler merges those into a single PHP block
        // and there is no inline-HTML to anchor on. The compiler-subclass
        // approach emits a marker per tag regardless, so we expect the
        // exact tag line.
        $exception = $this->captureRender('errors.runtime_dense', ['items' => (object) []]);

        $this->assertSame($this->fixturePath('errors/runtime_dense.tpl'), $exception->getFile());
        $this->assertSame(3, $exception->getLine());
    }

    public function test_error_inside_extends_child_block_maps_to_child_tpl_line(): void
    {
        $exception = $this->captureRender('errors.runtime_extends', ['user' => null]);

        $this->assertSame($this->fixturePath('errors/runtime_extends.tpl'), $exception->getFile());
        $this->assertSame(5, $exception->getLine());
    }

    public function test_error_inside_included_partial_maps_to_partial_tpl_line(): void
    {
        $exception = $this->captureRender('errors.runtime_include');

        $this->assertSame($this->fixturePath('errors/included_broken.tpl'), $exception->getFile());
        $this->assertSame(3, $exception->getLine());
    }

    public function test_compile_error_preserves_source_path_and_line(): void
    {
        $exception = $this->captureRender('errors.compile_error');

        $this->assertInstanceOf(ViewException::class, $exception);
        $this->assertInstanceOf(CompilerException::class, $exception->getPrevious());
        $this->assertSame($this->fixturePath('errors/compile_error.tpl'), $exception->getFile());
        $this->assertGreaterThan(1, $exception->getLine());
    }

    public function test_compile_error_in_included_partial_points_at_partial(): void
    {
        // Compile error in an {include}d child should report the
        // partial's path — not the wrapper's. CompilerException carries
        // the source filename as Smarty parsed it from the lexer state,
        // and our short-circuit in remapException preserves it.
        $exception = $this->captureRender('errors.compile_error_in_include');

        $this->assertInstanceOf(CompilerException::class, $exception->getPrevious());
        $this->assertSame($this->fixturePath('errors/compile_error_partial.tpl'), $exception->getFile());
    }

    public function test_error_inside_inline_include_maps_to_child_partial(): void
    {
        // {include file="..." inline} merges the child's compiled bytes
        // into the parent. Each child still gets its own __SLF header
        // emitted by compileTemplate, so the child path attribution
        // survives the inline merge.
        $exception = $this->captureRender('errors.runtime_inline_include');

        $this->assertSame($this->fixturePath('errors/included_broken.tpl'), $exception->getFile());
        $this->assertSame(3, $exception->getLine());
    }

    public function test_error_inside_template_function_body_via_call(): void
    {
        // {function} body chunks are flushed into $blockOrFunctionCode
        // and appended after the post-filtered main body. The body's
        // __SLM markers travel with the chunks; the lookup walks back
        // far enough to hit them even though they sit past the main
        // template's tail.
        $exception = $this->captureRender('errors.runtime_function_call', ['user' => null]);

        $this->assertSame($this->fixturePath('errors/runtime_function_call.tpl'), $exception->getFile());
        $this->assertSame(2, $exception->getLine());
    }

    public function test_error_inside_template_function_body_via_short_tag(): void
    {
        // Same as the {call} case but invoked via the short tag form
        // ({render_user}) — that path goes through canCompileTemplateFunctionCall
        // and a separate getTagCompiler('call') invocation in compileTag2.
        $exception = $this->captureRender('errors.runtime_function_short', ['user' => null]);

        $this->assertSame($this->fixturePath('errors/runtime_function_short.tpl'), $exception->getFile());
        $this->assertSame(2, $exception->getLine());
    }

    public function test_error_inside_capture_body_unmasks_runtime_rethrow(): void
    {
        // CaptureRuntime::close() rethrows "Not matching {capture}{/capture}"
        // when the user's body throw skips its bookkeeping, masking the
        // real error. remapException walks the previous() chain so the
        // inner Error's .tpl.php frame still wins, and the user sees the
        // real "Call to a member function ... on null" message.
        $exception = $this->captureRender('errors.runtime_capture', ['user' => null]);

        $this->assertSame($this->fixturePath('errors/runtime_capture.tpl'), $exception->getFile());
        $this->assertSame(3, $exception->getLine());
        $this->assertStringContainsString('getAuthIdentifier', $exception->getMessage());
        $this->assertStringNotContainsString('Not matching', $exception->getMessage());
    }

    public function test_error_inside_if_condition_maps_to_if_line(): void
    {
        $exception = $this->captureRender('errors.runtime_if_condition', ['user' => null]);

        $this->assertSame($this->fixturePath('errors/runtime_if_condition.tpl'), $exception->getFile());
        $this->assertSame(2, $exception->getLine());
    }

    private function captureRender(string $view, array $data = []): ViewException
    {
        try {
            view($view, $data)->render();
        } catch (ViewException $e) {
            return $e;
        }

        $this->fail("Expected view '{$view}' to throw a ViewException; it rendered without error.");
    }

    private function fixturePath(string $relative): string
    {
        return $this->viewsPath.'/'.$relative;
    }
}
