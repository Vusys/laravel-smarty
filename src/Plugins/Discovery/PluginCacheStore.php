<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins\Discovery;

/**
 * On-disk cache for the discovered class-backed plugin map.
 *
 * Production renders shouldn't pay the cost of walking the filesystem
 * on every cold start, so the discovery output is serialised to a PHP
 * file under `bootstrap/cache/` (mirroring Laravel's package manifest).
 * The fingerprint covers two layers: the configured + programmatic
 * namespaces and manually-registered classes, plus the *.php files
 * within those namespaces (path + mtime). The file-layer hash means
 * adding, removing, or modifying a plugin class invalidates the cache
 * automatically — no explicit `smarty:plugins:clear` required.
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

        if (! is_file($path)) {
            return null;
        }

        try {
            $payload = require $path;
        } catch (\Throwable) {
            // A truncated or hand-mangled cache file is a stale cache,
            // not a fatal condition — rescan and rewrite.
            return null;
        }

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
            // The `cacheable` requirement also acts as the format-version
            // check: 0.21-era cache files lack the key, fail here, and
            // get rescanned into the current shape. The type-enum check
            // keeps a schema-drifted entry from reaching fromArray()'s
            // throw — an invalid cache is always "rescan", never a 500.
            if (! is_array($entry)
                || ! isset($entry['type'], $entry['name'], $entry['class'], $entry['cacheable'])
                || ! in_array($entry['type'], ['modifier', 'function', 'block'], true)
                || ! is_string($entry['name'])
                || ! is_string($entry['class'])
                || ! is_bool($entry['cacheable'])
            ) {
                return null;
            }

            $descriptors[] = PluginDescriptor::fromArray([
                'type' => $entry['type'],
                'name' => $entry['name'],
                'class' => $entry['class'],
                'cacheable' => $entry['cacheable'],
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
        $directory = dirname($path);

        if (! is_dir($directory)) {
            return;
        }

        $payload = [
            'fingerprint' => self::fingerprint($namespaces, $manualClasses),
            'plugins' => array_map(static fn (PluginDescriptor $descriptor): array => $descriptor->toArray(), $descriptors),
        ];

        // Write-to-temp + rename (Laravel's manifest pattern): a request
        // that `require`s the file mid-write would otherwise hit a
        // truncated PHP file and 500 with a ParseError. rename() within
        // a directory is atomic, so readers see the old file or the new
        // one — never a partial.
        $temp = tempnam($directory, basename($path));
        if ($temp === false) {
            return;
        }

        file_put_contents($temp, '<?php return '.var_export($payload, true).';'.PHP_EOL);
        @chmod($temp, 0o666 & ~umask());

        if (! @rename($temp, $path)) {
            @unlink($temp);
        }
    }

    public static function clear(): void
    {
        $path = self::path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function path(): string
    {
        return self::$pathOverride ?? app()->bootstrapPath('cache/laravel-smarty-plugins.php');
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

        return hash('sha256', serialize([
            $sortedNamespaces,
            $sortedManual,
            PluginScanner::fingerprintInputs($namespaces, $manualClasses),
        ]));
    }
}
