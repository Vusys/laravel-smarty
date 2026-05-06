<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Smarty caching=true serves rendered output from disk on cache hits, so any
 * plugin whose result depends on request state (auth, session, csrf, etc.)
 * must be registered with cacheable=false — otherwise the first request's
 * output is baked into every subsequent render. These tests pin that
 * contract for every request-coupled plugin.
 */
class CachingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smarty.caching', true);
        $app['config']->set('smarty.cache_lifetime', 60);
        $app['config']->set('smarty.force_compile', false);
    }

    public function test_auth_block_re_evaluates_on_cache_hit(): void
    {
        // First render: guest. Cache stores the empty body.
        $first = view('auth')->render();
        $this->assertStringContainsString('[guest-yes]', $first);
        $this->assertStringNotContainsString('[auth-yes]', $first);

        // Second render against the warm cache: now authed. Body must run.
        $this->actingAs($this->stubUser());
        $second = view('auth')->render();
        $this->assertStringContainsString('[auth-yes]', $second);
        $this->assertStringNotContainsString('[guest-yes]', $second);
    }

    public function test_csrf_field_re_evaluates_on_cache_hit(): void
    {
        Session::start();
        $first = view('csrf')->render();
        $this->assertStringContainsString('value="'.Session::token().'"', $first);

        // Rotate the CSRF token; the cached output must not pin the old one.
        Session::regenerateToken();
        $second = view('csrf')->render();
        $this->assertStringContainsString('value="'.Session::token().'"', $second);
        $this->assertStringNotContainsString('value="'.csrf_token().'_x"', $second);
    }

    public function test_old_re_evaluates_on_cache_hit(): void
    {
        // First render: no flashed input → falls back to default.
        $first = view('old')->render();
        $this->assertStringContainsString('email=fallback@example.com', $first);

        // Flash input, render again: cache hit must surface the flashed value.
        Session::start();
        Session::flashInput(['email' => 'flashed@example.com']);
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $second = view('old')->render();
        $this->assertStringContainsString('email=flashed@example.com', $second);
    }

    public function test_session_helper_re_evaluates_on_cache_hit(): void
    {
        Session::start();
        Session::put('status', 'first');
        $first = view('config_session_markdown')->render();
        $this->assertStringContainsString('flash=first', $first);

        Session::put('status', 'second');
        $second = view('config_session_markdown')->render();
        $this->assertStringContainsString('flash=second', $second);
    }

    public function test_auto_shared_session_re_evaluates_on_cache_hit(): void
    {
        Session::start();
        Session::put('status', 'first');
        $first = view('shared_session_override')->render();
        $this->assertStringContainsString('status=first', $first);

        Session::put('status', 'second');
        $second = view('shared_session_override')->render();
        $this->assertStringContainsString('status=second', $second);
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

            public function setRememberToken($v): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }
}
