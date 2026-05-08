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
        $this->assertTrue($auth->check);
        $this->assertSame(1, $auth->id);
        $this->assertSame($user, $auth->user);
    }

    public function test_isset_reports_known_properties(): void
    {
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue(isset($auth->check));
        $this->assertTrue(isset($auth->id));
        $this->assertTrue(isset($auth->user));
        $this->assertFalse(isset($auth->something_unknown));
    }

    public function test_get_returns_null_for_unknown_property(): void
    {
        $this->actingAs($this->stubUser());

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertNull($auth->something_unknown);
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

    public function test_can_defers_to_gate(): void
    {
        $this->actingAs($this->stubUser());

        Gate::define('do-thing', fn ($user, $arg) => $arg === 'allowed');

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->can('do-thing', 'allowed'));
        $this->assertFalse($auth->can('do-thing', 'denied'));
    }

    public function test_can_supports_variadic_arguments(): void
    {
        $this->actingAs($this->stubUser());

        Gate::define('compare', fn ($user, $a, $b) => $a === $b);

        $auth = Auth::resolve();
        $this->assertNotNull($auth);

        $this->assertTrue($auth->can('compare', 'x', 'x'));
        $this->assertFalse($auth->can('compare', 'x', 'y'));
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
