<?php

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Support\Facades\Route as RouteFacade;
use Vusys\LaravelSmarty\Globals\Route;
use Vusys\LaravelSmarty\Tests\TestCase;

class RouteTest extends TestCase
{
    public function test_to_generates_named_route_url(): void
    {
        RouteFacade::get('/posts/{post}', fn () => 'ok')->name('posts.show');

        $route = Route::make();

        $this->assertSame(url('/posts/42'), $route->to('posts.show', ['post' => 42]));
    }

    public function test_to_without_params(): void
    {
        RouteFacade::get('/explore', fn () => 'ok')->name('explore.index');

        $route = Route::make();

        $this->assertSame(url('/explore'), $route->to('explore.index'));
    }

    public function test_path_emits_root_relative_url(): void
    {
        RouteFacade::get('/posts/{post}', fn () => 'ok')->name('posts.show');

        $route = Route::make();

        $this->assertSame('/posts/42', $route->path('posts.show', ['post' => 42]));
    }

    public function test_asset_returns_asset_url(): void
    {
        $route = Route::make();

        $this->assertStringEndsWith('/img/logo.svg', $route->asset('img/logo.svg'));
    }

    public function test_url_returns_absolute_url(): void
    {
        $route = Route::make();

        $this->assertSame(url('/login'), $route->url('/login'));
    }
}
