<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginDescriptor;
use Vusys\LaravelSmarty\Tests\TestCase;

class PluginCacheStoreTest extends TestCase
{
    private string $pluginCacheDir;

    private string $pluginCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginCacheDir = sys_get_temp_dir().'/laravel-smarty-tests/plugin-cache';
        @mkdir($this->pluginCacheDir, 0o755, true);

        $this->pluginCachePath = $this->pluginCacheDir.'/laravel-smarty-plugins.php';
        @unlink($this->pluginCachePath);

        PluginCacheStore::$pathOverride = $this->pluginCachePath;
    }

    protected function tearDown(): void
    {
        PluginCacheStore::$pathOverride = null;

        @unlink($this->pluginCachePath);

        parent::tearDown();
    }

    public function test_store_and_load_round_trip(): void
    {
        $namespaces = ['App\\Smarty\\Plugins'];
        $manualClasses = [];
        $descriptors = [
            new PluginDescriptor('modifier', 'since', 'App\\Smarty\\Plugins\\SinceModifier'),
            new PluginDescriptor('function', 'csp_nonce', 'App\\Smarty\\Plugins\\CspNonceFunction'),
        ];

        PluginCacheStore::store($namespaces, $manualClasses, $descriptors);

        $loaded = PluginCacheStore::load($namespaces, $manualClasses);

        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded);
        $this->assertSame('modifier', $loaded[0]->type);
        $this->assertSame('since', $loaded[0]->name);
        $this->assertSame('App\\Smarty\\Plugins\\SinceModifier', $loaded[0]->class);
    }

    public function test_load_returns_null_when_namespaces_change(): void
    {
        PluginCacheStore::store(['App\\Smarty\\Plugins'], [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $this->assertNull(PluginCacheStore::load(['App\\Other\\Plugins'], []));
    }

    public function test_load_returns_null_when_manual_classes_change(): void
    {
        PluginCacheStore::store([], ['App\\X'], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $this->assertNull(PluginCacheStore::load([], ['App\\Y']));
    }

    public function test_load_is_namespace_order_insensitive(): void
    {
        PluginCacheStore::store(['App\\A', 'App\\B'], [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        // Same set, different ordering — fingerprint sorts inputs so a
        // re-ordered config doesn't invalidate the cache.
        $loaded = PluginCacheStore::load(['App\\B', 'App\\A'], []);

        $this->assertNotNull($loaded);
    }

    public function test_load_returns_null_when_no_cache_file(): void
    {
        $this->assertNull(PluginCacheStore::load(['App\\Smarty\\Plugins'], []));
    }

    public function test_clear_removes_cache_file(): void
    {
        PluginCacheStore::store([], [], [new PluginDescriptor('modifier', 'a', 'App\\X')]);

        $this->assertFileExists($this->pluginCachePath);

        PluginCacheStore::clear();

        $this->assertFileDoesNotExist($this->pluginCachePath);
    }

    public function test_store_silently_no_ops_when_directory_missing(): void
    {
        PluginCacheStore::$pathOverride = sys_get_temp_dir().'/laravel-smarty-tests/no-such-dir/cache.php';

        // No throw, no file created. Lets the next render rescan
        // instead of failing because bootstrap/cache wasn't published.
        PluginCacheStore::store([], [], [new PluginDescriptor('modifier', 'a', 'App\\X')]);

        $this->assertFileDoesNotExist(PluginCacheStore::$pathOverride);
    }
}
