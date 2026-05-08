<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Contracts\Session\Session as SessionContract;

/**
 * Read-only template wrapper for the session store.
 *
 * Auto-assigned to `$session` for every render in
 * `SmartyEngine::get()`. Read-only is deliberate — templates that
 * mutate session state are a code smell. In console / queue / mail
 * context the bound session is empty: `has(...)` returns false,
 * `get(...)` returns its default, `token()` returns null.
 */
final class Session
{
    public function __construct(
        private readonly SessionContract $session,
    ) {}

    public static function make(): self
    {
        /** @var SessionContract $session */
        $session = app('session.store');

        return new self($session);
    }

    /**
     * Convenience accessor — `{$session->status}` is shorthand for
     * `{$session->get('status')}`. Returns null when the key is
     * absent.
     */
    public function __get(string $key): mixed
    {
        return $this->session->get($key);
    }

    public function __isset(string $key): bool
    {
        return $this->session->has($key);
    }

    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get($key, $default);
    }

    public function token(): ?string
    {
        return $this->session->isStarted() ? $this->session->token() : null;
    }

    /**
     * Returns the keys flashed *to* this request (the read side of
     * `_flash.old`). Useful for partials that should only render
     * when there's actually a flash payload — e.g.
     * `{if $session->flash() !== []}…{/if}`.
     *
     * @return array<int, string>
     */
    public function flash(): array
    {
        $keys = $this->session->get('_flash.old', []);

        return is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
    }
}
