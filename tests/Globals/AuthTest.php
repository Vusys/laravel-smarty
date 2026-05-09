<?php

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Support\Facades\Auth as AuthFacade;
use Illuminate\Support\Facades\Gate;
use Vusys\LaravelSmarty\Globals\Auth;
use Vusys\LaravelSmarty\Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_resolve_returns_null_when_guest(): void
    {
        $this->assertNull(Auth::resolve());
    }

    public function test_resolve_returns_wrapper_when_authenticated(): void
    {
        $user = $this->stubUser();
        $this->actingAs($user);

        $auth = Auth::resolve();

        $this->assertNotNull($auth);
        $this->assertSame(1, $auth->id);
        $this->assertSame($user, $auth->user);
    }

    public function test_unknown_property_access_throws(): void
    {
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        // Real public readonly properties; accessing an undefined one
        // raises an "Undefined property" warning that Laravel's error
        // handler turns into an ErrorException — same loud-failure
        // mechanism that catches `{$auth->user->name}` on guest. The
        // intent is for typos like `{$auth->ide}` to fail in dev
        // instead of rendering empty.
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Undefined property');

        /** @phpstan-ignore property.notFound */
        $auth->ide; // intentional typo
    }

    public function test_is_returns_false_for_null(): void
    {
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();

        $this->assertNotNull($auth);
        $this->assertFalse($auth->is(null));
    }

    public function test_is_compares_by_identifier(): void
    {
        $user = $this->stubUser(42);
        $this->actingAs($user);

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->is($this->stubUser(42)));
        $this->assertFalse($auth->is($this->stubUser(43)));
    }

    public function test_can_with_single_model_argument(): void
    {
        // The common case: $auth->can('update', $post) — same shape as
        // Laravel's $user->can('update', $post). Laravel's Gate::check
        // wraps a single non-array argument into [arg] internally.
        $this->actingAs($this->stubUser());

        Gate::define('do-thing', fn ($user, $arg) => $arg === 'allowed');

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->can('do-thing', 'allowed'));
        $this->assertFalse($auth->can('do-thing', 'denied'));
    }

    public function test_can_with_array_of_arguments(): void
    {
        // Multi-argument abilities take an array, matching Laravel's
        // $user->can('compare', [$a, $b]) shape — NOT variadic. A user
        // writing $auth->can('foo', [$bar]) gets the same behaviour as
        // $user->can('foo', [$bar]) in plain Laravel, which is the
        // ergonomic anchor.
        $this->actingAs($this->stubUser());

        Gate::define('compare', fn ($user, $a, $b) => $a === $b);

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->can('compare', ['x', 'x']));
        $this->assertFalse($auth->can('compare', ['x', 'y']));
    }

    public function test_can_any_passes_when_at_least_one_ability_passes(): void
    {
        $this->actingAs($this->stubUser());

        Gate::define('edit', fn () => false);
        Gate::define('delete', fn ($user, $post) => $post->owner === 'me');

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->canAny(['edit', 'delete'], (object) ['owner' => 'me']));
        $this->assertFalse($auth->canAny(['edit', 'delete'], (object) ['owner' => 'someone-else']));
    }

    public function test_can_any_fails_closed_with_empty_abilities(): void
    {
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertFalse($auth->canAny([]));
    }

    public function test_can_all_passes_only_when_every_ability_passes(): void
    {
        $this->actingAs($this->stubUser());

        Gate::define('edit', fn () => true);
        Gate::define('delete', fn ($user, $post) => $post->owner === 'me');

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->canAll(['edit', 'delete'], (object) ['owner' => 'me']));
        $this->assertFalse($auth->canAll(['edit', 'delete'], (object) ['owner' => 'someone-else']));
    }

    public function test_can_all_fails_closed_with_empty_abilities(): void
    {
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        // Mathematically "for all x in [] check(x)" is vacuously true,
        // but the wrapper mirrors the {canall} block's fail-closed
        // posture — passing [] by mistake should never authorize.
        $this->assertFalse($auth->canAll([]));
    }

    public function test_guard_returns_null_when_named_guard_has_no_user(): void
    {
        $this->defineApiGuard();
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        // No user authed against the api guard.
        $this->assertNull($auth->guard('api'));
    }

    public function test_guard_returns_subwrapper_when_named_guard_authed(): void
    {
        $this->defineApiGuard();
        $apiUser = $this->stubUser(99);

        AuthFacade::guard('api')->setUser($apiUser);

        // No default-guard user, but api is authed.
        $apiAuth = Auth::resolve('api');

        $this->assertNotNull($apiAuth);
        $this->assertSame($apiUser, $apiAuth->user);
        $this->assertSame(99, $apiAuth->id);
    }

    private function defineApiGuard(): void
    {
        $this->app['config']->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
    }
}
