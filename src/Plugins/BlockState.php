<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Plugins;

/**
 * Push/pop store for block-plugin "outer value" stacks.
 *
 * Block plugins like {auth} and {error} need to bind a variable on the open
 * call and restore the prior value on close. A `static $stack = []` inside
 * the plugin closure works for the happy path, but if the body throws,
 * Smarty never re-invokes the closure with $content !== null and the close
 * phase never runs — the entry leaks and the stack grows monotonically for
 * the lifetime of the worker. Under Octane / Swoole / RoadRunner the closure
 * (and its `static`) survives across requests, so the leak compounds.
 *
 * SmartyEngine calls reset() in a finally block around fetch(), which
 * guarantees a clean stack between renders even when the previous render
 * threw — keeping memory bounded and removing any chance that leftover
 * frames from a failed render influence a later one.
 */
class BlockState
{
    /** @var array<string, array<int, mixed>> */
    private static array $stacks = [];

    public static function push(string $name, mixed $value): void
    {
        self::$stacks[$name] ??= [];
        self::$stacks[$name][] = $value;
    }

    public static function pop(string $name): mixed
    {
        self::$stacks[$name] ??= [];

        return array_pop(self::$stacks[$name]);
    }

    public static function hasEntries(string $name): bool
    {
        return ! empty(self::$stacks[$name]);
    }

    public static function reset(): void
    {
        self::$stacks = [];
    }
}
