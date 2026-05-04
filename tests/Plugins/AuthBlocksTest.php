<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
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
}
