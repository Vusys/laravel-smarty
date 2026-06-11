<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Filesystem\Filesystem;

/**
 * Complement to CachingTest's positive controls: under the default
 * config (caching off) the output-cache directory must stay empty —
 * a regression that silently enables caching would otherwise only show
 * up as stale pages in production.
 */
class CachingDisabledTest extends TestCase
{
    public function test_default_config_writes_no_cache_files(): void
    {
        view('hello', ['name' => 'World'])->render();
        view('hello', ['name' => 'World'])->render();

        $this->assertEmpty((new Filesystem)->allFiles($this->cachePath));
    }
}
