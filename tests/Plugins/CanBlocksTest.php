<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Vusys\LaravelSmarty\Tests\TestCase;

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

    public function test_canany_renders_when_at_least_one_ability_passes(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit-post', fn ($user, $post) => $post->owner === 'me');
        Gate::define('delete-post', fn () => false);

        $output = view('canany', ['post' => (object) ['owner' => 'me']])->render();

        $this->assertStringContainsString('[canany-yes]', $output);
    }

    public function test_canany_skips_when_all_abilities_deny(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit-post', fn () => false);
        Gate::define('delete-post', fn () => false);

        $output = view('canany', ['post' => (object) ['owner' => 'me']])->render();

        $this->assertStringNotContainsString('[canany-yes]', $output);
    }

    public function test_canall_renders_only_when_every_ability_passes(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit-post', fn () => true);
        Gate::define('delete-post', fn () => true);

        $output = view('canall', ['post' => (object) ['owner' => 'me']])->render();

        $this->assertStringContainsString('[canall-yes]', $output);
    }

    public function test_canall_skips_when_any_ability_denies(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit-post', fn () => true);
        Gate::define('delete-post', fn () => false);

        $output = view('canall', ['post' => (object) ['owner' => 'me']])->render();

        $this->assertStringNotContainsString('[canall-yes]', $output);
    }

    public function test_canany_does_not_evaluate_body_when_all_deny(): void
    {
        $output = view('canany_lazy')->render();

        $this->assertStringContainsString('[done]', $output);
        $this->assertStringNotContainsString('A=', $output);
    }

    public function test_canall_does_not_evaluate_body_when_any_denies(): void
    {
        $output = view('canall_lazy')->render();

        $this->assertStringContainsString('[done]', $output);
        $this->assertStringNotContainsString('B=', $output);
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
