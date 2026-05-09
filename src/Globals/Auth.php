<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only template wrapper for the authenticated user.
 *
 * Auto-assigned to `$auth` for every render in `SmartyEngine::get()`.
 * `$auth` is **null when nobody is logged in** — the deliberate call
 * is loud failure: `{$auth->user->name}` outside an `{auth}` block or
 * `{if $auth}` guard raises an `ErrorException` instead of rendering
 * an empty string. That surfaces "I forgot the guest case" bugs in
 * development. Users who prefer silent rendering can lower
 * `smarty.error_reporting`, accepting the trade-off.
 *
 * Use `{if $auth}…{/if}` as the truthiness check; the property
 * surface is intentionally minimal (`id`, `user`) so that typos like
 * `{$auth->ide}` raise an "Cannot access undefined property" error
 * instead of silently rendering empty.
 */
final class Auth
{
    /**
     * Mirrors `auth()->id()`. Typed `mixed` because Laravel allows
     * custom identifier types (typically `int|string`, occasionally
     * UUID/object).
     */
    public readonly mixed $id;

    public function __construct(public readonly Authenticatable $user)
    {
        $this->id = $this->user->getAuthIdentifier();
    }

    /**
     * Resolve the wrapper for a guard, or `null` when that guard has
     * no authenticated user. Pass `null` for the application's
     * default guard.
     */
    public static function resolve(?string $guard = null): ?self
    {
        $user = AuthFacade::guard($guard)->user();

        if ($user === null) {
            return null;
        }

        return new self($user);
    }

    /**
     * True when `$user` is the authenticated user. Returns false on
     * `null` so `$auth->is($post->user)` is safe when a relation
     * hasn't loaded.
     */
    public function is(?Authenticatable $user): bool
    {
        return $user?->getAuthIdentifier() === $this->user->getAuthIdentifier();
    }

    /**
     * Mirrors `Authenticatable::can($ability, $arguments)` — pass the
     * model directly for the common single-argument case, or an array
     * for multi-argument abilities. Same shape as Laravel's
     * `$user->can(…)`.
     */
    public function can(string $ability, mixed $arguments = []): bool
    {
        return Gate::forUser($this->user)->check($ability, $arguments);
    }

    /**
     * Sub-wrapper bound to a non-default guard. Returns null when
     * that guard has no authenticated user, mirroring `resolve()`.
     */
    public function guard(string $name): ?self
    {
        return self::resolve($name);
    }
}
