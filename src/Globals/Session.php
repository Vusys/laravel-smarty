<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Globals;

use Illuminate\Contracts\Session\Session as SessionContract;

/**
 * Read-only template wrapper for the session store.
 *
 * Auto-assigned to `$session` for every render in
 * `SmartyEngine::get()`. Read-only is deliberate — templates that
 * mutate session state are a code smell. The wrapper tolerates apps
 * that don't bind a session store at all (stateless API workers,
 * queue-only processes, etc.): `has(...)` returns false, `get(...)`
 * returns its default, `token()` returns null. The wrapper itself is
 * always non-null; templates can read it uniformly without guarding.
 */
final class Session
{
    public function __construct(
        private readonly ?SessionContract $session = null,
    ) {}

    public static function make(): self
    {
        if (! app()->bound('session.store')) {
            return new self;
        }

        /** @var SessionContract $session */
        $session = resolve('session.store');

        return new self($session);
    }

    /**
     * Convenience accessor — `{$session->status}` is shorthand for
     * `{$session->get('status')}`. Returns null when the key is
     * absent.
     */
    public function __get(string $key): mixed
    {
        return $this->session?->get($key);
    }

    public function __isset(string $key): bool
    {
        return $this->session instanceof SessionContract && $this->session->has($key);
    }

    public function has(string $key): bool
    {
        return $this->session instanceof SessionContract && $this->session->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->session instanceof SessionContract) {
            return $default;
        }

        return $this->session->get($key, $default);
    }

    public function token(): ?string
    {
        if (! $this->session instanceof SessionContract || ! $this->session->isStarted()) {
            return null;
        }

        return $this->session->token();
    }

    /**
     * Returns the keys flashed *to* this request — the keys for
     * which `$session->has(...)` would currently return true thanks
     * to a previous request's `flash()` call. Useful for partials
     * that should only render when there's actually a flash payload
     * (e.g. `{if $session->flashedKeys() !== []}…{/if}`).
     *
     * Distinct from Laravel's `$session->flash($key, $value)` setter
     * — this is read-only.
     *
     * Note: this reads the underlying `_flash.old` bag directly,
     * which is technically internal to `Illuminate\Session\Store`.
     * Laravel doesn't expose a public "what was flashed to me"
     * method as of writing; if that ever lands, this method should
     * delegate.
     *
     * @return array<int, string>
     */
    public function flashedKeys(): array
    {
        if (! $this->session instanceof SessionContract) {
            return [];
        }

        $keys = $this->session->get('_flash.old', []);

        return is_array($keys) ? array_values(array_filter($keys, is_string(...))) : [];
    }
}
