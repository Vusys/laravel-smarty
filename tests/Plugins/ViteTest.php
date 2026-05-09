<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Vusys\LaravelSmarty\Tests\TestCase;

class ViteTest extends TestCase
{
    public function test_vite_helpers_invoke_underlying_vite_methods(): void
    {
        $fake = new class extends Vite
        {
            /** @var array<int, array{entrypoints: mixed, buildDirectory: ?string}> */
            public array $calls = [];

            public int $refreshCalls = 0;

            /** @var array<int, array{path: string, buildDirectory: ?string}> */
            public array $assetCalls = [];

            /** @var array<int, array{path: string, buildDirectory: ?string}> */
            public array $contentCalls = [];

            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                $this->calls[] = ['entrypoints' => $entrypoints, 'buildDirectory' => $buildDirectory];

                return new HtmlString('<vite-tags '.count($this->calls).'>');
            }

            public function reactRefresh(): HtmlString
            {
                $this->refreshCalls++;

                return new HtmlString('<refresh>');
            }

            public function cspNonce()
            {
                return 'nonce-abc123';
            }

            public function asset($asset, $buildDirectory = null): string
            {
                $this->assetCalls[] = ['path' => $asset, 'buildDirectory' => $buildDirectory];

                return '/assets/'.$asset.($buildDirectory !== null ? '?build='.$buildDirectory : '');
            }

            public function content($asset, $buildDirectory = null): string
            {
                $this->contentCalls[] = ['path' => $asset, 'buildDirectory' => $buildDirectory];

                return '<svg id="'.$asset.'"/>';
            }
        };

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
        $this->assertStringContainsString('refresh=<refresh>', $output);
        $this->assertStringContainsString('nonce=nonce-abc123', $output);
        $this->assertStringContainsString('asset=/assets/resources/img/logo.svg', $output);
        $this->assertStringContainsString('asset_build=/assets/resources/img/logo.svg?build=custom-build', $output);
        // {vite_content} is a function plugin, not a {$var} interpolation, so
        // its output isn't auto-escaped — which is the point of the helper
        // (templates inline raw SVG markup directly into the document).
        $this->assertStringContainsString('content=<svg id="resources/img/sprite.svg"/>', $output);
    }

    public function test_csp_nonce_renders_empty_string_when_no_nonce_set(): void
    {
        $fake = new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }

            public function reactRefresh(): HtmlString
            {
                return new HtmlString('');
            }

            public function cspNonce()
            {
                return null;
            }

            public function asset($asset, $buildDirectory = null): string
            {
                return '';
            }

            public function content($asset, $buildDirectory = null): string
            {
                return '';
            }
        };

        $this->app->instance(Vite::class, $fake);

        $output = view('vite')->render();

        $this->assertStringContainsString('nonce=', $output);
        $this->assertStringNotContainsString('nonce=null', $output);
    }
}
