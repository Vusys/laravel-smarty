<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vusys\LaravelSmarty\SmartyServiceProvider;

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

    protected function stubUser(int $id = 1): Authenticatable
    {
        return new class($id) implements Authenticatable
        {
            public function __construct(private readonly int $id) {}

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return $this->id;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($v): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }
}
