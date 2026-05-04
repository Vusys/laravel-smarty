<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Vusys\LaravelSmarty\Tests\TestCase;

class ViteTest extends TestCase
{
    public function test_vite_tag_invokes_vite_with_entrypoints_and_build_directory(): void
    {
        $fake = new class extends Vite
        {
            /** @var array<int, array{entrypoints: mixed, buildDirectory: ?string}> */
            public array $calls = [];

            public int $refreshCalls = 0;

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

        $this->assertStringContainsString('string=<vite-tags 1>', $output);
        $this->assertStringContainsString('array=<vite-tags 2>', $output);
        $this->assertStringContainsString('build=<vite-tags 3>', $output);
        $this->assertStringContainsString('refresh=<refresh>', $output);
    }
}
