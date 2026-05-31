# Auto-shared wrapper objects

Plugin tags like `{route name="…"}` or `{session key="…"}` are designed for output position — they emit straight to the template body and can't be used as a value (an `{include}` parameter, an `{if}` operand, an attribute expression). To plug that gap the package auto-shares five read-only wrapper objects on every render:

| Variable | Wraps | Public surface |
|----------|-------|----------------|
| `$auth` (or `null` when no user is authenticated) | `Auth::guard()` | `id`, `user`, `is(?User)`, `can($ability, $arguments = [])`, `canAny(array $abilities, $arguments = [])`, `canAll(array $abilities, $arguments = [])`, `guard($name)`. Use `{if $auth}` for the truthiness check. |
| `$request` | `Illuminate\Http\Request` (read-only) | `routeIs(...$patterns)`, `route($param, $default = null)`, `is(...$patterns)`, `input($key, $default)`, `fullUrl()`, `path()` |
| `$session` | `Illuminate\Session\Store` (read-only) | `__get($key)`, `has($key)`, `get($key, $default)`, `token()`, `flashedKeys()` |
| `$route` | `UrlGenerator` | `to($name, $params)`, `path($name, $params)`, `asset($path)`, `url($path)` |
| `$errors` | `Illuminate\Support\ViewErrorBag` (read-only) | `any()`, `has($key)`, `count()`, `all($format = null)`, `first($key, $format = null)`, `get($key, $format = null)`, `getBag($name)`. Always non-null even outside session contexts. |

```smarty
{* Active nav state without controller plumbing *}
<a class="{class array=['nav-item' => true, 'is-active' => $request->routeIs('feed.*')]}" href="{$route->to('feed.index')}">Home</a>

{* Per-element auth checks (the {auth} block would shadow $user) *}
{if $auth && $auth->id !== $post->user_id}
  <button>Follow</button>
{/if}

{* Reusable partial — pass the URL as an include parameter *}
{include file="partials/composer.tpl" action_url=$route->to('posts.replies.store', ['post' => $post->id])}

{* Flash messages — has() works because $session is a real object, not an array *}
{if $session->has('status')}
  <div class="notification is-success">{$session->status}</div>
{/if}

{* Validation errors — iterate the whole bag or pull a single field *}
{if $errors->any()}
  <ul class="errors">
    {foreach $errors->all() as $message}
      <li>{$message|escape}</li>
    {/foreach}
  </ul>
{/if}

{if $errors->has('email')}
  <p class="error">{$errors->first('email')|escape}</p>
{/if}

{* Named bag (e.g. login form on a registration page) *}
{if $errors->getBag('login')->any()}
  <p>Login failed.</p>
{/if}
```

`{$var}` is auto-escaped under the package's default `escape_html=true` config — that applies to wrapper output too. No explicit `|escape` needed.

## Reserved names

`auth`, `request`, `session`, `route`, and `errors` are reserved view-data keys. Passing one of them via `view('foo', ['auth' => …])` raises `Vusys\LaravelSmarty\Exceptions\ReservedTemplateVariable` rather than silently letting your data win. Rename the colliding view-data key.

`$errors` has one carve-out: Laravel's stock `Illuminate\View\Middleware\ShareErrorsFromSession` middleware (in the default `web` group) calls `View::share('errors', …)` on every request, so an `Illuminate\Support\ViewErrorBag` is always present in the gathered view data. The engine silently drops that framework-injected share and lets the package's `$errors` wrapper take over — both paths surface the same underlying `ViewErrorBag`, so templates render identically. An `errors` key with any other value type still throws.

## `$auth` is null when no user is authenticated — by design

Outside an `{auth}` block or `{if $auth}` guard, `{$auth->user->name}` raises `ErrorException: Attempt to read property "user" on null`. That's deliberate: silently rendering an empty string on guest is exactly the "I forgot the guest case" class of bug we want to surface during development. PHP 8.4 demotes "read property on null" to a warning, but Laravel's default error handler converts warnings to `ErrorException`, so the loud failure holds out of the box. Lower `smarty.error_reporting` if you want quieter output (and accept the trade-off).

## `$auth->id` type caveat

`$auth->id` is typed `mixed` because Laravel allows custom identifier types (typically `int|string`, occasionally UUID/object). The strict comparison in `{if $auth->id === $post->user_id}` — the canonical pattern — will quietly return false if the two sides differ in type (e.g. `int(42)` vs `string('42')`). If you query data from sources that don't preserve types (some JSON payloads, untyped session storage), cast on the way in or compare loosely.

## Outside HTTP context (mail, queue, console)

The wrappers are still auto-assigned but reflect Laravel's synthetic state: `$auth` is `null`, `$request->routeIs(…)` returns false, `$session->token()` is `null`, `$route->to(…)` still works (URL generation doesn't need a request). Templates that render in mail/queue contexts should treat all five as if they were guest/empty.

`$session` and `$errors` also tolerate apps that don't bind a session store at all (stateless API workers, queue-only processes that strip session middleware, etc.) — `has()`, `get()`, `flashedKeys()`, `any()`, `first()` return false/default/empty rather than raising. The wrappers themselves are always non-null; callers don't need to guard them.
