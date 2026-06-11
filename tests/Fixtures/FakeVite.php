<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Fixtures;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * Recording Vite double shared by every test that fakes the Vite
 * container binding (previously four near-identical anonymous classes).
 * Each method records its arguments and returns a distinctive marker
 * carrying the call count, so tests can assert both wiring and
 * re-evaluation across cached renders.
 */
class FakeVite extends Vite
{
    /** @var array<int, array{entrypoints: mixed, buildDirectory: ?string}> */
    public array $calls = [];

    public int $refreshCalls = 0;

    /** @var array<int, array{path: string, buildDirectory: ?string}> */
    public array $assetCalls = [];

    /** @var array<int, array{path: string, buildDirectory: ?string}> */
    public array $contentCalls = [];

    // Named fakeNonce: the parent class already declares an (untyped)
    // protected $nonce, and redeclaring it with a type is a fatal.
    public mixed $fakeNonce = 'nonce-abc123';

    public function __invoke($entrypoints, $buildDirectory = null): HtmlString
    {
        $this->calls[] = ['entrypoints' => $entrypoints, 'buildDirectory' => $buildDirectory];

        return new HtmlString('<vite-tags '.count($this->calls).'>');
    }

    public function reactRefresh(): HtmlString
    {
        $this->refreshCalls++;

        return new HtmlString('<refresh-'.$this->refreshCalls.'>');
    }

    public function cspNonce()
    {
        return $this->fakeNonce;
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
}
