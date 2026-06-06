<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use Vusys\LaravelSmarty\LaravelSmarty;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\Tests\TestCase;

class LaravelSmartyApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        LaravelSmarty::flushDiscoveredCache();
        PluginCacheStore::clear();
    }

    protected function tearDown(): void
    {
        LaravelSmarty::flushDiscoveredCache();
        PluginCacheStore::clear();

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smarty.plugin_namespaces', [
            'Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins',
        ]);
    }

    public function test_discover_plugins_in_silently_skips_empty_namespace_input(): void
    {
        // Empty strings and bare backslashes trim to '' and should not
        // be treated as a registered namespace; calling them in
        // shouldn't pollute the scanned set or alter resolution.
        LaravelSmarty::discoverPluginsIn('', '\\', '\\\\');

        $namespaces = LaravelSmarty::namespaces();

        // Only the config-set namespace should be present — none of
        // the empties got past the trim guard.
        $this->assertSame(['Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins'], $namespaces);
    }

    public function test_discover_plugins_in_adds_the_namespace_to_the_scanned_set(): void
    {
        LaravelSmarty::discoverPluginsIn('Acme\\Smarty\\Plugins');

        $this->assertContains('Acme\\Smarty\\Plugins', LaravelSmarty::namespaces());
    }

    public function test_discover_plugins_in_continues_past_empty_entries_in_the_middle(): void
    {
        // The loop guard `continue`s past empties; a `break` would drop
        // every namespace listed after the empty one.
        LaravelSmarty::discoverPluginsIn('Acme\\First', '', 'Acme\\Second');

        $namespaces = LaravelSmarty::namespaces();

        $this->assertContains('Acme\\First', $namespaces);
        $this->assertContains('Acme\\Second', $namespaces);
    }

    public function test_discover_plugins_in_deduplicates_repeated_namespaces(): void
    {
        // `namespaces()` runs `array_unique` at the surface, so the
        // dedup guard inside `discoverPluginsIn` is only observable on
        // the internal `$extraNamespaces` storage.
        LaravelSmarty::discoverPluginsIn('Acme\\Smarty\\Plugins');
        LaravelSmarty::discoverPluginsIn('Acme\\Smarty\\Plugins');

        $reflection = new \ReflectionClass(LaravelSmarty::class);
        $extras = $reflection->getProperty('extraNamespaces')->getValue();

        $this->assertSame(['Acme\\Smarty\\Plugins'], $extras);
    }

    public function test_rebuild_discovery_cache_writes_a_fresh_cache(): void
    {
        $cachePath = sys_get_temp_dir().'/laravel-smarty-tests/rebuild-cache/laravel-smarty-plugins.php';
        @mkdir(dirname($cachePath), 0o755, true);
        @unlink($cachePath);

        PluginCacheStore::$pathOverride = $cachePath;

        try {
            $descriptors = LaravelSmarty::rebuildDiscoveryCache();

            // Returned descriptors include the fixture plugins
            $names = array_map(static fn ($d) => $d->name, $descriptors);
            $this->assertContains('since', $names);

            // …and the cache file was written through to disk
            $this->assertFileExists($cachePath);
        } finally {
            PluginCacheStore::$pathOverride = null;
            @unlink($cachePath);
        }
    }

    public function test_rebuild_discovery_cache_clears_a_stale_cache_first(): void
    {
        $cachePath = sys_get_temp_dir().'/laravel-smarty-tests/stale-cache/laravel-smarty-plugins.php';
        @mkdir(dirname($cachePath), 0o755, true);

        // Seed a deliberately-bogus cache file at the expected path so
        // we can prove rebuildDiscoveryCache cleared it before writing.
        file_put_contents($cachePath, '<?php return ["fingerprint" => "stale", "plugins" => []];');
        $stalePayload = require $cachePath;
        $this->assertSame('stale', $stalePayload['fingerprint']);

        PluginCacheStore::$pathOverride = $cachePath;

        try {
            LaravelSmarty::rebuildDiscoveryCache();

            $rebuilt = require $cachePath;
            $this->assertNotSame('stale', $rebuilt['fingerprint']);
            $this->assertNotEmpty($rebuilt['plugins']);
        } finally {
            PluginCacheStore::$pathOverride = null;
            @unlink($cachePath);
        }
    }

    public function test_rebuild_discovery_cache_forces_a_rescan_even_when_a_matching_cache_exists(): void
    {
        $cachePath = sys_get_temp_dir().'/laravel-smarty-tests/rebuild-rescan-cache/laravel-smarty-plugins.php';
        @mkdir(dirname($cachePath), 0o755, true);
        @unlink($cachePath);

        PluginCacheStore::$pathOverride = $cachePath;

        try {
            // First, prime a valid cache file (right fingerprint over the
            // current inputs) so we can then swap the plugin list inside it
            // without breaking the load-side fingerprint check.
            LaravelSmarty::rebuildDiscoveryCache();
            /** @var array{fingerprint: string, plugins: array<int, array<string, string>>} $payload */
            $payload = require $cachePath;

            // Poison the cached plugins. If `rebuildDiscoveryCache()` skips
            // clearing the file (mutant), the load path returns this
            // synthetic list and `since` never makes it back into the
            // result — the rescan is silently bypassed.
            $payload['plugins'] = [[
                'type' => 'modifier',
                'name' => 'poison_should_be_evicted',
                'class' => 'Synthetic\\NoSuchClass',
            ]];
            file_put_contents($cachePath, '<?php return '.var_export($payload, true).';'.PHP_EOL);

            // Drop the in-memory memo so the file is the only source of
            // descriptors going into the next call.
            $reflection = new \ReflectionClass(LaravelSmarty::class);
            $reflection->getProperty('resolved')->setValue(null, null);

            $descriptors = LaravelSmarty::rebuildDiscoveryCache();
            $names = array_map(static fn ($d) => $d->name, $descriptors);

            $this->assertNotContains('poison_should_be_evicted', $names);
            $this->assertContains('since', $names);
        } finally {
            PluginCacheStore::$pathOverride = null;
            @unlink($cachePath);
        }
    }

    public function test_flush_discovered_cache_removes_the_on_disk_cache_file(): void
    {
        $cachePath = sys_get_temp_dir().'/laravel-smarty-tests/flush-cache/laravel-smarty-plugins.php';
        @mkdir(dirname($cachePath), 0o755, true);
        @unlink($cachePath);

        PluginCacheStore::$pathOverride = $cachePath;

        try {
            LaravelSmarty::rebuildDiscoveryCache();
            $this->assertFileExists($cachePath);

            // flushDiscoveredCache's contract is "drop in-memory state and
            // the on-disk cache" — and because it never calls
            // resolveDescriptors afterward, store() never runs to overwrite
            // the file. The clear() call is the only thing that removes it.
            LaravelSmarty::flushDiscoveredCache();

            $this->assertFileDoesNotExist($cachePath);
        } finally {
            PluginCacheStore::$pathOverride = null;
            @unlink($cachePath);
        }
    }

    public function test_resolve_descriptors_short_circuits_on_a_cache_hit_instead_of_rescanning(): void
    {
        $cachePath = sys_get_temp_dir().'/laravel-smarty-tests/cache-hit-short-circuit/laravel-smarty-plugins.php';
        @mkdir(dirname($cachePath), 0o755, true);
        @unlink($cachePath);

        PluginCacheStore::$pathOverride = $cachePath;

        try {
            // Seed a valid cache file, then poison its plugin list. Cache
            // load only checks the fingerprint and entry shape, so the
            // synthetic descriptor passes validation.
            LaravelSmarty::rebuildDiscoveryCache();
            /** @var array{fingerprint: string, plugins: array<int, array<string, string>>} $payload */
            $payload = require $cachePath;
            $payload['plugins'] = [[
                'type' => 'modifier',
                'name' => 'cache_hit_marker',
                'class' => 'Synthetic\\NoSuchClass',
            ]];
            file_put_contents($cachePath, '<?php return '.var_export($payload, true).';'.PHP_EOL);

            // Reach into `resolveDescriptors()` directly (and reset the
            // memo) — if the cache-hit early-return is removed, we fall
            // through to PluginScanner::scan, which would replace the
            // synthetic entry with the real fixture set.
            $reflection = new \ReflectionClass(LaravelSmarty::class);
            $reflection->getProperty('resolved')->setValue(null, null);
            $resolveMethod = $reflection->getMethod('resolveDescriptors');
            $descriptors = $resolveMethod->invoke(null);

            $names = array_map(static fn ($d) => $d->name, $descriptors);
            $this->assertSame(['cache_hit_marker'], $names);
        } finally {
            PluginCacheStore::$pathOverride = null;
            @unlink($cachePath);
        }
    }

    public function test_resolve_descriptors_reads_from_a_pre_existing_cache_file(): void
    {
        $cachePath = sys_get_temp_dir().'/laravel-smarty-tests/preexisting-cache/laravel-smarty-plugins.php';
        @mkdir(dirname($cachePath), 0o755, true);
        @unlink($cachePath);

        PluginCacheStore::$pathOverride = $cachePath;

        try {
            // First, write the cache through the public API so we have a
            // real, valid cache file on disk with the right fingerprint.
            LaravelSmarty::rebuildDiscoveryCache();
            $this->assertFileExists($cachePath);

            // Drop only in-memory memoisation; the file stays. The next
            // resolveDescriptors() call must take the cached-file branch.
            $reflection = new \ReflectionClass(LaravelSmarty::class);
            $resolvedProp = $reflection->getProperty('resolved');
            $resolvedProp->setValue(null, null);

            // Invoke resolveDescriptors directly so we don't rely on the
            // view-engine resolver (which caches the Smarty instance after
            // the first render and would short-circuit this path).
            $resolveMethod = $reflection->getMethod('resolveDescriptors');
            $descriptors = $resolveMethod->invoke(null);

            $names = array_map(static fn ($d) => $d->name, $descriptors);
            $this->assertContains('since', $names);

            // Memoisation cache is now populated from the file load.
            $this->assertSame($descriptors, $resolvedProp->getValue());
        } finally {
            PluginCacheStore::$pathOverride = null;
            @unlink($cachePath);
        }
    }
}
