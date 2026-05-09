<?php

namespace Vusys\LaravelSmarty\Tests;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

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
        $first = view('csrf_field')->render();
        $this->assertStringContainsString('value="'.Session::token().'"', $first);

        // Rotate the CSRF token; the cached output must not pin the old one.
        Session::regenerateToken();
        $second = view('csrf_field')->render();
        $this->assertStringContainsString('value="'.Session::token().'"', $second);
        $this->assertStringNotContainsString('value="'.csrf_token().'_x"', $second);
    }

    public function test_csrf_token_re_evaluates_on_cache_hit(): void
    {
        Session::start();
        $first = view('csrf_token')->render();
        $this->assertStringContainsString('content="'.Session::token().'"', $first);

        // Rotate the CSRF token; the cached output must not pin the old one.
        Session::regenerateToken();
        $second = view('csrf_token')->render();
        $this->assertStringContainsString('content="'.Session::token().'"', $second);
        $this->assertStringNotContainsString('content=""', $second);
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

    public function test_auto_shared_auth_wrapper_re_evaluates_on_cache_hit(): void
    {
        // First render: guest. Wrapper is null; template hits the {else} arm.
        $first = view('cache_auth_wrapper')->render();
        $this->assertStringContainsString('guest', $first);
        $this->assertStringNotContainsString('auth-id=', $first);

        // Second render against the warm cache: now authed. Body must run.
        $this->actingAs($this->stubUser(7));
        $second = view('cache_auth_wrapper')->render();
        $this->assertStringContainsString('auth-id=7', $second);
        $this->assertStringNotContainsString('guest', $second);
    }

    public function test_auto_shared_request_wrapper_re_evaluates_on_cache_hit(): void
    {
        Route::get('/first', fn () => 'ok')->name('first');
        Route::get('/second', fn () => 'ok')->name('second');

        $this->get('/first');
        $first = view('cache_request_wrapper')->render();
        $this->assertStringContainsString('path=first', $first);

        $this->get('/second');
        $second = view('cache_request_wrapper')->render();
        $this->assertStringContainsString('path=second', $second);
    }

    public function test_auto_shared_route_wrapper_re_evaluates_on_cache_hit(): void
    {
        Route::get('/home', fn () => 'ok')->name('home.index');

        // First render: default root URL.
        url()->forceRootUrl('http://first.test');
        $first = view('cache_route_wrapper')->render();
        $this->assertStringContainsString('url=http://first.test/home', $first);

        // Mutate the URL generator's root URL. With $route nocache the
        // second render emits the new host instead of the baked first
        // one.
        url()->forceRootUrl('http://second.test');
        $second = view('cache_route_wrapper')->render();
        $this->assertStringContainsString('url=http://second.test/home', $second);
    }

    public function test_can_and_cannot_blocks_re_evaluate_on_cache_hit(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('update-post', static fn (): bool => false);

        $first = view('can', ['post' => (object) ['owner' => 'me']])->render();
        $this->assertStringNotContainsString('[can-yes]', $first);
        $this->assertStringContainsString('[cannot-yes]', $first);

        Gate::define('update-post', static fn (): bool => true);
        $second = view('can', ['post' => (object) ['owner' => 'me']])->render();
        $this->assertStringContainsString('[can-yes]', $second);
        $this->assertStringNotContainsString('[cannot-yes]', $second);
    }

    public function test_canany_block_re_evaluates_on_cache_hit(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit-post', static fn (): bool => false);
        Gate::define('delete-post', static fn (): bool => false);

        $first = view('canany', ['post' => (object) ['owner' => 'me']])->render();
        $this->assertStringNotContainsString('[canany-yes]', $first);

        Gate::define('edit-post', static fn (): bool => true);
        $second = view('canany', ['post' => (object) ['owner' => 'me']])->render();
        $this->assertStringContainsString('[canany-yes]', $second);
    }

    public function test_canall_block_re_evaluates_on_cache_hit(): void
    {
        $this->actingAs($this->stubUser());
        Gate::define('edit-post', static fn (): bool => true);
        Gate::define('delete-post', static fn (): bool => false);

        $first = view('canall', ['post' => (object) ['owner' => 'me']])->render();
        $this->assertStringNotContainsString('[canall-yes]', $first);

        Gate::define('delete-post', static fn (): bool => true);
        $second = view('canall', ['post' => (object) ['owner' => 'me']])->render();
        $this->assertStringContainsString('[canall-yes]', $second);
    }

    public function test_error_block_re_evaluates_on_cache_hit(): void
    {
        Session::start();

        $first = view('error')->render();
        $this->assertStringNotContainsString('class="err"', $first);

        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'email' => ['boom'],
        ]));
        Session::put('errors', $errors);

        $second = view('error')->render();
        $this->assertStringContainsString('<p class="err">boom</p>', $second);
    }

    public function test_lang_re_evaluates_on_cache_hit(): void
    {
        Lang::addLines(['messages.greeting' => 'Hello'], 'en');
        Lang::addLines(['messages.greeting' => 'Bonjour'], 'fr');

        App::setLocale('en');
        $first = view('cache_lang')->render();
        $this->assertStringContainsString('greeting=Hello', $first);

        App::setLocale('fr');
        $second = view('cache_lang')->render();
        $this->assertStringContainsString('greeting=Bonjour', $second);
    }

    public function test_lang_choice_re_evaluates_on_cache_hit(): void
    {
        Lang::addLines(['messages.apples' => '{0} no apples|[1,*] :count apples'], 'en');

        $first = view('cache_lang_choice', ['count' => 0])->render();
        $this->assertStringContainsString('apples=no apples', $first);

        $second = view('cache_lang_choice', ['count' => 5])->render();
        $this->assertStringContainsString('apples=5 apples', $second);
    }

    public function test_service_resolves_container_on_cache_hit(): void
    {
        $count = 0;
        $this->app->bind('test.counter', static function () use (&$count): string {
            $count++;

            return 'value';
        });

        view('cache_service')->render();
        view('cache_service')->render();

        // {service} is non-cacheable, so the container resolve runs on every
        // render. If a refactor silently drops the cacheable=false flag, the
        // first render's resolved value is baked into the cache and the
        // closure only fires once.
        $this->assertSame(2, $count);
    }

    public function test_vite_calls_re_evaluate_on_cache_hit(): void
    {
        $fake = new class extends Vite
        {
            public int $calls = 0;

            public int $refreshCalls = 0;

            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                $this->calls++;

                return new HtmlString('<vite-'.$this->calls.'>');
            }

            public function reactRefresh(): HtmlString
            {
                $this->refreshCalls++;

                return new HtmlString('<refresh-'.$this->refreshCalls.'>');
            }
        };

        $this->app->instance(Vite::class, $fake);

        $first = view('cache_vite')->render();
        $second = view('cache_vite')->render();

        $this->assertSame(2, $fake->calls);
        $this->assertSame(2, $fake->refreshCalls);
        $this->assertStringContainsString('<vite-1>', $first);
        $this->assertStringContainsString('<vite-2>', $second);
        $this->assertStringContainsString('<refresh-1>', $first);
        $this->assertStringContainsString('<refresh-2>', $second);
    }
}
