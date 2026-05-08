<?php

namespace Vusys\LaravelSmarty\Tests\Globals;

use ErrorException;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Session;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * End-to-end tests: render real Smarty templates that hit each wrapper
 * through the auto-share point. These prove the documented usage
 * patterns (`{if $auth}…`, `{$request->routeIs('foo.*')}`,
 * `{$route->to(...)}` as `{include}` parameters, etc.) actually compile
 * and dispatch correctly through Smarty 5.
 */
class IntegrationTest extends TestCase
{
    public function test_auth_wrapper_is_null_for_guest(): void
    {
        $output = view('globals_auth')->render();

        $this->assertStringContainsString('guest', $output);
        $this->assertStringNotContainsString('authed:', $output);
    }

    public function test_auth_wrapper_renders_id_when_authed(): void
    {
        $this->actingAs($this->stubUser(7));

        $output = view('globals_auth')->render();

        $this->assertStringContainsString('authed:7:7', $output);
    }

    public function test_auth_wrapper_loud_failure_outside_guard(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Attempt to read property "user" on null');

        // Guest, but template forgets the {if $auth} guard.
        view('globals_auth_loud')->render();
    }

    public function test_request_wrapper_reads_route_and_input(): void
    {
        RouteFacade::get('/feed', fn () => 'ok')->name('feed.index');
        $this->get('/feed?q=hi');

        $output = view('globals_request')->render();

        $this->assertStringContainsString('routeIs=yes', $output);
        $this->assertStringContainsString('input=hi', $output);
        $this->assertStringContainsString('path=feed', $output);
    }

    public function test_request_wrapper_reflects_synthetic_request_outside_http(): void
    {
        $output = view('globals_request')->render();

        $this->assertStringContainsString('routeIs=no', $output);
        $this->assertStringContainsString('input=fallback', $output);
    }

    public function test_session_wrapper_property_and_method_access(): void
    {
        Session::start();
        Session::put('status', 'saved');

        $output = view('globals_session')->render();

        $this->assertStringContainsString('status=saved', $output);
        $this->assertStringContainsString('has-error=no', $output);
        $this->assertStringContainsString('default=d', $output);
    }

    public function test_route_wrapper_generates_urls(): void
    {
        RouteFacade::get('/posts/{post}', fn () => 'ok')->name('posts.show');
        RouteFacade::get('/posts', fn () => 'ok')->name('posts.index');

        $output = view('globals_route')->render();

        $this->assertStringContainsString('href="'.url('/posts/42').'"', $output);
        $this->assertStringContainsString('src="'.asset('img/logo.svg').'"', $output);
        $this->assertStringContainsString('/posts', $output);
    }

    public function test_request_method_call_inside_class_array_plugin(): void
    {
        // Combination test: {class array=[..., 'is-active' => $request->routeIs(...)]}
        // exercises Smarty's array-literal expression parser passing a method-call
        // value into the `{class}` plugin. Confirms the nav-active example from
        // the README actually compiles and renders.
        RouteFacade::get('/feed', fn () => 'ok')->name('feed.index');
        RouteFacade::get('/explore', fn () => 'ok')->name('explore.index');

        $this->get('/feed');
        $output = view('globals_combined_class')->render();
        $this->assertStringContainsString('class="nav-item is-active"', $output);
        $this->assertStringContainsString('href="'.url('/feed').'"', $output);

        $this->get('/explore');
        $output = view('globals_combined_class')->render();
        $this->assertStringContainsString('class="nav-item"', $output);
        $this->assertStringNotContainsString('is-active', $output);
    }

    public function test_route_method_call_as_include_parameter(): void
    {
        // Combination test: {include file="..." action_url=$route->to(...)}.
        // Method call as an include-parameter value — one of the original
        // motivations for this whole change set, since plugin function tags
        // (e.g. `{route name=...}`) can't be used as parameter values.
        RouteFacade::get('/posts/{post}/replies', fn () => 'ok')->name('posts.replies.store');

        $output = view('globals_combined_include')->render();

        $this->assertStringContainsString('action="'.url('/posts/42/replies').'"', $output);
    }
}
