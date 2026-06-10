<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginDescriptor;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\SinceModifier;
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

    public function test_load_is_manual_class_order_insensitive(): void
    {
        PluginCacheStore::store([], ['App\\A', 'App\\B'], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        // The sort() over manualClasses inside fingerprint() is what
        // keeps a reordered manual list from invalidating the cache —
        // dropping it would make store() and load() compute different
        // fingerprints from the same logical set.
        $loaded = PluginCacheStore::load([], ['App\\B', 'App\\A']);

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

    public function test_load_returns_null_when_an_entry_type_is_not_a_string(): void
    {
        // Each per-entry shape check must reject on its own rule —
        // type=int alone is enough to invalidate, even if name/class
        // are valid strings. A weakened guard would fall through to
        // PluginDescriptor::fromArray and either throw or build a bad
        // descriptor instead of returning a clean null.
        $namespaces = ['App\\X'];
        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'] = [['type' => 42, 'name' => 'a', 'class' => 'App\\X']];
        file_put_contents($this->pluginCachePath, '<?php return '.var_export($valid, true).';');

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_load_returns_null_when_an_entry_name_is_not_a_string(): void
    {
        $namespaces = ['App\\X'];
        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'] = [['type' => 'modifier', 'name' => 42, 'class' => 'App\\X']];
        file_put_contents($this->pluginCachePath, '<?php return '.var_export($valid, true).';');

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_load_returns_null_when_an_entry_class_is_not_a_string(): void
    {
        $namespaces = ['App\\X'];
        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'] = [['type' => 'modifier', 'name' => 'a', 'class' => 42]];
        file_put_contents($this->pluginCachePath, '<?php return '.var_export($valid, true).';');

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_load_returns_null_on_unparseable_cache_file(): void
    {
        // A truncated write (pre-atomic-rename era) or hand-mangled file
        // must surface as "stale cache → rescan", never as a ParseError
        // bubbling into a 500.
        file_put_contents($this->pluginCachePath, '<?php return [broken');

        $this->assertNull(PluginCacheStore::load(['App\\X'], []));
    }

    public function test_load_returns_null_when_an_entry_type_is_an_unknown_string(): void
    {
        // 'gadget' is a string, so the is_string guard passes — the
        // type-enum check must reject it here rather than letting
        // PluginDescriptor::fromArray throw out of load().
        $namespaces = ['App\\X'];
        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'] = [['type' => 'gadget', 'name' => 'a', 'class' => 'App\\X', 'cacheable' => true]];
        file_put_contents($this->pluginCachePath, '<?php return '.var_export($valid, true).';');

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_load_returns_null_when_an_entry_lacks_the_cacheable_key(): void
    {
        // 0.21-era cache files predate the cacheable field; requiring it
        // is the format-version check that forces a clean rescan after
        // upgrade instead of silently defaulting every plugin.
        $namespaces = ['App\\X'];
        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $valid = require $this->pluginCachePath;
        $valid['plugins'] = [['type' => 'modifier', 'name' => 'a', 'class' => 'App\\X']];
        file_put_contents($this->pluginCachePath, '<?php return '.var_export($valid, true).';');

        $this->assertNull(PluginCacheStore::load($namespaces, []));
    }

    public function test_round_trip_preserves_cacheable_flag(): void
    {
        $namespaces = ['App\\X'];

        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('function', 'tick', 'App\\Tick', false),
            new PluginDescriptor('modifier', 'since', 'App\\Since'),
        ]);

        $loaded = PluginCacheStore::load($namespaces, []);

        $this->assertNotNull($loaded);
        $this->assertFalse($loaded[0]->cacheable);
        $this->assertTrue($loaded[1]->cacheable);
    }

    public function test_store_leaves_no_temp_files_behind(): void
    {
        // The atomic write goes through tempnam() + rename(); a failed
        // cleanup would litter bootstrap/cache with orphaned temp files.
        foreach (glob($this->pluginCacheDir.'/*') ?: [] as $file) {
            @unlink($file);
        }

        PluginCacheStore::store([], [], [
            new PluginDescriptor('modifier', 'a', 'App\\X'),
        ]);

        $this->assertSame([$this->pluginCachePath], glob($this->pluginCacheDir.'/*'));
    }

    public function test_load_returns_null_when_a_new_php_file_appears_in_a_scanned_namespace(): void
    {
        // Real namespace that resolves to disk so the file-content layer
        // of the fingerprint actually walks something. Drop a probe .php
        // file into the directory after storing — the cache should now
        // be considered stale because a class might have been added.
        $namespaces = ['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'];

        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'since', SinceModifier::class),
        ]);

        $this->assertNotNull(PluginCacheStore::load($namespaces, []));

        $probe = __DIR__.'/../../Fixtures/Plugins/_TmpInvalidationProbe.php';
        file_put_contents($probe, "<?php\n");

        try {
            $this->assertNull(PluginCacheStore::load($namespaces, []));
        } finally {
            @unlink($probe);
        }
    }

    public function test_load_returns_null_when_a_manual_class_file_mtime_changes(): void
    {
        // Manual classes get fingerprinted by their backing file's mtime
        // (resolved through reflection), so editing the class
        // invalidates the cache without an explicit clear step.
        $class = SinceModifier::class;

        PluginCacheStore::store([], [$class], [
            new PluginDescriptor('modifier', 'since', $class),
        ]);

        $this->assertNotNull(PluginCacheStore::load([], [$class]));

        $file = (new \ReflectionClass($class))->getFileName();
        $this->assertIsString($file);
        $original = filemtime($file);

        try {
            touch($file, $original + 2);
            clearstatcache(true, $file);

            $this->assertNull(PluginCacheStore::load([], [$class]));
        } finally {
            touch($file, $original);
            clearstatcache(true, $file);
        }
    }

    public function test_load_hits_cache_when_namespace_files_are_unchanged(): void
    {
        // Round-trip with a real on-disk namespace — covers the path
        // where the file-content fingerprint actually iterates entries
        // (the other round-trip test uses a fake namespace that
        // resolves to no directories).
        $namespaces = ['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'];

        PluginCacheStore::store($namespaces, [], [
            new PluginDescriptor('modifier', 'since', SinceModifier::class),
        ]);

        $loaded = PluginCacheStore::load($namespaces, []);

        $this->assertNotNull($loaded);
        $this->assertCount(1, $loaded);
        $this->assertSame('since', $loaded[0]->name);
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
