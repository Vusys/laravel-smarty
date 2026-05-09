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

    public function test_load_returns_null_when_payload_lacks_required_keys(): void
    {
        // A cache file written by an older incompatible version, or
        // hand-edited, that lacks the fingerprint/plugins keys.
        file_put_contents($this->pluginCachePath, '<?php return [\'something\' => \'else\'];');

        $this->assertNull(PluginCacheStore::load(['App\\X'], []));
    }

    public function test_load_returns_null_when_plugins_payload_is_not_an_array(): void
    {
        // Seed a valid cache so the fingerprint computation matches our
        // load() inputs, then corrupt only the `plugins` value. That
        // forces the failure to surface from the plugins-shape branch
        // (line 53), not from the earlier fingerprint or keys checks.
        $namespaces = ['App\\X'];
        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'] = 'not-an-array';
        file_put_contents(
            $this->pluginCachePath,
            '<?php return '.var_export($valid, true).';',
        );

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_load_returns_null_when_an_entry_is_malformed(): void
    {
        // First seed a valid cache for fingerprint computation, then
        // corrupt one entry in place. Triggers the per-entry validation
        // branch in load().
        $namespaces = ['App\\X'];

        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'][] = ['type' => 'modifier']; // missing name + class

        file_put_contents(
            $this->pluginCachePath,
            '<?php return '.var_export($valid, true).';',
        );

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_path_falls_back_to_app_bootstrap_path_when_no_override(): void
    {
        PluginCacheStore::$pathOverride = null;

        // app()->bootstrapPath() resolves under Orchestra's testbench
        // workbench dir, not user-installed Laravel — but the assertion
        // is the same: the path ends in our cache filename.
        $this->assertStringEndsWith('/cache/laravel-smarty-plugins.php', PluginCacheStore::path());
    }
}
