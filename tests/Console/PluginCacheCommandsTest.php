<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Console;

use Vusys\LaravelSmarty\LaravelSmarty;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\Tests\TestCase;

class PluginCacheCommandsTest extends TestCase
{
    private string $pluginCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        LaravelSmarty::flushDiscoveredCache();

        $this->pluginCachePath = sys_get_temp_dir().'/laravel-smarty-tests/console-cache/laravel-smarty-plugins.php';
        @mkdir(dirname($this->pluginCachePath), 0o755, true);
        @unlink($this->pluginCachePath);

        PluginCacheStore::$pathOverride = $this->pluginCachePath;
    }

    protected function tearDown(): void
    {
        PluginCacheStore::$pathOverride = null;
        @unlink($this->pluginCachePath);

        LaravelSmarty::flushDiscoveredCache();

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smarty.plugin_namespaces', [
            'Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins',
        ]);
    }

    public function test_smarty_plugins_cache_writes_the_discovery_cache(): void
    {
        $this->assertFileDoesNotExist($this->pluginCachePath);

        $this->artisan('smarty:plugins:cache')
            ->expectsOutputToContain('class-backed Smarty plugin')
            ->assertSuccessful();

        $this->assertFileExists($this->pluginCachePath);

        // The cached file is a valid PHP-returned array with both the
        // fingerprint and the discovered plugins.
        $payload = require $this->pluginCachePath;
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('fingerprint', $payload);
        $this->assertArrayHasKey('plugins', $payload);
        $this->assertNotEmpty($payload['plugins']);
    }

    public function test_smarty_plugins_clear_removes_the_cache_file(): void
    {
        // Seed the cache so there's something to clear
        $this->artisan('smarty:plugins:cache')->assertSuccessful();
        $this->assertFileExists($this->pluginCachePath);

        $this->artisan('smarty:plugins:clear')
            ->expectsOutputToContain('cache cleared')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->pluginCachePath);
    }

    public function test_smarty_plugins_clear_is_a_no_op_when_cache_file_is_absent(): void
    {
        $this->assertFileDoesNotExist($this->pluginCachePath);

        // No throw, exit 0 — running clear before any cache exists is
        // the deploy-pipeline shape (clear → maybe cache) and shouldn't
        // be noisy.
        $this->artisan('smarty:plugins:clear')->assertSuccessful();

        $this->assertFileDoesNotExist($this->pluginCachePath);
    }
}
