# Troubleshooting

Symptoms first, causes second — scan the headings for what you're seeing.

## Template edits don't show up after a deploy

Smarty compiles `.tpl` files into PHP under `smarty.compile_path` and, with
`compile_check` on (the default), recompiles whenever the source file is newer than the
compiled output. Two deploy patterns break that assumption:

- **`compile_check => false`** (the production performance setting) skips the mtime
  check entirely. Compiled output is trusted until you delete it — run
  `php artisan smarty:clear-compiled` (or `smarty:optimize --force`) as a deploy step.
- **Deploys that rewind mtimes** (rsync with `--times` from a build box, Docker image
  layers, some atomic-symlink strategies) can leave the new source file *older* than the
  compiled output, so even `compile_check` sees nothing to do. Same fix: clear or force
  on deploy.

If you use Smarty's output cache, pair it with `php artisan smarty:clear-cache` — cached
pages are not invalidated by template changes, only by lifetime expiry.

## `ReservedTemplateVariable` exception

```
Vusys\LaravelSmarty\Exceptions\ReservedTemplateVariable
```

`auth`, `request`, `session`, `route` and `errors` are auto-shared
[wrapper objects](wrapper-objects.md), assigned on every render. Passing one of those
names via view data (`view('foo', ['session' => …])`, `View::share('route', …)`, or a
composer's `$view->with('auth', …)`) would silently shadow the wrapper, so the engine
throws instead. Rename the colliding key.

The one exception: Laravel's stock `ShareErrorsFromSession` middleware shares `errors`
as a `ViewErrorBag` on every web request — the engine recognises that exact case and
swaps in its own `$errors` wrapper around the same bag.

## Output is escaped twice (`&amp;lt;` in the page)

`escape_html` is on by default, so don't combine it with manual escaping:

- Drop `|escape` from templates — under auto-escaping the plain form is a no-op anyway,
  and `|escape:'force'`-style variants double-escape.
- Custom plugins that return markup should either build it from escaped parts
  (function-plugin output is *not* auto-escaped) or be documented for `nofilter` use.
- `|json` and `|markdown` handle this themselves — their output renders correctly
  without `nofilter`.

## Raw HTML where you wanted it escaped

Function-plugin output bypasses the `escape_html` pass by design (that's how
`{csrf_field}` can emit an `<input>`). The package's own plugins escape anything
user-coupled ({old}, {lang}, …) — apply the same rule to plugins you write:
`htmlspecialchars()` anything that originates from user input before returning it.

## "unknown modifier" / "tag … disabled by security setting" under a policy

The [Strict policy](security.md) switches modifiers to an allow-list and bans the
state-reaching tags (`{config}`, `{service}`, `{session}`, `{dump}`, `{dd}`). If a
template legitimately needs a modifier that isn't allow-listed, subclass the policy and
append to `$allowed_modifiers`. Also note: switching the `security` config does **not**
invalidate already-compiled templates — `php artisan smarty:clear-compiled` after
changing it.

## Octane / Swoole / RoadRunner notes

Workers are long-lived processes, so anything `static` survives across requests. The
package is built for that, and the guarantees are worth knowing:

- **Block-state frames** ({auth}'s `$user`, {error}'s `$message`) are reset in a
  `finally` around every render, so an exception mid-block can't leak a binding into the
  next request's render.
- **Wrapper objects** are re-assigned per render from the live container, so `$auth`,
  `$request` etc. always reflect the current request, not the worker's first one.
- **Plugin discovery** memoises per process — after a code change to a plugin class, the
  file-mtime fingerprint invalidates the on-disk cache, but reload the workers
  (`octane:reload`) like you would for any code change.

## Smarty's output cache serves stale request data

It shouldn't: every request- or locale-coupled built-in registers nocache (see the
`caching` row in [Configuration](configuration.md)). If a *custom* plugin's output is
being baked into cached pages, register it with `cacheable=false` — for class-backed
plugins, `#[SmartyPlugin(type: '…', name: '…', cacheable: false)]`. Note Smarty only
honours the flag for function and block plugins; a custom *modifier*'s output follows
the cacheability of the expression it appears in.

## A view renders the wrong template with the same filename

Fixed in 0.21.0 — older versions resolved templates by basename, so `dashboard.tpl` and
`admin/dashboard.tpl` could shadow each other. Upgrade, then clear the compile and cache
dirs (file names changed): `php artisan smarty:clear-compiled && php artisan
smarty:clear-cache`.
