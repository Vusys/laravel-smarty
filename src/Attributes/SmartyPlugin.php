<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Attributes;

use Attribute;

/**
 * Tags a class for class-backed plugin auto-discovery.
 *
 * Apply to any class with an `__invoke()` method matching the relevant
 * Smarty plugin signature for the chosen `$type`. The class is then
 * picked up by `LaravelSmarty`'s namespace scan (or
 * `LaravelSmarty::registerPluginClass()`) and wired into every Smarty
 * instance the package builds.
 *
 * The attribute's `$type` and `$name` are authoritative — they trump the
 * classname-suffix convention, so a class can opt out of the convention
 * (rename freely, live anywhere in the configured namespaces) without
 * any double-registration.
 *
 * Example:
 *
 *     #[SmartyPlugin(type: 'modifier', name: 'since')]
 *     final class Since
 *     {
 *         public function __invoke(mixed $value): string
 *         {
 *             return $value === null ? '' : Carbon::parse($value)->diffForHumans();
 *         }
 *     }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SmartyPlugin
{
    /**
     * @param  'modifier'|'function'|'block'  $type
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
    ) {}
}
