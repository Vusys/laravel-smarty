<?php

namespace Vusys\LaravelSmarty\Tests;

use Vusys\LaravelSmarty\SmartyServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected string $viewsPath;

    protected string $compilePath;

    protected string $cachePath;

    protected function setUp(): void
    {
        $this->viewsPath = __DIR__.'/Fixtures/views';
        $this->compilePath = sys_get_temp_dir().'/laravel-smarty-tests/compile';
        $this->cachePath = sys_get_temp_dir().'/laravel-smarty-tests/cache';

        (new Filesystem)->deleteDirectory($this->compilePath);
        (new Filesystem)->deleteDirectory($this->cachePath);

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [SmartyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [$this->viewsPath]);
        $app['config']->set('smarty.compile_path', $this->compilePath);
        $app['config']->set('smarty.cache_path', $this->cachePath);
        $app['config']->set('smarty.force_compile', true);
    }
}
