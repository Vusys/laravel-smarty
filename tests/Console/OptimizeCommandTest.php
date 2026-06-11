<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Console;

use Illuminate\Filesystem\Filesystem;
use Vusys\LaravelSmarty\Tests\TestCase;

class OptimizeCommandTest extends TestCase
{
    /**
     * The shared fixture tree deliberately contains broken templates
     * (errors/compile_error.tpl etc.) for the source-map tests — and
     * smarty:optimize now fails on compile errors. These tests run
     * against their own temp view path seeded with valid templates
     * only; the failure test adds a broken one explicitly.
     */
    private string $optimizeViewsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->optimizeViewsPath = sys_get_temp_dir().'/laravel-smarty-tests/optimize-views';
        $files = new Filesystem;
        $files->deleteDirectory($this->optimizeViewsPath);
        $files->ensureDirectoryExists($this->optimizeViewsPath);
        $files->copy($this->viewsPath.'/hello.tpl', $this->optimizeViewsPath.'/hello.tpl');
        $files->copy($this->viewsPath.'/loop.tpl', $this->optimizeViewsPath.'/loop.tpl');

        // Controlled source mtimes (fresh CI checkouts stamp fixtures
        // "now") and force_compile off, so the no-flag run in
        // test_force_flag_recompiles genuinely exercises the up-to-date
        // path instead of being forced through by the test config.
        touch($this->optimizeViewsPath.'/hello.tpl', time() - 600);
        touch($this->optimizeViewsPath.'/loop.tpl', time() - 600);
        config()->set('view.paths', [$this->optimizeViewsPath]);
        config()->set('smarty.force_compile', false);
    }

    public function test_compiles_all_templates(): void
    {
        $this->artisan('smarty:optimize')
            ->expectsOutputToContain('Compiled 2 template(s).')
            ->assertSuccessful();

        $this->assertCount(2, (new Filesystem)->allFiles($this->compilePath));
    }

    public function test_force_flag_recompiles(): void
    {
        $this->artisan('smarty:optimize')->assertSuccessful();

        // Backdate the compiled output; --force must rewrite it (mtime
        // advances), a plain re-run must not. Exit codes alone pass even
        // when --force is silently dropped.
        $files = (new Filesystem)->allFiles($this->compilePath);
        $this->assertNotEmpty($files);
        $backdated = time() - 300;
        foreach ($files as $file) {
            touch($file->getPathname(), $backdated);
        }
        clearstatcache();

        $this->artisan('smarty:optimize')->assertSuccessful();
        clearstatcache();
        foreach ($files as $file) {
            $this->assertSame($backdated, filemtime($file->getPathname()), 'Without --force, up-to-date output must be left alone.');
        }

        $this->artisan('smarty:optimize', ['--force' => true])->assertSuccessful();
        clearstatcache();
        foreach ($files as $file) {
            $this->assertGreaterThan($backdated, filemtime($file->getPathname()), '--force must rewrite compiled output.');
        }
    }

    public function test_compile_error_fails_the_command(): void
    {
        // Vendor compileAll() swallows the exception and echoes a marker
        // into the trail; the command must surface that as a FAILURE exit
        // so deploy pipelines can gate on pre-compilation.
        file_put_contents($this->optimizeViewsPath.'/broken.tpl', "{if \$x}\nno closing tag\n");

        $this->artisan('smarty:optimize')
            ->expectsOutputToContain('1 template(s) failed to compile.')
            ->assertFailed();
    }

    public function test_extension_option_is_honoured(): void
    {
        $this->artisan('smarty:optimize', ['--extension' => 'tpl'])->assertSuccessful();

        $this->assertNotEmpty((new Filesystem)->allFiles($this->compilePath));
    }

    public function test_extension_option_overrides_config_default(): void
    {
        // Config default points at an extension that doesn't match the
        // fixtures; --extension=tpl must still win. A Coalesce swap on
        // the ?? would prefer config (zero matches) over the option.
        config()->set('smarty.extension', 'zzz');

        $this->artisan('smarty:optimize', ['--extension' => 'tpl'])
            ->expectsOutputToContain('Compiled')
            ->assertSuccessful();

        $this->assertNotEmpty((new Filesystem)->allFiles($this->compilePath));
    }

    public function test_unknown_extension_compiles_nothing(): void
    {
        // No fixtures end in `.absent`; the original ternary keeps the
        // provided extension as-is, so 0 files compile. A Ternary swap
        // would fall back to 'tpl' and compile the real fixtures —
        // catching the swap.
        $this->artisan('smarty:optimize', ['--extension' => 'absent'])
            ->expectsOutputToContain('Compiled 0 template(s).')
            ->assertSuccessful();

        $this->assertEmpty((new Filesystem)->allFiles($this->compilePath));
    }

    public function test_progress_trail_is_streamed_to_console(): void
    {
        // Smarty echoes "<dir>---<file> compiled in <s> seconds" per
        // file. Asserting that text confirms both the `$trail !== ''`
        // guard and the `$this->line(...)` call survive.
        $this->artisan('smarty:optimize')
            ->expectsOutputToContain('compiled in')
            ->assertSuccessful();
    }
}
