<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Foundation\Vite;
use Smarty\Smarty;
use Vusys\LaravelSmarty\Tests\Fixtures\FakeVite;
use Vusys\LaravelSmarty\Tests\TestCase;

class ViteTest extends TestCase
{
    public function test_vite_helpers_invoke_underlying_vite_methods(): void
    {
        $fake = new FakeVite;
        $this->app->instance(Vite::class, $fake);

        $output = view('vite')->render();

        $this->assertSame('resources/js/app.js', $fake->calls[0]['entrypoints']);
        $this->assertNull($fake->calls[0]['buildDirectory']);

        $this->assertSame(['resources/css/app.css', 'resources/js/app.js'], $fake->calls[1]['entrypoints']);
        $this->assertNull($fake->calls[1]['buildDirectory']);

        $this->assertSame('resources/js/app.js', $fake->calls[2]['entrypoints']);
        $this->assertSame('custom-build', $fake->calls[2]['buildDirectory']);

        $this->assertSame(1, $fake->refreshCalls);

        $this->assertSame('resources/img/logo.svg', $fake->assetCalls[0]['path']);
        $this->assertNull($fake->assetCalls[0]['buildDirectory']);
        $this->assertSame('resources/img/logo.svg', $fake->assetCalls[1]['path']);
        $this->assertSame('custom-build', $fake->assetCalls[1]['buildDirectory']);

        $this->assertSame('resources/img/sprite.svg', $fake->contentCalls[0]['path']);

        $this->assertStringContainsString('string=<vite-tags 1>', $output);
        $this->assertStringContainsString('array=<vite-tags 2>', $output);
        $this->assertStringContainsString('build=<vite-tags 3>', $output);
        $this->assertStringContainsString('refresh=<refresh-1>', $output);
        $this->assertStringContainsString('nonce=nonce-abc123', $output);
        $this->assertStringContainsString('asset=/assets/resources/img/logo.svg', $output);
        $this->assertStringContainsString('asset_build=/assets/resources/img/logo.svg?build=custom-build', $output);
        // {vite_content} is a function plugin, not a {$var} interpolation, so
        // its output isn't auto-escaped — which is the point of the helper
        // (templates inline raw SVG markup directly into the document).
        $this->assertStringContainsString('content=<svg id="resources/img/sprite.svg"/>', $output);
    }

    public function test_vite_helpers_are_registered_uncached(): void
    {
        // Vite helpers resolve hot-mode vs build-manifest URLs, CSP nonces,
        // and SVG content per-request — caching the rendered output would
        // ship stale URLs/nonces across renders. Every Vite plugin must
        // register with cacheable=false.
        $this->app->instance(Vite::class, new FakeVite);

        view('vite')->render();

        $smarty = $this->app['view']->getEngineResolver()->resolve('smarty')->smarty();

        foreach (['vite', 'vite_react_refresh', 'csp_nonce', 'vite_asset', 'vite_content'] as $name) {
            [, $cacheable] = $smarty->getRegisteredPlugin(Smarty::PLUGIN_FUNCTION, $name);
            $this->assertFalse($cacheable, "{{$name}} must register with cacheable=false");
        }
    }

    public function test_csp_nonce_renders_empty_string_when_no_nonce_set(): void
    {
        $fake = new FakeVite;
        $fake->fakeNonce = null;
        $this->app->instance(Vite::class, $fake);

        $output = view('vite')->render();

        $this->assertStringContainsString('nonce=', $output);
        $this->assertStringNotContainsString('nonce=null', $output);
    }
}
