<?php

namespace Vusys\LaravelSmarty\Tests\Console;

use Illuminate\Filesystem\Filesystem;
use Vusys\LaravelSmarty\Tests\TestCase;

class ClearCacheCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smarty.caching', true);
        $app['config']->set('smarty.force_compile', false);
    }

    public function test_clears_cached_output(): void
    {
        view('hello', ['name' => 'World'])->render();

        $files = new Filesystem;
        $this->assertNotEmpty(
            $files->allFiles($this->cachePath),
            'Smarty should have written a cache file when caching is enabled.',
        );

        $this->artisan('smarty:clear-cache')->assertSuccessful();

        $this->assertEmpty($files->allFiles($this->cachePath));
    }

    public function test_file_option_targets_a_single_template(): void
    {
        view('hello', ['name' => 'World'])->render();
        view('loop', ['items' => ['one']])->render();

        $files = new Filesystem;
        $this->assertCount(2, $files->allFiles($this->cachePath));

        $this->artisan('smarty:clear-cache', ['--file' => 'hello.tpl'])->assertSuccessful();

        // --file= must narrow the operation to one template's cache. Without
        // the option pass-through this falls back to clearAllCache() and
        // both cached outputs vanish.
        $this->assertCount(1, $files->allFiles($this->cachePath));
    }

    public function test_empty_file_option_still_clears_everything(): void
    {
        view('hello', ['name' => 'World'])->render();
        view('loop', ['items' => ['one']])->render();

        $files = new Filesystem;
        $this->assertCount(2, $files->allFiles($this->cachePath));

        // Symfony parses `--file=` as an empty string. The guard
        // `is_string($file) && $file !== ''` must fall through to
        // clearAllCache(); flipping it to `||` would route an empty string
        // into clearCache('') and clear nothing.
        $this->artisan('smarty:clear-cache', ['--file' => ''])->assertSuccessful();

        $this->assertEmpty($files->allFiles($this->cachePath));
    }

    public function test_expire_option_preserves_fresh_cache_entries(): void
    {
        view('hello', ['name' => 'World'])->render();

        $files = new Filesystem;
        $this->assertCount(1, $files->allFiles($this->cachePath));

        // Cache file was just written, so a 9999s expiry must spare it.
        // Without the ternary picking the (int) branch, $expire would be
        // null and the file would be cleared regardless.
        $this->artisan('smarty:clear-cache', ['--expire' => '9999'])
            ->expectsOutputToContain('Cleared 0 Smarty cache file(s).')
            ->assertSuccessful();

        $this->assertCount(1, $files->allFiles($this->cachePath));
    }

    public function test_expire_zero_clears_and_reports_count(): void
    {
        view('hello', ['name' => 'World'])->render();

        // expire=0 means "anything older than 0s" — clears the file and
        // the success line interpolates the returned int. Without
        // $this->info(...) the artisan output assertion below fails.
        $this->artisan('smarty:clear-cache', ['--expire' => '0'])
            ->expectsOutputToContain('Cleared 1 Smarty cache file(s).')
            ->assertSuccessful();

        $this->assertEmpty((new Filesystem)->allFiles($this->cachePath));
    }
}
