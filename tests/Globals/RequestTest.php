<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Route as RouteFacade;
use Vusys\LaravelSmarty\Globals\Request;
use Vusys\LaravelSmarty\Tests\TestCase;

class RequestTest extends TestCase
{
    public function test_route_is_matches_route_name_patterns(): void
    {
        RouteFacade::get('/feed', fn () => 'ok')->name('feed.index');
        $this->get('/feed');

        $request = Request::make();

        $this->assertTrue($request->routeIs('feed.index'));
        $this->assertTrue($request->routeIs('feed.*'));
        $this->assertTrue($request->routeIs('explore.*', 'feed.*'));
        $this->assertFalse($request->routeIs('explore.*'));
    }

    public function test_route_is_returns_false_when_no_route_bound(): void
    {
        // Synthetic request — no route resolved.
        $request = new Request(HttpRequest::create('/nowhere'));

        $this->assertFalse($request->routeIs('anything.*'));
    }

    public function test_route_returns_default_when_no_route_bound(): void
    {
        // Synthetic request — no route resolved (console / queue / mail
        // context, or any render outside the routing dispatcher).
        $request = new Request(HttpRequest::create('/nowhere'));

        $this->assertNull($request->route('post'));
        $this->assertSame('fallback', $request->route('post', 'fallback'));
    }

    public function test_route_distinguishes_absent_from_null_parameter(): void
    {
        // A parameter explicitly bound to `null` is *returned* as null,
        // not replaced by the default — so user code can tell "this
        // route doesn't have that key" apart from "the value is null".
        // In practice route params can't legitimately be null today
        // (string segments are always strings, implicit binding either
        // resolves or 404s), but pinning the contract guards against
        // someone setting it via Route::setParameter() in middleware.
        RouteFacade::get('/items/{item}', fn () => 'ok')->name('items.show');
        $this->get('/items/x');

        $route = resolve('router')->getRoutes()->getByName('items.show');
        request()->setRouteResolver(fn () => $route);
        $route->bind(request());
        $route->setParameter('item', null);

        $request = Request::make();

        // Present-but-null returns null, even when default is non-null.
        $this->assertNull($request->route('item', 'fallback'));

        // Absent returns the default.
        $this->assertSame('fallback', $request->route('missing', 'fallback'));
    }

    public function test_route_returns_route_parameter_or_null(): void
    {
        RouteFacade::get('/users/{username}', fn () => 'ok')->name('users.show');
        $this->get('/users/alice');

        $request = Request::make();

        $this->assertSame('alice', $request->route('username'));
        $this->assertNull($request->route('missing'));
        $this->assertSame('fallback', $request->route('missing', 'fallback'));
    }

    public function test_route_returns_bound_model_for_implicit_binding(): void
    {
        // Non-string route parameter — the wrapper hands back whatever
        // Laravel resolved, including bound model instances.
        RouteFacade::get('/posts/{post}', fn () => 'ok')->name('posts.show');
        $this->get('/posts/42');

        // Manually replace the resolved string with an object so we
        // don't need a full Eloquent setup — Laravel's implicit
        // binding does the same kind of substitution.
        $bound = (object) ['id' => 42, 'title' => 'hi'];
        $route = resolve('router')->getRoutes()->getByName('posts.show');
        request()->setRouteResolver(fn () => $route);
        $route->bind(request());
        $route->setParameter('post', $bound);

        $request = Request::make();

        $this->assertSame($bound, $request->route('post'));
    }

    public function test_is_matches_url_patterns(): void
    {
        $request = new Request(HttpRequest::create('/posts/42/replies'));

        $this->assertTrue($request->is('posts/*'));
        $this->assertTrue($request->is('posts/*/replies'));
        $this->assertFalse($request->is('users/*'));
    }

    public function test_input_returns_value_or_default(): void
    {
        $request = new Request(HttpRequest::create('/?q=hello'));

        $this->assertSame('hello', $request->input('q'));
        $this->assertSame('default', $request->input('missing', 'default'));
        $this->assertNull($request->input('missing'));
    }

    public function test_full_url_and_path(): void
    {
        $request = new Request(HttpRequest::create('https://example.com/foo/bar?x=1'));

        $this->assertSame('https://example.com/foo/bar?x=1', $request->fullUrl());
        $this->assertSame('foo/bar', $request->path());
    }
}
