<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty;

use Smarty\Smarty;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginCacheStore;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginDescriptor;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginRegistrar;
use Vusys\LaravelSmarty\Plugins\Discovery\PluginScanner;

/**
 * Public entry point for class-backed plugin discovery.
 *
 * Three registration channels feed into the same dispatch logic:
 *
 *  1. `smarty.plugin_namespaces` config — namespaces the host app
 *     wants scanned. Default `['App\\Smarty\\Plugins']`. Empty array
 *     disables namespace discovery without disabling the manual APIs.
 *
 *  2. `LaravelSmarty::discoverPluginsIn(...)` — programmatic namespace
 *     registration, intended for third-party packages that ship their
 *     own plugins. Idempotent: calling twice with the same namespace
 *     is a no-op.
 *
 *  3. `LaravelSmarty::registerPluginClass($class)` — manual single-class
 *     registration. Useful for plugins that live outside any scanned
 *     namespace (one-offs, test fixtures). Throws if the class has
 *     neither the `#[SmartyPlugin]` attribute nor a recognised suffix.
 *
 * The `function.<name>.php` / `plugins_paths` convention keeps working
 * unchanged — the class-backed paths are *additional* registration
 * channels that run alongside it, not a replacement.
 */
class LaravelSmarty
{
    /** @var array<int, string> */
    protected static array $extraNamespaces = [];

    /** @var array<int, class-string> */
    protected static array $manualClasses = [];

    /** @var array<int, PluginDescriptor>|null */
    protected static ?array $resolved = null;

    /**
     * Add one or more PSR-4 namespaces to the discovery scan. Intended
     * for third-party package service providers — host apps should
     * prefer the `smarty.plugin_namespaces` config key.
     */
    public static function discoverPluginsIn(string ...$namespaces): void
    {
        foreach ($namespaces as $namespace) {
            $namespace = trim($namespace, '\\');
            if ($namespace === '') {
                continue;
            }
            if (! in_array($namespace, self::$extraNamespaces, true)) {
                self::$extraNamespaces[] = $namespace;
            }
        }

        self::$resolved = null;
    }

    /**
     * Register a single class regardless of namespace. The class must
     * either carry `#[SmartyPlugin]` or end in Modifier/Function/Block;
     * anything else throws.
     *
     * @param  class-string  $class
     */
    public static function registerPluginClass(string $class): void
    {
        /** @var class-string $class */
        $class = ltrim($class, '\\');

        if (! in_array($class, self::$manualClasses, true)) {
            self::$manualClasses[] = $class;
        }

        self::$resolved = null;
    }

    /**
     * Install every discovered plugin onto a Smarty instance. Called by
     * `SmartyFactory` after the built-in plugins, so user plugins win
     * if they happen to share a name with a `function.<name>.php`
     * file-based plugin (registered plugins shadow plugin-path lookups
     * inside Smarty itself).
     */
    public static function registerOn(Smarty $smarty): void
    {
        PluginRegistrar::register($smarty, self::resolveDescriptors());
    }

    /**
     * Force a fresh scan and rewrite the on-disk cache. Used by the
     * `smarty:plugins:cache` console command.
     *
     * @return array<int, PluginDescriptor>
     */
    public static function rebuildDiscoveryCache(): array
    {
        self::$resolved = null;
        PluginCacheStore::clear();

        return self::resolveDescriptors();
    }

    /**
     * Drop in-memory state and the on-disk cache. Used by tests and by
     * the `smarty:plugins:clear` console command.
     */
    public static function flushDiscoveredCache(): void
    {
        self::$extraNamespaces = [];
        self::$manualClasses = [];
        self::$resolved = null;

        PluginCacheStore::clear();
    }

    /**
     * @return array<int, string>
     */
    public static function namespaces(): array
    {
        $configValue = function_exists('config') ? config('smarty.plugin_namespaces', []) : [];
        $config = is_array($configValue) ? array_values(array_filter($configValue, is_string(...))) : [];

        return array_values(array_unique([
            ...$config,
            ...self::$extraNamespaces,
        ]));
    }

    /**
     * @return array<int, class-string>
     */
    public static function manualClasses(): array
    {
        return array_values(array_unique(self::$manualClasses));
    }

    /**
     * @return array<int, PluginDescriptor>
     */
    protected static function resolveDescriptors(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $namespaces = self::namespaces();
        $manualClasses = self::manualClasses();

        $cached = PluginCacheStore::load($namespaces, $manualClasses);
        if ($cached !== null) {
            return self::$resolved = $cached;
        }

        $descriptors = PluginScanner::scan($namespaces, $manualClasses);
        PluginCacheStore::store($namespaces, $manualClasses, $descriptors);

        return self::$resolved = $descriptors;
    }
}
