<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Read-only template wrapper for URL generation.
 *
 * Auto-assigned to `$route` for every render in
 * `SmartyEngine::get()`. Covers the cases the `{route}`, `{url}`,
 * `{asset}` plugin tags can't reach — `{include}` parameters, `{if}`
 * conditions, attribute expressions. The plugin tags stay for the
 * common output-position case.
 *
 * Marked nocache by `SmartyEngine::get()` even though URL generation
 * looks deterministic: `UrlGenerator::route()` reads the current
 * request's host/scheme to decide between absolute and root-relative
 * URLs, so a baked URL could be wrong on the next render under a
 * different host (multi-tenant, proxy that swaps `X-Forwarded-Host`,
 * etc.).
 */
final class Route
{
    public function __construct(
        private readonly UrlGenerator $url,
    ) {}

    public static function make(): self
    {
        /** @var UrlGenerator $url */
        $url = resolve(UrlGenerator::class);

        return new self($url);
    }

    /**
     * Generate a URL for a named route. Accepts both associative
     * (`['post' => $id]`) and positional (`[$id]`) parameter arrays,
     * mirroring Laravel's `UrlGenerator::route()`.
     *
     * @param  array<int|string, mixed>  $params
     */
    public function to(string $name, array $params = []): string
    {
        return $this->url->route($name, $params);
    }

    /**
     * Like `to()` but emits a root-relative URL (no scheme/host).
     *
     * @param  array<int|string, mixed>  $params
     */
    public function path(string $name, array $params = []): string
    {
        return $this->url->route($name, $params, false);
    }

    public function asset(string $path): string
    {
        return $this->url->asset($path);
    }

    public function url(string $path): string
    {
        return $this->url->to($path);
    }
}
