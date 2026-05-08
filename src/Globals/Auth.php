<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
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
 */
final class Auth
{
    public function __construct(
        private readonly Guard $guard,
    ) {}

    /**
     * Resolve the wrapper for a guard, or `null` when that guard has
     * no authenticated user. Pass `null` for the application's
     * default guard.
     */
    public static function resolve(?string $guard = null): ?self
    {
        $resolved = AuthFacade::guard($guard);

        if ($resolved->user() === null) {
            return null;
        }

        return new self($resolved);
    }

    public function __get(string $property): mixed
    {
        return match ($property) {
            'check' => true, // wrapper only exists when authed; check is always true here
            'id' => $this->guard->id(),
            'user' => $this->guard->user(),
            default => null,
        };
    }

    public function __isset(string $property): bool
    {
        return in_array($property, ['check', 'id', 'user'], true);
    }

    /**
     * True when `$user` is the authenticated user. Returns false on
     * `null` so `$auth->is($post->user)` is safe when a relation
     * hasn't loaded.
     */
    public function is(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        $current = $this->guard->user();

        if ($current === null) {
            return false;
        }

        return $current->getAuthIdentifier() === $user->getAuthIdentifier();
    }

    /**
     * Mirrors `Gate::check($ability, [...arguments])` — accepts any
     * number of model/argument values like Laravel's gate does.
     */
    public function can(string $ability, mixed ...$arguments): bool
    {
        return Gate::forUser($this->guard->user())->check($ability, $arguments);
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
