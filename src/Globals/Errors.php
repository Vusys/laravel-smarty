<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Read-only template wrapper for Laravel's `ViewErrorBag`.
 *
 * Auto-assigned to `$errors` for every render in `SmartyEngine::get()`.
 * The wrapper is **always non-null** — it tolerates the absence of a
 * session store (queue/mail/console contexts), a missing `errors` key,
 * or a non-bag value, returning empty/false/null/0 uniformly. Templates
 * can call `{if $errors->any()}` without guarding the wrapper itself.
 *
 * `getBag(...)` returns a *new `Errors` wrapper* scoped to the named
 * bag, so templates never have to reach into Laravel's `MessageBag`
 * directly — the wrapper's own surface (`any()`, `has()`, `first()`,
 * etc.) works the same on default and named bags.
 */
final class Errors
{
    public function __construct(
        private readonly ?ViewErrorBag $bag = null,
        private readonly string $name = 'default',
    ) {}

    public static function make(): self
    {
        if (! app()->bound('session.store')) {
            return new self;
        }

        $errors = resolve('session.store')->get('errors');

        return new self($errors instanceof ViewErrorBag ? $errors : null);
    }

    public function any(): bool
    {
        return $this->resolveBag()->any();
    }

    public function has(string $key): bool
    {
        return $this->resolveBag()->has($key);
    }

    public function count(): int
    {
        return $this->resolveBag()->count();
    }

    /**
     * @return array<string>
     */
    public function all(?string $format = null): array
    {
        return $format === null
            ? $this->resolveBag()->all()
            : $this->resolveBag()->all($format);
    }

    public function first(string $key = '*', ?string $format = null): string
    {
        return $format === null
            ? $this->resolveBag()->first($key)
            : $this->resolveBag()->first($key, $format);
    }

    /**
     * Mirrors `MessageBag::get()`. Returns `array<string>` for an exact
     * key match; for wildcard keys (e.g. `user.*`) returns the nested
     * `array<string, array<string>>` shape MessageBag uses to keep the
     * matching subkeys distinct.
     *
     * @return array<string>|array<string, array<string>>
     */
    public function get(string $key, ?string $format = null): array
    {
        return $format === null
            ? $this->resolveBag()->get($key)
            : $this->resolveBag()->get($key, $format);
    }

    /**
     * Sub-wrapper bound to a named bag. The default bag is `'default'`,
     * so `getBag('default')` returns the same logical view as the root
     * wrapper. Unknown bag names return an empty wrapper rather than
     * raising — same defensive shape as the missing-session path.
     */
    public function getBag(string $name): self
    {
        return new self($this->bag, $name);
    }

    private function resolveBag(): MessageBag
    {
        if (! $this->bag instanceof ViewErrorBag) {
            return new MessageBag;
        }

        $bag = $this->bag->getBag($this->name);

        return $bag instanceof MessageBag ? $bag : new MessageBag;
    }
}
