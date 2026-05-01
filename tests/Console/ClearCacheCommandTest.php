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
}
