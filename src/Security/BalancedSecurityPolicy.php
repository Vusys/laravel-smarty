<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Security;

use Smarty\Security;

/**
 * Sensible default \Smarty\Security policy for templates authored by
 * trusted parties — typically developers and admins editing CMS templates.
 *
 * Blocks the obvious server-side execution vectors (`{php}`, `{math}`,
 * super-globals, arbitrary static-class access) while leaving common
 * formatting affordances (modifiers, constants) alone so admin-authored
 * templates aren't mysteriously broken.
 *
 * For untrusted, user-submitted templates use {@see StrictSecurityPolicy}
 * instead — it inherits from this class and tightens the remaining knobs.
 */
class BalancedSecurityPolicy extends Security
{
    /**
     * Sentinel entry for `$static_classes` — Smarty's documented
     * `'none'` value is broken upstream (it ends up in
     * `in_array($class, 'none')`, a PHP 8 TypeError). Using a non-empty
     * array with one entry no real class can match has the same intent:
     * the parser sees a non-empty list (so it gates access), and
     * `in_array()` returns false for every real class.
     */
    public const DENY_ALL_STATIC_CLASSES_SENTINEL = '__laravel_smarty_deny_all__';

    /**
     * Tags this policy bans. Lifted to a class constant so subclasses
     * (notably {@see StrictSecurityPolicy}) can compose against the
     * Balanced baseline at class-load time, not via a runtime
     * `array_merge($this->disabled_tags, …)` that would silently drop
     * Balanced bans if a user-subclass shadowed the property.
     *
     * `{php}` is a raw PHP block — the largest single RCE vector.
     * `{math}` evaluates its `equation` argument with `eval()` (see
     * Smarty's Math.php); even with input filtering, no admin needs that.
     */
    public const CORE_DISABLED_TAGS = ['php', 'math'];

    /**
     * @var array<int, string>
     */
    public $disabled_tags = self::CORE_DISABLED_TAGS;

    /**
     * Forbid `\App\Models\User::find(...)` from templates. Forces data
     * through controllers / view models.
     *
     * Subclassing: override to allow specific classes by listing them
     * directly (e.g. `['App\\Trusted']`) — the upstream `empty()`
     * short-circuit in `isTrustedStaticClass()` only kicks in for an
     * empty array, so any non-empty list works as an allow-list. The
     * sentinel is only needed when you want pure deny-all and have no
     * classes to allow; never override to `[]`.
     *
     * @var array<int, string>
     */
    public $static_classes = [self::DENY_ALL_STATIC_CLASSES_SENTINEL];

    /**
     * Templates should reach state via Laravel helpers (`request()`,
     * `auth()`, `session()`), not `$_GET`/`$_POST`/`$_SERVER`.
     *
     * @var bool
     */
    public $allow_super_globals = false;

    /**
     * Constants stay readable. Admins commonly need framework/app
     * constants such as `APP_VERSION` or `PHP_EOL`. Apps that `define()`
     * secrets are an anti-pattern; we don't shape the default around them.
     * Override to `false` (and optionally set `$trusted_constants`) to
     * tighten further.
     *
     * @var bool
     */
    public $allow_constants = true;

    /**
     * Explicit upstream default — listed here so the value is reviewable
     * alongside the other knobs.
     *
     * @var array<int, string>
     */
    public $streams = ['file'];

    /**
     * Defence against accidental include loops; loose enough that real
     * layouts pass.
     *
     * @var int
     */
    public $max_template_nesting = 50;
}
