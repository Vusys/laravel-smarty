<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Vusys\LaravelSmarty\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class UrlHelpersTest extends TestCase
{
    public function test_route_url_and_asset_helpers_resolve(): void
    {
        Route::get('/users/{id}', fn () => null)->name('users.show');

        $output = view('urls')->render();

        $this->assertStringContainsString('route=http://localhost/users/42', $output);
        $this->assertStringContainsString('url=http://localhost/foo', $output);
        $this->assertStringContainsString('asset=http://localhost/img.png', $output);
    }
}
