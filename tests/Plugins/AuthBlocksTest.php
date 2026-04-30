<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;

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
