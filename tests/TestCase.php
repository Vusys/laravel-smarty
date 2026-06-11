<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Vusys\LaravelSmarty\LaravelSmarty;
use Vusys\LaravelSmarty\Plugins\BlockState;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\SmartyFactory;
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

        // Without an override, PluginCacheStore writes into the *shared
        // vendor testbench skeleton's* bootstrap/cache/, persisting
        // across tests and across whole suite runs. Point it at a
        // per-process temp file instead; tests that need their own path
        // (PluginCacheStoreTest) re-override after this.
        $pluginCacheDir = sys_get_temp_dir().'/laravel-smarty-tests/plugin-cache-'.getmypid();
        @mkdir($pluginCacheDir, 0o755, true);
        PluginCacheStore::$pathOverride = $pluginCacheDir.'/laravel-smarty-plugins.php';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Orchestra rebuilds the app per test, but these statics live
        // for the PHP process's lifetime — flush them all here,
        // unconditionally, so no test depends on the previous test's
        // discipline (and random execution order stays viable).
        LaravelSmarty::flushDiscoveredCache();
        SmartyFactory::flushConfigurators();
        BlockState::reset();
        PluginCacheStore::$pathOverride = null;

        parent::tearDown();
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
