<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins\Discovery;

use Vusys\LaravelSmarty\Exceptions\PluginRegistrationException;
use Vusys\LaravelSmarty\LaravelSmarty;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins\CollidingSinceModifier;
use Vusys\LaravelSmarty\Tests\Fixtures\ExternalPlugins\StandaloneFunction;
use Vusys\LaravelSmarty\Tests\Fixtures\Plugins\PlainHelper;
use Vusys\LaravelSmarty\Tests\TestCase;

class PluginAutoDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Static state on LaravelSmarty (extra namespaces, manual classes,
        // memoised descriptors) and the on-disk cache must be cleared
        // between tests — Orchestra rebuilds the app per test, but those
        // statics live in the PHP process for the suite's lifetime.
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

    public function test_modifiers_functions_and_blocks_are_all_auto_discovered(): void
    {
        $output = view('discovery')->render();

        $this->assertStringContainsString('since=[raw]', $output);
        $this->assertStringContainsString('loud=HELLO!', $output);
        $this->assertStringContainsString('wrap=<span>body</span>', $output);
        $this->assertStringContainsString('shouty=HI', $output);
        $this->assertStringContainsString('shrunk=yo', $output);
        $this->assertStringContainsString('multi=mw(x)', $output);
        $this->assertStringContainsString('nested=nest:x', $output);
    }

    public function test_register_plugin_class_adds_classes_outside_scanned_namespaces(): void
    {
        LaravelSmarty::registerPluginClass(StandaloneFunction::class);

        $output = view('standalone')->render();

        $this->assertStringContainsString('out=standalone:hi', $output);
    }

    public function test_unrecognised_manually_registered_class_throws_at_render(): void
    {
        // PlainHelper has no suffix and no attribute; manual registration
        // is a programmer error and we'd rather fail loud at boot than
        // silently drop the registration.
        LaravelSmarty::registerPluginClass(PlainHelper::class);

        $this->expectException(PluginRegistrationException::class);
        $this->expectExceptionMessage('cannot be registered as a Smarty plugin');

        view('discovery')->render();
    }

    public function test_collision_between_scanned_and_manual_class_throws(): void
    {
        // SinceModifier in the scanned namespace registers as
        // (modifier, since); CollidingSinceModifier carries
        // public string $name = 'since' so manual registration drops
        // a second (modifier, since) — should throw, not silently win.
        LaravelSmarty::registerPluginClass(CollidingSinceModifier::class);

        $this->expectException(PluginRegistrationException::class);
        $this->expectExceptionMessage("name 'since'");

        view('discovery')->render();
    }

    public function test_discover_plugins_in_is_idempotent(): void
    {
        $namespace = 'Vusys\\LaravelSmarty\\Tests\\Fixtures\\Plugins';

        LaravelSmarty::discoverPluginsIn($namespace);
        LaravelSmarty::discoverPluginsIn($namespace);
        LaravelSmarty::discoverPluginsIn($namespace);

        $namespaces = LaravelSmarty::namespaces();

        // The same namespace from the config-set + three programmatic
        // registrations must collapse to a single entry.
        $matches = array_filter($namespaces, static fn (string $ns): bool => $ns === $namespace);
        $this->assertCount(1, $matches);
    }

    public function test_empty_plugin_namespaces_disables_namespace_discovery(): void
    {
        config()->set('smarty.plugin_namespaces', []);
        LaravelSmarty::flushDiscoveredCache();

        // Even though SinceModifier is on disk under a recognised
        // suffix, an empty config + no programmatic namespace + no
        // manual class means nothing is registered — and a template
        // that uses {$x|since} fails at compile because Smarty can't
        // resolve the modifier.
        $this->expectException(\Throwable::class);

        view('discovery')->render();
    }
}
