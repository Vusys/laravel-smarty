<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins\Discovery;

/**
 * On-disk cache for the discovered class-backed plugin map.
 *
 * Production renders shouldn't pay the cost of walking the filesystem
 * on every cold start, so the discovery output is serialised to a PHP
 * file under `bootstrap/cache/` (mirroring Laravel's package manifest).
 * The file embeds a fingerprint of the inputs (configured + programmatic
 * namespaces, plus manually-registered classes), so adding a new
 * namespace or moving a class invalidates the cache without an explicit
 * `smarty:plugins:clear` step.
 */
class PluginCacheStore
{
    /**
     * Override the cache file path. Used by tests to isolate cache state
     * between test cases. Set to `null` to restore the default.
     */
    public static ?string $pathOverride = null;

    /**
     * Return the cached descriptor list when one exists and matches the
     * given input fingerprint, or `null` when a fresh scan is needed.
     *
     * @param  array<int, string>  $namespaces
     * @param  array<int, class-string>  $manualClasses
     * @return array<int, PluginDescriptor>|null
     */
    public static function load(array $namespaces, array $manualClasses): ?array
    {
        $path = self::path();

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $payload = require $path;

        if (! is_array($payload) || ! isset($payload['fingerprint'], $payload['plugins'])) {
            return null;
        }

        if ($payload['fingerprint'] !== self::fingerprint($namespaces, $manualClasses)) {
            return null;
        }

        if (! is_array($payload['plugins'])) {
            return null;
        }

        $descriptors = [];
        foreach ($payload['plugins'] as $entry) {
            if (! is_array($entry)
                || ! isset($entry['type'], $entry['name'], $entry['class'])
                || ! is_string($entry['type'])
                || ! is_string($entry['name'])
                || ! is_string($entry['class'])
            ) {
                return null;
            }

            $descriptors[] = PluginDescriptor::fromArray([
                'type' => $entry['type'],
                'name' => $entry['name'],
                'class' => $entry['class'],
            ]);
        }

        return $descriptors;
    }

    /**
     * Persist the descriptor list under the given input fingerprint.
     * Silently no-ops when there's no `bootstrap/cache/` directory to
     * write into — the next render will rescan and try again.
     *
     * @param  array<int, string>  $namespaces
     * @param  array<int, class-string>  $manualClasses
     * @param  array<int, PluginDescriptor>  $descriptors
     */
    public static function store(array $namespaces, array $manualClasses, array $descriptors): void
    {
        $path = self::path();
        if ($path === null) {
            return;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            return;
        }

        $payload = [
            'fingerprint' => self::fingerprint($namespaces, $manualClasses),
            'plugins' => array_map(static fn (PluginDescriptor $descriptor): array => $descriptor->toArray(), $descriptors),
        ];

        file_put_contents($path, '<?php return '.var_export($payload, true).';'.PHP_EOL);
    }

    public static function clear(): void
    {
        $path = self::path();
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public static function path(): ?string
    {
        if (self::$pathOverride !== null) {
            return self::$pathOverride;
        }

        // Wrapped in function_exists + try/catch so the package can be
        // referenced from non-Laravel bootstraps (e.g. dump tooling)
        // without a hard failure — the cache simply isn't used there.
        if (! function_exists('app')) {
            return null;
        }

        try {
            return app()->bootstrapPath('cache/laravel-smarty-plugins.php');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $namespaces
     * @param  array<int, class-string>  $manualClasses
     */
    private static function fingerprint(array $namespaces, array $manualClasses): string
    {
        $sortedNamespaces = $namespaces;
        sort($sortedNamespaces);

        $sortedManual = $manualClasses;
        sort($sortedManual);

        return hash('sha256', serialize([$sortedNamespaces, $sortedManual]));
    }
}
