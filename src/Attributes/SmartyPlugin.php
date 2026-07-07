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
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class SmartyPlugin
{
    /**
     * `$type` is intentionally a bare `string` and NOT annotated as the
     * `'modifier'|'function'|'block'` union: PHP does not enforce a PHPDoc
     * union when the attribute is built via reflection, so an invalid
     * `type:` written in userland source (see the BadAttributeType*
     * fixtures) constructs happily and can only be caught at discovery
     * time by PluginScanner::resolveDescriptor. Narrowing the type here
     * would make that runtime guard look like dead code to static
     * analysis (Larastan) and invite its removal.
     *
     * @param  bool  $cacheable  Set to false when the plugin's output
     *                           depends on request state (auth, session,
     *                           locale, current URL): under
     *                           `smarty.caching` the call is then placed
     *                           in a {nocache} region and re-evaluated on
     *                           every cache hit instead of being baked
     *                           into the cached page. Note Smarty only
     *                           honours this for function and block
     *                           plugins — modifier output follows the
     *                           cacheability of the expression it
     *                           appears in.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly bool $cacheable = true,
    ) {}
}
