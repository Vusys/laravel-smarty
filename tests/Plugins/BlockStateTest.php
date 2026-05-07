<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Throwable;
use Vusys\LaravelSmarty\Plugins\BlockState;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * Block plugins like {auth} and {error} push the outer variable onto a stack
 * during the open phase and pop it back during close. If the body throws,
 * Smarty's compiled `while ($_block_repeat) { … }` loop never reaches the
 * close call, so the entry leaks. Under PHP-FPM the worker dies at request
 * end and the leak self-heals; under Octane / Swoole / RoadRunner the
 * closure-static stack survives across requests and grows monotonically.
 *
 * SmartyEngine::get() resets BlockState in a `finally` so the stack is clean
 * between renders regardless of what happened during the previous fetch.
 */
class BlockStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BlockState::reset();
    }

    public function test_render_resets_block_state_after_clean_render(): void
    {
        BlockState::push('auth.user', 'leaked-from-elsewhere');

        view('hello', ['name' => 'World'])->render();

        $this->assertFalse(
            BlockState::hasEntries('auth.user'),
            'Stack from before the render must not survive past it.'
        );
    }

    public function test_auth_block_state_clears_after_body_throws(): void
    {
        $this->actingAs($this->stubUser());

        try {
            view('auth_throws')->render();
            $this->fail('Expected the body to throw.');
        } catch (Throwable) {
            // Expected.
        }

        $this->assertFalse(
            BlockState::hasEntries('auth.user'),
            '{auth} body throwing must not leak the outer-$user stack frame past the render boundary.'
        );
    }

    public function test_error_block_state_clears_after_body_throws(): void
    {
        Session::start();
        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'email' => ['boom'],
        ]));
        Session::put('errors', $errors);

        try {
            view('error_throws')->render();
            $this->fail('Expected the body to throw.');
        } catch (Throwable) {
            // Expected.
        }

        $this->assertFalse(
            BlockState::hasEntries('error.message'),
            '{error} body throwing must not leak the outer-$message stack frame past the render boundary.'
        );
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
