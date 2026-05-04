<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Debug;

use Illuminate\View\ViewException;
use Smarty\CompilerException;
use Vusys\LaravelSmarty\Debug\CompilerOverride;
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
    public function test_compiler_override_is_installed_and_active(): void
    {
        $this->assertTrue(CompilerOverride::isInstalled(), 'Stream wrapper should be registered.');
        $this->assertTrue(CompilerOverride::isAnchorVerified(), 'Replacement anchor should match Smarty source.');

        // Render any template and confirm the resolved compiler is our
        // subclass — i.e. that the stream-wrapper rewrite of Source.php
        // actually took effect.
        $smarty = $this->app->make(SmartyFactory::class)->make([$this->viewsPath]);
        $template = $smarty->createTemplate('hello.tpl', null, null, $smarty);
        $compiler = $template->getSource()->createCompiler();
        $this->assertInstanceOf(LineTrackingCompiler::class, $compiler);
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
