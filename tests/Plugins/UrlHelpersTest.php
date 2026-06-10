<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Support\Facades\Route;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\TestCase;

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

    public function test_url_tags_are_registered_uncached(): void
    {
        // URL generation reads the current request's host/scheme
        // (multi-tenant domains, X-Forwarded-Host), so generated URLs
        // must not be baked into the output cache — the same reasoning
        // as the $route wrapper's nocache flag in SmartyEngine.
        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['route', 'url', 'asset', 'signed_route', 'temporary_signed_route'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_FUNCTION, $name);
            $this->assertFalse($cacheable, "{{$name}} must register with cacheable=false");
        }
    }
}
