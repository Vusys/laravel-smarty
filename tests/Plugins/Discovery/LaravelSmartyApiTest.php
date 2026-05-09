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
