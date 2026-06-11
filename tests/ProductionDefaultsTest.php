<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewException;

/**
 * The rest of the suite runs with force_compile=true (so fixture edits
 * always take effect), which means the production-shaped permutation —
 * compile once, reuse via compile_check — was otherwise never
 * exercised. These tests run on the package's default config.
 */
class ProductionDefaultsTest extends TestCase
{
    private string $prodViewsPath;

    protected function setUp(): void
    {
        // Own copy of the template with a controlled mtime: on a fresh
        // CI checkout every tracked fixture's mtime is "just now", which
        // would race the backdated compiled file through compile_check.
        $this->prodViewsPath = sys_get_temp_dir().'/laravel-smarty-tests/prod-views';
        $files = new Filesystem;
        $files->deleteDirectory($this->prodViewsPath);
        $files->ensureDirectoryExists($this->prodViewsPath);
        $files->copy(__DIR__.'/Fixtures/views/hello.tpl', $this->prodViewsPath.'/hello.tpl');
        touch($this->prodViewsPath.'/hello.tpl', time() - 600);

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        // Deliberately NOT calling parent: parent sets force_compile=true.
        $app['config']->set('view.paths', [$this->prodViewsPath, $this->viewsPath]);
        $app['config']->set('smarty.compile_path', $this->compilePath);
        $app['config']->set('smarty.cache_path', $this->cachePath);
    }

    public function test_compiled_template_is_reused_on_a_warm_render(): void
    {
        $first = view('hello', ['name' => 'World'])->render();

        $files = new Filesystem;
        $compiled = $files->allFiles($this->compilePath);
        $this->assertCount(1, $compiled);

        // Backdate the compiled file (still newer than the source, so
        // compile_check stays satisfied): if the second render
        // recompiles, the write bumps mtime and the assertion catches it.
        $path = $compiled[0]->getPathname();
        $backdated = time() - 300;
        touch($path, $backdated);
        clearstatcache(true, $path);

        $second = view('hello', ['name' => 'World'])->render();

        clearstatcache(true, $path);
        $this->assertSame($first, $second);
        $this->assertSame($backdated, filemtime($path), 'Warm render must reuse the compiled template, not rewrite it.');
    }

    public function test_error_mapping_still_works_on_a_warm_compile(): void
    {
        // The source-map markers live in the compiled file; a warm render
        // must map a runtime error to the same .tpl line as the compiling
        // render did — i.e. SourceMap works from markers re-read off disk,
        // not just from compile-time state.
        $capture = function (): ViewException {
            try {
                view('errors.runtime_simple', ['user' => null])->render();
            } catch (ViewException $e) {
                return $e;
            }

            $this->fail('Expected the render to throw.');
        };

        $cold = $capture();
        $warm = $capture();

        $this->assertStringEndsWith('runtime_simple.tpl', $warm->getFile());
        $this->assertSame($cold->getFile(), $warm->getFile());
        $this->assertSame($cold->getLine(), $warm->getLine());
        $this->assertGreaterThan(1, $warm->getLine(), 'Line 1 is the entry-path fallback — markers were not read.');
    }
}
