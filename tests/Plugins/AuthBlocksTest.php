<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Vusys\LaravelSmarty\Tests\TestCase;

class AuthBlocksTest extends TestCase
{
    public function test_auth_block_renders_only_when_authenticated(): void
    {
        $this->actingAs($this->stubUser());

        $output = view('auth')->render();

        $this->assertStringContainsString('[auth-yes]', $output);
        $this->assertStringNotContainsString('[guest-yes]', $output);
    }

    public function test_guest_block_renders_only_when_not_authenticated(): void
    {
        $output = view('auth')->render();

        $this->assertStringContainsString('[guest-yes]', $output);
        $this->assertStringNotContainsString('[auth-yes]', $output);
    }

    public function test_auth_block_does_not_evaluate_body_when_guest(): void
    {
        $output = view('auth_lazy', ['user' => null])->render();

        $this->assertStringContainsString('G=no-user', $output);
        $this->assertStringNotContainsString('A=', $output);
    }

    public function test_guest_block_does_not_evaluate_body_when_authenticated(): void
    {
        $this->actingAs($this->stubUser());

        $output = view('guest_lazy', ['user' => null])->render();

        $this->assertStringContainsString('A=no-touch', $output);
        $this->assertStringNotContainsString('G=', $output);
    }

    public function test_auth_block_binds_authenticated_user_as_dollar_user(): void
    {
        $this->actingAs($this->namedUser('Ada'));

        $output = view('auth_user')->render();

        $this->assertStringContainsString('inside=Ada', $output);
    }

    public function test_auth_block_restores_outer_user_after_exit(): void
    {
        $this->actingAs($this->namedUser('Ada'));

        $output = view('auth_user', ['user' => 'outer'])->render();

        $this->assertStringContainsString('before=outer', $output);
        $this->assertStringContainsString('inside=Ada', $output);
        $this->assertStringContainsString('after=outer', $output);
    }

    public function test_auth_block_does_not_assign_user_when_guest(): void
    {
        $output = view('auth_user', ['user' => 'outer'])->render();

        $this->assertStringContainsString('before=outer', $output);
        $this->assertStringNotContainsString('inside=', $output);
        $this->assertStringContainsString('after=outer', $output);
    }

    public function test_auth_block_assigns_user_when_no_outer_user(): void
    {
        $this->actingAs($this->stubUser());

        $output = view('auth_no_outer')->render();

        $this->assertStringContainsString('before=(none)', $output);
        $this->assertStringContainsString('inside=1', $output);
        // Outer $user was undefined; close restores to null, which the
        // |default modifier renders as '(none)'.
        $this->assertStringContainsString('after=(none)', $output);
    }

    public function test_auth_block_uses_named_guard(): void
    {
        $this->app['config']->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
        $this->app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => \stdClass::class]);

        // Set the user only on the api guard. actingAs($u, 'api') would
        // also call shouldUse('api') and shift the default guard, masking
        // the test we want to run here.
        Auth::guard('api')->setUser($this->stubUser());

        $output = view('auth_guard')->render();

        $this->assertStringContainsString('HIT-API user=1', $output);
    }

    public function test_guest_block_uses_named_guard(): void
    {
        $this->app['config']->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
        $this->app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => \stdClass::class]);

        // No user on the api guard — guest body should fire.
        $output = view('guest_guard')->render();
        $this->assertStringContainsString('HIT-GUEST', $output);

        // Now sign the user in on the api guard; guest body must not fire.
        Auth::guard('api')->setUser($this->stubUser());
        $authedOutput = view('guest_guard')->render();
        $this->assertStringNotContainsString('HIT-GUEST', $authedOutput);
    }

    public function test_can_block_does_not_evaluate_body_when_denied(): void
    {
        $output = view('can_lazy')->render();

        $this->assertStringContainsString('D=denied', $output);
        $this->assertStringNotContainsString('C=', $output);
    }

    public function test_cannot_block_does_not_evaluate_body_when_allowed(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit', static fn () => true);

        $output = view('cannot_lazy')->render();

        $this->assertStringContainsString('Y=allowed', $output);
        $this->assertStringNotContainsString('X=', $output);
    }

    protected function stubUser(): Authenticatable
    {
        return new class implements Authenticatable
        {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }

    protected function namedUser(string $name): Authenticatable
    {
        return new class($name) implements Authenticatable
        {
            public function __construct(public string $name) {}

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }
}
