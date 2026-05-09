<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Http\Request as HttpRequest;

/**
 * Read-only template wrapper for the current HTTP request.
 *
 * Auto-assigned to `$request` for every render in
 * `SmartyEngine::get()`. Purpose-built read API rather than a direct
 * expose of `Illuminate\Http\Request` — keeps the template surface
 * small and prevents state-mutating methods from being callable in
 * templates. In console / queue / mail context the wrapped request
 * is Laravel's synthetic Request: `routeIs(...)` is always false,
 * `input(...)` returns defaults, etc.
 */
final class Request
{
    public function __construct(
        private readonly HttpRequest $request,
    ) {}

    public static function make(): self
    {
        /** @var HttpRequest $request */
        $request = resolve('request');

        return new self($request);
    }

    /**
     * Mirrors `Illuminate\Http\Request::routeIs()` — variadic
     * patterns, returns true when any pattern matches the current
     * route name. Always false when no route is bound (console,
     * queue, etc.).
     */
    public function routeIs(string ...$patterns): bool
    {
        return $this->request->routeIs(...$patterns);
    }

    /**
     * Returns a route parameter as Laravel resolved it. For string
     * segments (e.g. the `{username}` placeholder) this is the raw
     * string; with implicit route-model binding it's the bound
     * model instance. `$default` is returned only when the binding
     * doesn't have that parameter at all, or when there is no
     * current route — a parameter that's explicitly bound to `null`
     * is returned as null.
     */
    public function route(string $param, mixed $default = null): mixed
    {
        $route = $this->request->route();

        if ($route === null) {
            return $default;
        }

        $params = $route->parameters();

        return array_key_exists($param, $params) ? $params[$param] : $default;
    }

    /**
     * Mirrors `Illuminate\Http\Request::is()` — variadic URL
     * patterns matched against the request path.
     */
    public function is(string ...$patterns): bool
    {
        return $this->request->is(...$patterns);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request->input($key, $default);
    }

    public function fullUrl(): string
    {
        return $this->request->fullUrl();
    }

    public function path(): string
    {
        return $this->request->path();
    }
}
