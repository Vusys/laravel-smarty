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
     * `{php}` is a raw PHP block — the largest single RCE vector.
     * `{math}` evaluates its `equation` argument with `eval()` (see
     * Smarty's Math.php); even with input filtering, no admin needs that.
     *
     * @var array<int, string>
     */
    public $disabled_tags = ['php', 'math'];

    /**
     * Forbid `\App\Models\User::find(...)` from templates. Forces data
     * through controllers / view models. Specific classes can be opted
     * back in by subclassing and overriding `$static_classes`.
     *
     * Smarty's documented `'none'` sentinel is broken upstream — it ends
     * up in `in_array($class, 'none')` which is a PHP 8 TypeError. The
     * non-empty array with a sentinel entry no real class can match
     * achieves the same intent: the parser sees a non-empty list (so it
     * gates access), and `in_array()` returns false for every real class.
     *
     * @var array<int, string>
     */
    public $static_classes = ['__laravel_smarty_deny_all__'];

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
