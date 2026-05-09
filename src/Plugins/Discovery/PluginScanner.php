<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins\Discovery;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Vusys\LaravelSmarty\Attributes\SmartyPlugin;
use Vusys\LaravelSmarty\Exceptions\PluginRegistrationException;

/**
 * Walks one or more PSR-4 namespaces and returns descriptors for each
 * class that opts into class-backed plugin registration via either the
 * `#[SmartyPlugin]` attribute or the `*Modifier` / `*Function` /
 * `*Block` classname-suffix convention.
 *
 * The scanner is deliberately tolerant: classes that don't match either
 * registration style are skipped silently (so a directory full of
 * helpers can sit alongside plugins). Only manually-registered classes
 * that fail both checks raise an exception — that's a programmer error
 * the caller wants to know about.
 */
class PluginScanner
{
    /**
     * @param  array<int, string>  $namespaces
     * @param  array<int, class-string>  $manualClasses
     * @return array<int, PluginDescriptor>
     */
    public static function scan(array $namespaces, array $manualClasses): array
    {
        $descriptors = [];

        foreach ($namespaces as $namespace) {
            foreach (self::classesIn($namespace) as $class) {
                $descriptor = self::resolveDescriptor($class);
                if ($descriptor instanceof PluginDescriptor) {
                    $descriptors[] = $descriptor;
                }
            }
        }

        foreach ($manualClasses as $class) {
            $descriptor = self::resolveDescriptor($class);
            if (! $descriptor instanceof PluginDescriptor) {
                throw PluginRegistrationException::unrecognizedClass($class);
            }
            $descriptors[] = $descriptor;
        }

        return $descriptors;
    }

    /**
     * @param  class-string|string  $class
     */
    public static function resolveDescriptor(string $class): ?PluginDescriptor
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        // Abstracts, interfaces, traits, enums-with-private-constructor:
        // not instantiable → not a plugin.
        if (! $reflection->isInstantiable()) {
            return null;
        }

        $attributes = $reflection->getAttributes(SmartyPlugin::class);
        if ($attributes !== []) {
            $attribute = $attributes[0]->newInstance();

            if (! in_array($attribute->type, ['modifier', 'function', 'block'], true)) {
                throw PluginRegistrationException::invalidType($attribute->type, $class);
            }

            /** @var class-string $fqcn */
            $fqcn = $reflection->getName();

            return new PluginDescriptor($attribute->type, $attribute->name, $fqcn);
        }

        $type = self::typeFromSuffix($reflection->getShortName());
        if ($type === null) {
            return null;
        }

        /** @var class-string $fqcn */
        $fqcn = $reflection->getName();

        return new PluginDescriptor($type, self::nameFromConvention($reflection, $type), $fqcn);
    }

    /**
     * @return 'modifier'|'function'|'block'|null
     */
    private static function typeFromSuffix(string $shortName): ?string
    {
        if (str_ends_with($shortName, 'Modifier')) {
            return 'modifier';
        }
        if (str_ends_with($shortName, 'Function')) {
            return 'function';
        }
        if (str_ends_with($shortName, 'Block')) {
            return 'block';
        }

        return null;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  'modifier'|'function'|'block'  $type
     */
    private static function nameFromConvention(ReflectionClass $reflection, string $type): string
    {
        // A `public string $name` property with a default value wins over
        // the derived name. Lets a class keep a tidy classname while
        // emitting a different tag (`SinceModifier` → `since` is the
        // default; declare `$name = 'time_ago'` to override).
        if ($reflection->hasProperty('name')) {
            $property = $reflection->getProperty('name');
            if ($property->isPublic() && ! $property->isStatic() && $property->hasDefaultValue()) {
                $value = $property->getDefaultValue();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        $shortName = $reflection->getShortName();
        $suffixLength = strlen(ucfirst($type));
        $stripped = substr($shortName, 0, -$suffixLength);

        return Str::snake($stripped);
    }

    /**
     * Yield every concrete class FQN that lives under a PSR-4 namespace.
     * Resolves the namespace's filesystem roots through the active
     * Composer autoloader so apps with non-standard directory layouts
     * (PSR-4 mappings to `src/`, `module/`, etc.) work without config.
     *
     * Returns an empty iterator when the namespace doesn't resolve to
     * any directory or when none of the resolved directories exist —
     * disabling discovery for that namespace silently.
     *
     * @return iterable<int, class-string>
     */
    private static function classesIn(string $namespace): iterable
    {
        $namespace = trim($namespace, '\\');
        if ($namespace === '') {
            return;
        }

        $loader = self::composerLoader();
        if (! $loader instanceof ClassLoader) {
            return;
        }

        foreach (self::directoriesForNamespace($loader, $namespace) as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($directory));
                $relative = ltrim($relative, DIRECTORY_SEPARATOR);
                $relative = substr($relative, 0, -4); // strip .php

                /** @var class-string $class */
                $class = $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

                yield $class;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function directoriesForNamespace(ClassLoader $loader, string $namespace): array
    {
        $namespace .= '\\';
        $prefixes = $loader->getPrefixesPsr4();
        $directories = [];

        foreach ($prefixes as $prefix => $paths) {
            if (! str_starts_with($namespace, $prefix)) {
                continue;
            }

            $relative = substr($namespace, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative);

            foreach ($paths as $base) {
                $directories[] = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.rtrim($relativePath, DIRECTORY_SEPARATOR);
            }
        }

        return $directories;
    }

    private static function composerLoader(): ?ClassLoader
    {
        $functions = spl_autoload_functions();

        foreach ($functions as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
                return $autoloader[0];
            }
        }

        return null;
    }
}
