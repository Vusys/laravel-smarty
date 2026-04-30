<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class CanBlocksTest extends TestCase
{
    public function test_can_block_renders_only_when_gate_allows(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('update-post', fn ($user, $post) => $post->owner === 'me');

        $output = view('can', ['post' => (object) ['owner' => 'me']])->render();

        $this->assertStringContainsString('[can-yes]', $output);
        $this->assertStringNotContainsString('[cannot-yes]', $output);
    }

    public function test_cannot_block_renders_only_when_gate_denies(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('update-post', fn ($user, $post) => $post->owner === 'me');

        $output = view('can', ['post' => (object) ['owner' => 'someone-else']])->render();

        $this->assertStringContainsString('[cannot-yes]', $output);
        $this->assertStringNotContainsString('[can-yes]', $output);
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
