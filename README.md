# laravel-smarty

[![Tests](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml/badge.svg)](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/Vusys/laravel-smarty/graph/badge.svg)](https://codecov.io/gh/Vusys/laravel-smarty)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-FF2D20?logo=laravel)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)
[![Rector](https://img.shields.io/badge/Rector-passing-brightgreen.svg)](rector.php)
[![Code Style: Pint](https://img.shields.io/badge/code%20style-Laravel%20Pint-FF2D20.svg?logo=laravel)](https://github.com/laravel/pint)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Replace Blade with [Smarty 5](https://www.smarty.net/) as the default view engine in a Laravel application.

## Why this exists

![Top Gear 'but I like this' meme captioned with Twig, Latte, and Smarty](img/likethis.jpg)

Blade is the right answer for most Laravel apps, but a few situations push teams towards Smarty:

- **Migrating a legacy PHP app into Laravel** where thousands of `.tpl` templates already exist and rewriting them all to Blade is not on the table.
- **Designer / non-PHP authors** who already know Smarty's syntax and modifier pipeline (`{$var|truncate:80|escape}`).
- **Stricter sandboxing** — Smarty's security policy can lock down what templates are allowed to do, which is harder to retrofit on Blade.
- **Per-team preference** for Smarty's tag style and inheritance model.

This package wires Smarty into Laravel's view machinery so you keep using `view('foo', $data)` from controllers and `view()` returns a `View` instance that renders Smarty under the hood.

## How it works

- The `.tpl` extension is registered ahead of `.blade.php` on Laravel's view finder, so `view('welcome')` resolves `welcome.tpl` first and falls back to `welcome.blade.php` if no Smarty template exists. Both engines coexist — this is a soft replacement, not a forced rewrite.
- A `SmartyEngine` implements `Illuminate\Contracts\View\Engine` and is registered on the `view.engine.resolver` for the `smarty` engine name.
- A `SmartyFactory` builds a configured `Smarty` instance per resolver invocation, wired up with the configured compile/cache directories, caching settings, and plugin paths.
- A `BridgedSmarty` subclass overrides `doCreateTemplate()` so that every sub-template loaded via `{extends}` or `{include}` fires Laravel's `creating:` and `composing:` events with a real `Illuminate\View\View` instance. This means **view composers and `barryvdh/laravel-debugbar`'s view collector see the full template tree** on every render — same surface Blade exposes, even when Smarty's compile cache is warm.
- The same `doCreateTemplate()` hook injects a `LineTrackingCompiler` onto every Smarty `Template` via reflection, so runtime errors raised inside a `.tpl` body can be walked back to the originating tag — see [Template error source mapping](#template-error-source-mapping).

## Requirements

- PHP `^8.1`
- Laravel `^10 | ^11 | ^12 | ^13`
- `smarty/smarty` `^5.4`

## Installation

```bash
composer require vusys/laravel-smarty
```

The service provider is registered automatically via package discovery.

Publish the config (optional):

```bash
php artisan vendor:publish --tag=smarty-config
```

That writes `config/smarty.php`.

## Quick start

Create `resources/views/welcome.tpl`:

```smarty
<!doctype html>
<html lang="en">
<head><title>{$title|escape}</title></head>
<body>
  <h1>Hello, {$name|escape}!</h1>
</body>
</html>
```

Render it like any Laravel view:

```php
Route::get('/', fn () => view('welcome', [
    'title' => 'Home',
    'name'  => 'World',
]));
```

Smarty resolves before Blade, so a `welcome.tpl` overrides an existing `welcome.blade.php` for the same view name.

### Template inheritance

```smarty
{* resources/views/layouts/main.tpl *}
<!doctype html>
<html>
<head><title>{block name="title"}My App{/block}</title></head>
<body>
  {include file="partials/nav.tpl"}
  <main>{block name="content"}{/block}</main>
</body>
</html>
```

```smarty
{* resources/views/posts.tpl *}
{extends file="layouts/main.tpl"}
{block name="title"}Posts{/block}
{block name="content"}
  {foreach $posts as $post}
    <article>
      <h2>{$post.title|escape}</h2>
      <p>{$post.body|truncate:140:"…"|escape}</p>
    </article>
  {/foreach}
{/block}
```

## Configuration

`config/smarty.php`:

| key             | default                                        | description |
|-----------------|------------------------------------------------|-------------|
| `extension`     | `tpl`                                          | File extension registered as the highest-priority view extension. |
| `compile_path`  | `storage_path('framework/smarty/compile')`     | Where Smarty writes compiled templates. |
| `cache_path`    | `storage_path('framework/smarty/cache')`       | Where Smarty writes its output cache. |
| `caching`       | `false`                                        | Toggles `Smarty::CACHING_LIFETIME_CURRENT`. Built-in request-coupled plugins (`{auth}` / `{guest}` / `{can}` / `{cannot}` / `{canany}` / `{canall}` / `{feature}` / `feature_active` / `{signed_route}` / `{temporary_signed_route}` / `{error}` / `{csrf_field}` / `{csrf_token}` / `{old}` / `{session}` / `{service}` / `{dump}` / `{dd}` / `{vite}` / `{vite_react_refresh}` / `{csp_nonce}` / `{vite_asset}` / `{vite_content}` / `{lang}` / `{lang_choice}`) and the auto-shared wrapper objects (`$auth`, `$request`, `$session`, `$route` — see [Auto-shared wrapper objects](#auto-shared-wrapper-objects)) are registered as non-cacheable, so they still re-evaluate per render on a warm cache. Wrap your own request-coupled tags in `{nocache}…{/nocache}` for the same guarantee. |
| `cache_lifetime`| `3600`                                         | Cache lifetime in seconds when `caching` is on. |
| `force_compile` | `false`                                        | Recompile every render. Useful in development. |
| `debugging`     | `false`                                        | Smarty's debug console. |
| `escape_html`   | `true`                                         | Auto-escape `{$var}` outputs through `htmlspecialchars()`, matching Blade's `{{ }}`. Set to `false` to require explicit `\|escape`. |
| `plugins_paths` | `[]`                                           | Extra directories scanned for Smarty plugins. |
| `left_delimiter` / `right_delimiter` | `null`                    | Override Smarty's `{` / `}` delimiters. Useful next to inline JavaScript using the same braces. Leave `null` for defaults. |
| `compile_check` | `true`                                         | Recheck template mtimes on every render. Disable in production for a small per-render win — at the cost of needing an explicit `smarty:clear-compiled` after a deploy. |
| `default_modifiers` | `[]`                                       | Modifiers applied automatically to every `{$var}` output (e.g. `['strip']`). |
| `error_reporting`| `null`                                        | `error_reporting()`-style bitmask Smarty applies while rendering. `null` leaves PHP's level untouched. |
| `security`      | `null`                                         | Apply a `\Smarty\Security` policy. `null` (no security), `'balanced'`, `'strict'`, or a class-string extending `\Smarty\Security`. See [Security policy](#security-policy). |

Both directories are created automatically via Laravel's `Filesystem::ensureDirectoryExists()` if missing.

### Customising Smarty further

The keys above cover the common cases on purpose — this package is deliberately smaller-surface than wrapping every native Smarty option. For everything else (security policy, custom cache resource, registering your own plugins or filters, swapping the resource handler, tweaking obscure flags), register a configurator from a service provider's `boot()`:

```php
use Smarty\Smarty;
use Vusys\LaravelSmarty\SmartyFactory;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SmartyFactory::configure(function (Smarty $smarty, array $config): void {
            // 1) Anything Smarty exposes a setter for.
            $smarty->setMergeCompiledIncludes(true);
            $smarty->setAutoLiteral(false);

            // 2) Lock templates down with Smarty's security policy.
            //    Or use the shipped presets — see [Security policy](#security-policy).
            $smarty->enableSecurity(
                new \Vusys\LaravelSmarty\Security\BalancedSecurityPolicy($smarty),
            );

            // 3) Register custom plugins next to the built-in ones.
            $smarty->registerPlugin(
                Smarty::PLUGIN_MODIFIER,
                'since',
                fn ($value) => $value === null ? '' : \Illuminate\Support\Carbon::parse($value)->diffForHumans(),
            );

            // 4) Swap the output cache resource for a custom one.
            $smarty->registerCacheResource('redis', new \App\Smarty\RedisCacheResource);
            $smarty->setCachingType('redis');
        });
    }
}
```

The callback fires once per Smarty instance, after the curated config and built-in plugins have been applied — your code has the final say. The second argument is the resolved `smarty.*` config array, so you can branch on environment-specific values without re-reading `config()`.

Why a service-provider hook and not a closure in `config/smarty.php`? Closures aren't serialisable, so they would silently break `php artisan config:cache`. A static `configure()` call from a service provider stays cache-safe.

## Security policy

Smarty templates can do a lot at runtime — raw `{php}` blocks, `{math equation=...}` (which `eval()`s its argument), `{fetch file=...}` (which reads files and URLs), `$_SERVER` / `$_GET` access, arbitrary static-class calls. For dev-authored templates that's fine. For anything where templates come from CMS admins, partners, or end users, those features are footguns.

The package ships two `\Smarty\Security` subclasses you can opt into with one config line:

| value      | class                                                              | use case |
|------------|--------------------------------------------------------------------|----------|
| `null`     | _no policy_                                                         | Default. Trusted, dev-authored templates only. |
| `balanced` | `Vusys\LaravelSmarty\Security\BalancedSecurityPolicy`              | Admin-authored / CMS templates. Blocks `{php}`, `{math}`, super-globals, and arbitrary static-class access. Leaves modifiers and constants alone. |
| `strict`   | `Vusys\LaravelSmarty\Security\StrictSecurityPolicy`                | User-submitted / multi-tenant templates. Inherits Balanced and additionally blocks `{fetch}` / `{eval}` / `{include_php}`, all constants, all stream wrappers, and switches modifiers to an explicit allow-list (Smarty 5's full default modifier set minus `regex_replace` for catastrophic-backtracking DoS, plus this package's own modifiers). |

Enable in `config/smarty.php`:

```php
'security' => 'balanced',
```

Or wire a policy yourself via `SmartyFactory::configure()` if you need non-default tweaks:

```php
SmartyFactory::configure(function (\Smarty\Smarty $smarty) {
    $policy = new \Vusys\LaravelSmarty\Security\StrictSecurityPolicy($smarty);
    $policy->trusted_constants = ['APP_VERSION'];           // allow specific constants
    $policy->allowed_modifiers[] = 'my_custom_modifier';    // append host-app modifiers
    $smarty->enableSecurity($policy);
});
```

**Subclassing.** Both shipped classes set everything as plain public properties, so you can subclass and override any single knob without touching the rest:

```php
class AppSecurityPolicy extends \Vusys\LaravelSmarty\Security\BalancedSecurityPolicy
{
    public $allow_constants = false;             // tighten this one default
    public $trusted_constants = ['APP_VERSION']; // but allow this single constant
}
```

Then point the config at it: `'security' => \App\Smarty\AppSecurityPolicy::class`.

**Custom modifiers under Strict.** `StrictSecurityPolicy::$allowed_modifiers` ships with Smarty's built-in formatting modifiers plus the modifiers this package registers (`currency`, `trans`, `markdown`, etc.). If your app registers its own modifier, append it via subclassing — anything not on the list is rejected at render time with a `\Smarty\Exception`.

**`markdown` produces raw HTML.** The package's `markdown` modifier wraps `Str::markdown()` and emits unescaped tags (`<strong>`, `<a>`, …). The Strict policy's threat model is "trusted data, untrusted template" — it doesn't gate the *data* templates render. If your app passes user-controlled strings into a template that uses `{$x|markdown nofilter}`, that's still a stored-XSS surface. Drop `markdown` from `$allowed_modifiers` in a subclass if your data-trust profile demands it.

**Toggling the policy after templates were already compiled.** Smarty caches compiled templates under `compile_path`. Switching `'security'` from `null` to `'strict'` does not invalidate previously compiled output, so the first render after a switch may still execute a template that was compiled with no policy attached. Run `php artisan smarty:clear-compiled` after changing the setting to be safe.

**Invalid config values.** If the `'security'` key isn't `null`, `'balanced'`, `'strict'`, or a class extending `\Smarty\Security`, the engine throws `InvalidArgumentException` on the first view render. Silent fallback to "no security" would be unsafe — the user assumes they're protected. Non-string values (`true`, an array, etc.) and unknown class names both fail with a descriptive message.

**Out of scope (for now).** A dedicated logging channel for security violations (Laravel's default exception reporter already captures `\Smarty\Exception`); auto-invalidating the compile cache when the policy changes (clear `compile_path` after toggling); and a publishable subclass stub.

## Built-in plugins

The package registers a curated set of Smarty plugins on every render that mirror Blade directives and Laravel helpers, so the common cases work out of the box.

### Auth & authorisation blocks

Block tags that wrap `auth()`, `Gate::allows()`, and friends. Their bodies short-circuit when the predicate is false, matching Blade's `@auth` / `@can` semantics — a `{$user->name}` inside `{auth}` won't blow up on a guest request.

```smarty
{auth}
  Welcome back, {$user->name|escape}.
{/auth}

{guest}
  Please <a href="{route name='login'}">sign in</a>.
{/guest}

{can ability="update" model=$post}
  <a href="...">Edit</a>
{/can}

{cannot ability="delete" model=$post}
  (read-only)
{/cannot}

{canany abilities=['update', 'delete'] model=$post}
  <a href="...">Manage</a>
{/canany}

{canall abilities=['publish', 'feature'] model=$post}
  <button>Publish &amp; feature</button>
{/canall}

{auth guard="api"}
  API user.
{/auth}
```

`{auth}` and `{guest}` accept an optional `guard=` parameter and otherwise default to the application's primary guard. Inside `{auth}` the authenticated user is bound as `$user` for the duration of the block (any outer `$user` is restored on exit), so you can write `{$user->name|escape}` without passing the user via view data. `{can}` / `{cannot}` accept `ability=` and an optional `model=` (passed as the gate's argument). `{canany}` / `{canall}` accept `abilities=[...]` and an optional `model=` — `{canany}` matches Blade's `@canany` (renders if any ability passes); `{canall}` is the equivalent of calling `Gate::check([...], $model)` (renders only when every ability passes).

Both multi-ability blocks accept `inverse=true` for the negative arm — `{canany inverse=true}` renders when *none* of the abilities pass, `{canall inverse=true}` renders when *any* of them are missing. Empty `abilities=[]` fails closed in both arms (an accidental empty list never authorizes). For an `{else}`-style layout in a single decision, drop into `{if}` with the wrapper methods on `$auth`:

```smarty
{if $auth?->canAny(['update', 'delete'], $post)}
  <a href="...">Manage</a>
{else}
  (no permissions)
{/if}
```

`$auth->canAny(array $abilities, mixed $arguments = [])` and `$auth->canAll(array $abilities, mixed $arguments = [])` mirror the blocks (and apply the same fail-closed posture for an empty list). Use `?->` to keep guest renders safe — `$auth` is null for unauthenticated requests.

### Pennant feature flags

Block tag for `Laravel\Pennant\Feature` — body short-circuits when the flag is off, matching Blade's `@feature`. Requires the optional `laravel/pennant` package; the tag silently no-ops when Pennant isn't installed.

```smarty
{feature name="new-dashboard"}
  <a href="{route name='dashboard.v2'}">Try the new dashboard</a>
{/feature}

{feature name="beta-export" for=$auth->user}
  <button>Export</button>
{/feature}
```

`name=` is the flag identifier. `for=` (optional) scopes the check to a given subject — typically `$auth->user`, but anything Pennant accepts as a scope (a model, a string, a `Scope` instance) works. Without `for=`, Pennant uses its default scope.

Pass `inverse=true` to render the body when the flag is *inactive* — useful for the "show legacy variant when flag is off" half of an A/B layout:

```smarty
{feature name="compact-composer" for=$auth->user}…compact composer…{/feature}
{feature name="compact-composer" for=$auth->user inverse=true}…wide composer…{/feature}
```

For an `{else}`-style layout in a single decision, use the `feature_active(...)` modifier inside `{if}`:

```smarty
{if feature_active('compact-composer', $auth->user)}
  …compact composer…
{else}
  …wide composer…
{/if}
```

`feature_active($name, $for = null)` returns a bool. The optional second argument is the scope subject (same semantics as the block's `for=`).

Scoped checks (`for=` or the modifier's second argument) need an explicit `{if $auth}` guard in templates that may render for guests, because Pennant's `for(null)` is undefined.

### Form helpers

```smarty
<meta name="csrf-token" content="{csrf_token}">

<form method="post" action="{route name='posts.update' post=$post->id}">
  {csrf_field}
  {method_field method="PUT"}

  <input name="title" value="{old field='title' default=$post->title|default:''}">

  {error field="title"}
    <p class="error">{$message|escape}</p>
  {/error}
</form>
```

Inside `{error}` the validation message is bound as `$message` for the duration of the block, restored on exit.

| Tag | Equivalent |
|-----|------------|
| `{csrf_field}` | `csrf_field()` — full hidden input |
| `{csrf_token}` | `csrf_token()` — raw token, e.g. for `<meta>` tags or AJAX headers |
| `{method_field method="PUT"}` | `method_field('PUT')` |
| `{old field="title" default=...}` | `old('title', $default)` |
| `{error field="..."}...{/error}` | `@error('...')` — body renders only when there is a validation error; `$message` is bound inside |

### URLs & assets

| Tag | Equivalent |
|-----|------------|
| `{route name="posts.show" post=$post}` | `route('posts.show', ['post' => $post])` — every named param other than `name=` becomes a route parameter |
| `{url path="/login"}` | `url('/login')` |
| `{asset path="img/logo.svg"}` | `asset('img/logo.svg')` |
| `{signed_route name="unsubscribe" user=$user->id}` | `URL::signedRoute('unsubscribe', ['user' => $user->id])` — same param convention as `{route}` |
| `{temporary_signed_route name="download" expiration=3600 file=$file->id}` | `URL::temporarySignedRoute('download', 3600, ['file' => $file->id])` — `expiration=` accepts `int` seconds or any `DateTimeInterface` |

Both signed-URL helpers are non-cacheable: a baked signature would either ship a stale URL on warm renders or, for the temporary variant, an already-expired link.

### Translation

```smarty
<h1>{lang key="welcome" name=$user->name}</h1>
<p>{"errors.required"|trans}</p>

<p>{lang_choice key="messages.apples" count=$count}</p>
<p>{"messages.apples"|trans_choice:$count}</p>
```

| Tag/modifier | Equivalent |
|--------------|------------|
| `{lang key="..." foo=... bar=...}` | `__('...', ['foo' => ..., 'bar' => ...])` — every named param other than `key=` becomes a replacement |
| `\|trans` modifier | `__($key, $replace = [])` |
| `{lang_choice key="..." count=$n foo=...}` | `trans_choice('...', $n, ['foo' => ...])` — every named param other than `key=` and `count=` becomes a replacement |
| `\|trans_choice` modifier | `trans_choice($key, $count, $replace = [])` |

### Vite

```smarty
<head>
  {vite_react_refresh}
  {vite entrypoints=['resources/js/app.js']}
  <script nonce="{csp_nonce}">window.__APP_CONFIG = {$config|json};</script>
</head>

{* Versioned URL for an asset that isn't part of an entrypoint *}
<img src="{vite_asset path='resources/img/logo.svg'}" alt="">

{* Inline SVG sprite (output is raw — function plugins aren't auto-escaped) *}
{vite_content path="resources/img/sprite.svg"}
```

| Tag | Equivalent |
|-----|------------|
| `{vite entrypoints=[...] build_directory=...}` | Blade's `@vite([...], $buildDirectory)` — `build_directory` is optional |
| `{vite_react_refresh}` | Blade's `@viteReactRefresh` |
| `{csp_nonce}` | `Vite::cspNonce()` — the per-request CSP nonce, empty string when none has been set |
| `{vite_asset path="..." build_directory="..."}` | `Vite::asset($path, $buildDirectory)` — single versioned URL for an asset not declared as an entrypoint |
| `{vite_content path="..." build_directory="..."}` | `Vite::content($path, $buildDirectory)` — file contents (e.g. for inline SVG sprites under hashed builds) |

`{csp_nonce}`, `{vite_asset}`, and `{vite_content}` are all non-cacheable — the nonce changes per request, and asset URLs / contents change between hot mode and a built deployment.

### Conditional attributes

```smarty
<button class="{class array=['btn' => true, 'btn-primary' => $isPrimary, 'btn-disabled' => !$isActive]}">
<div style="{style array=['color: red' => $hasError, 'font-weight: bold' => $emphasised]}">
```

| Tag | Equivalent |
|-----|------------|
| `{class array=[...]}` | Blade's `@class([...])` — delegates to `Illuminate\Support\Arr::toCssClasses()`, the same helper Blade uses |
| `{style array=[...]}` | Blade's `@style([...])` — delegates to `Illuminate\Support\Arr::toCssStyles()` |

### Number formatting

Wraps `Illuminate\Support\Number` (Laravel 11+) so locale-aware currency, byte sizes, percentages, and abbreviated counts work as Smarty modifiers. On Laravel 10 these modifiers don't register; Smarty's native `number_format` continues to work.

```smarty
{$total|currency:'GBP'}            {* £1,234.56 *}
{$bytes|file_size}                 {* 1.46 KB    *}
{$bytes|file_size:1}               {* 1.5 KB     *}
{$share|percentage:1}              {* 12.3%      *}
{$views|abbreviate}                {* 1K         *}
{$count|number_for_humans:1}       {* 1.5 thousand *}
```

| Modifier | Equivalent |
|----------|------------|
| `\|currency:$in:$locale:$precision` | `Number::currency($value, $in, $locale, $precision)` |
| `\|file_size:$precision:$maxPrecision` | `Number::fileSize($bytes, $precision, $maxPrecision)` |
| `\|percentage:$precision:$maxPrecision:$locale` | `Number::percentage($value, $precision, $maxPrecision, $locale)` |
| `\|abbreviate:$precision:$maxPrecision` | `Number::abbreviate($value, $precision, $maxPrecision)` |
| `\|number_for_humans:$precision:$maxPrecision:$abbreviate` | `Number::forHumans($value, $precision, $maxPrecision, $abbreviate)` |

### Misc helpers

```smarty
<title>{config key="app.name" default="My App"}</title>

{if $session->has('status')}
  <div class="alert">{$session->status}</div>
{/if}

<article>{$post->body|markdown nofilter}</article>
```

`$session` is one of four auto-shared wrapper objects — see [Auto-shared wrapper objects](#auto-shared-wrapper-objects) for the full surface (`$auth`, `$request`, `$session`, `$route`).

| Tag/modifier | Equivalent |
|--------------|------------|
| `{config key="app.name" default=...}` | `config('app.name', $default)` |
| `{session key="status" default=...}` | `session('status', $default)` |
| `{session key="status" assign="status"}` | `$status = session('status')` (assigns instead of printing) |
| `$session->status` (auto-shared, see [Auto-shared wrapper objects](#auto-shared-wrapper-objects)) | `session('status')` |
| `\|markdown` modifier | `Illuminate\Support\Str::markdown($value)` — pair with `nofilter` to keep the rendered HTML, the same way you'd reach for Blade's `{!! !!}` |
| `\|json` modifier | `Js::from($value)` — JSON-encodes for safe JS embedding |
| `{service name="App\\Services\\Foo" assign="foo"}` | `resolve('App\\Services\\Foo')` and assign as `$foo` for the rest of the template |
| `{dump x=$x y=$y}` | `dump($x, $y)` — every named param is dumped |
| `{dd x=$x}` | `dd($x, ...)` — every named param is dumped, then halts |

## Auto-shared wrapper objects

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

### Reserved names

`auth`, `request`, `session`, `route`, and `errors` are reserved view-data keys. Passing one of them via `view('foo', ['auth' => …])` raises `Vusys\LaravelSmarty\Exceptions\ReservedTemplateVariable` rather than silently letting your data win. Rename the colliding view-data key.

`$errors` has one carve-out: Laravel's stock `Illuminate\View\Middleware\ShareErrorsFromSession` middleware (in the default `web` group) calls `View::share('errors', …)` on every request, so an `Illuminate\Support\ViewErrorBag` is always present in the gathered view data. The engine silently drops that framework-injected share and lets the package's `$errors` wrapper take over — both paths surface the same underlying `ViewErrorBag`, so templates render identically. An `errors` key with any other value type still throws.

### `$auth` is null when no user is authenticated — by design

Outside an `{auth}` block or `{if $auth}` guard, `{$auth->user->name}` raises `ErrorException: Attempt to read property "user" on null`. That's deliberate: silently rendering an empty string on guest is exactly the "I forgot the guest case" class of bug we want to surface during development. PHP 8.4 demotes "read property on null" to a warning, but Laravel's default error handler converts warnings to `ErrorException`, so the loud failure holds out of the box. Lower `smarty.error_reporting` if you want quieter output (and accept the trade-off).

### `$auth->id` type caveat

`$auth->id` is typed `mixed` because Laravel allows custom identifier types (typically `int|string`, occasionally UUID/object). The strict comparison in `{if $auth->id === $post->user_id}` — the canonical pattern — will quietly return false if the two sides differ in type (e.g. `int(42)` vs `string('42')`). If you query data from sources that don't preserve types (some JSON payloads, untyped session storage), cast on the way in or compare loosely.

### Outside HTTP context (mail, queue, console)

The wrappers are still auto-assigned but reflect Laravel's synthetic state: `$auth` is `null`, `$request->routeIs(…)` returns false, `$session->token()` is `null`, `$route->to(…)` still works (URL generation doesn't need a request). Templates that render in mail/queue contexts should treat all five as if they were guest/empty.

`$session` and `$errors` also tolerate apps that don't bind a session store at all (stateless API workers, queue-only processes that strip session middleware, etc.) — `has()`, `get()`, `flashedKeys()`, `any()`, `first()` return false/default/empty rather than raising. The wrappers themselves are always non-null; callers don't need to guard them.

## Pagination

Laravel's paginator integrates without extra wiring. The package ships Smarty ports of every `pagination::*` template Laravel includes:

```php
public function index(Request $request)
{
    return view('posts', [
        'posts' => Post::query()->paginate(15),
    ]);
}
```

```smarty
{foreach $posts as $post}
  <article>{$post->title|escape}</article>
{/foreach}

{$posts->links()}                            {* default tailwind *}
{$posts->links('pagination::bootstrap-5')}   {* pick another preset *}
```

Bundled presets: `pagination::tailwind` (default), `pagination::simple-tailwind`, `pagination::bootstrap-5`, `pagination::simple-bootstrap-5`, `pagination::bootstrap-4`, `pagination::simple-bootstrap-4`, `pagination::bootstrap-3`, `pagination::simple-bootstrap-3`, `pagination::semantic-ui`.

The package's `.tpl` versions take priority over Laravel's framework Blade pagination views, so `$paginator->links('pagination::bootstrap-5')` resolves to a Smarty template — not the framework's `bootstrap-5.blade.php`. To customise, publish them and edit in place:

```bash
php artisan vendor:publish --tag=smarty-pagination-views
```

Anything you publish under `resources/views/vendor/pagination/` wins over both the package's bundled `.tpl` and Laravel's bundled `.blade.php` for the matching preset name.

## Custom modifiers and plugins

Drop a file into a directory listed in `plugins_paths`:

```php
// resources/smarty/plugins/modifier.since.php
<?php

use Illuminate\Support\Carbon;

function smarty_modifier_since(mixed $value): string
{
    return $value === null ? '' : Carbon::parse($value)->diffForHumans();
}
```

```php
// config/smarty.php
'plugins_paths' => [
    resource_path('smarty/plugins'),
],
```

Use it in any template:

```smarty
Posted {$post->created_at|since}            {* Posted 3 hours ago *}
Updated {$post->updated_at|since|escape}    {* Updated 2 minutes ago *}
```

The same convention applies to `function.<name>.php`, `block.<name>.php`, etc. — see the [Smarty plugin docs](https://www.smarty.net/docs/en/plugins.tpl).

### Class-backed plugins

Two **additional**, optional registration styles let you write plugins as classes instead of file-scoped functions. Both are wired up alongside `plugins_paths` — pick whichever ergonomics suit the team, or mix freely. Discovery is namespace-driven, so plugins from a third-party package and host-app plugins both land on the same Smarty instance without manual stitching.

#### Convention by classname suffix

Drop a class under one of the configured `plugin_namespaces` and end its name with `Modifier`, `Function`, or `Block`. The package picks it up on first render and registers an instance through the container — so the class can declare constructor dependencies and they'll be auto-resolved.

```php
// app/Smarty/Plugins/SinceModifier.php
namespace App\Smarty\Plugins;

use Illuminate\Support\Carbon;

class SinceModifier
{
    public function __invoke(mixed $value): string
    {
        return $value === null ? '' : Carbon::parse($value)->diffForHumans();
    }
}
```

```smarty
Posted {$post->created_at|since}    {* Posted 3 hours ago *}
```

The tag/modifier name defaults to the classname with the suffix stripped and snake-cased (`SinceModifier` → `since`, `CspNonceFunction` → `csp_nonce`, `MultiWordBlock` → `multi_word`). Override by declaring a `public string $name = '...'` property on the class.

#### Attribute-tagged

Wear a `#[SmartyPlugin]` attribute on any class with `__invoke()` to opt out of the suffix convention — useful for packages that want IDE-discoverable, type-checkable plugin declarations, or for keeping a domain-shaped classname.

```php
use Vusys\LaravelSmarty\Attributes\SmartyPlugin;

#[SmartyPlugin(type: 'modifier', name: 'since')]
final class TimeAgo
{
    public function __invoke(mixed $value): string
    {
        return $value === null ? '' : Carbon::parse($value)->diffForHumans();
    }
}
```

A class carrying the attribute is **never** also matched by the suffix convention — the attribute wins outright, so renaming the class won't double-register the plugin.

#### Configuring discovery

```php
// config/smarty.php
'plugin_namespaces' => [
    'App\\Smarty\\Plugins',          // default
    'App\\Billing\\Smarty',          // mix as many as you like
],
```

Set to `[]` to disable namespace discovery entirely (the manual APIs below still work).

Third-party packages can register their own discovery namespace from a service provider's `boot()`, without forcing the host app to edit config:

```php
use Vusys\LaravelSmarty\LaravelSmarty;

public function boot(): void
{
    LaravelSmarty::discoverPluginsIn('Vendor\\BillingPack\\Smarty');
}
```

Or register a single class regardless of namespace — useful for one-offs, test fixtures, or classes you'd rather not move:

```php
LaravelSmarty::registerPluginClass(App\Reports\TimeAgo::class);
```

The class still has to either carry `#[SmartyPlugin]` or end in a recognised suffix; anything else throws.

#### Discovery cache

Discovery walks the configured namespaces using Composer's PSR-4 prefix map. The result is cached as a PHP file under `bootstrap/cache/laravel-smarty-plugins.php` — production renders pay zero filesystem-walk cost. The cache fingerprint is derived from the configured + programmatically-registered namespaces (and any manually-registered classes), so adding a namespace invalidates the cache automatically.

```bash
php artisan smarty:plugins:cache    # discover and cache (run during deploy)
php artisan smarty:plugins:clear    # delete the cache file
```

#### Plugin signatures

Class plugins follow Smarty's native plugin signatures, so an `__invoke` method is all you need:

| Type | `__invoke()` signature |
|------|------------------------|
| `modifier` | `__invoke(mixed $value, ...$extraArgs): mixed` — chainable like any other modifier. |
| `function` | `__invoke(array $params): string` — params come straight from `{tag key=value …}`. |
| `block` | `__invoke(array $params, ?string $content, \Smarty\Template $template, bool &$repeat): string` — `$content` is `null` on open, the body string on close. |

#### Collision behaviour

Two classes that resolve to the same `(type, name)` pair (e.g. a convention-discovered `SinceModifier` and a manually-registered class with `public string $name = 'since'`) throw `PluginRegistrationException` at first render. We'd rather fail loud than silently shadow — the bug a "last-wins" rule invites is exactly the one this throws against.

A class plugin and a `function.<name>.php` file plugin sharing a name is allowed: Smarty looks up registered plugins first, so the class plugin shadows the file plugin. That's the same behaviour Smarty itself applies to any registered plugin and is documented in the [Smarty plugin docs](https://www.smarty.net/docs/en/plugins.tpl).

## Artisan commands

Five commands ship for managing Smarty's compile cache, output cache, and class-backed plugin discovery. They share the same Smarty instance the runtime uses, so configuration, plugins, and paths all match what `view()` sees.

### `smarty:optimize`

Pre-compiles every template found under the configured view paths. Useful in deploy pipelines to amortise the first-render compile cost.

```bash
php artisan smarty:optimize
php artisan smarty:optimize --extension=tpl
php artisan smarty:optimize --force          # recompile even if up to date
```

| Option        | Description |
|---------------|-------------|
| `--extension` | Template extension to scan. Defaults to `smarty.extension`. |
| `--force`     | Recompile even when compiled output is current. |

### `smarty:clear-compiled`

Removes compiled `.tpl.php` files from `smarty.compile_path`.

```bash
php artisan smarty:clear-compiled
php artisan smarty:clear-compiled --file=welcome.tpl
```

| Option         | Description |
|----------------|-------------|
| `--file`       | Clear compiled output for one specific template. |
| `--compile-id` | Restrict to a specific `compile_id`. |
| `--expire`     | Only clear entries older than N seconds. |

### `smarty:clear-cache`

Clears Smarty's rendered output cache (only relevant when `smarty.caching` is enabled).

```bash
php artisan smarty:clear-cache
php artisan smarty:clear-cache --file=welcome.tpl --cache-id=user.42
```

| Option         | Description |
|----------------|-------------|
| `--file`       | Clear cache for one specific template. |
| `--cache-id`   | Restrict to a `cache_id` group. |
| `--compile-id` | Restrict to a `compile_id`. |
| `--expire`     | Only clear entries older than N seconds. |

### `smarty:plugins:cache`

Walks the configured `plugin_namespaces` (plus anything registered via `LaravelSmarty::discoverPluginsIn()`), discovers class-backed plugins, and writes the resolved map to `bootstrap/cache/laravel-smarty-plugins.php`. Run during deploy to skip the filesystem walk on cold starts.

```bash
php artisan smarty:plugins:cache
```

### `smarty:plugins:clear`

Deletes the discovery cache file. Useful when you've added a namespace or moved a plugin class — though the cache fingerprint also auto-invalidates when the configured namespaces or manually-registered classes change.

```bash
php artisan smarty:plugins:clear
```

## Laravel integration

### View composers

`composing:` and `creating:` events fire for every template Smarty loads, including `{extends}` parents and `{include}` partials, so view composers work the same as they do for Blade:

```php
View::composer('layouts.main', function ($view) {
    $view->with('user', auth()->user());
});
```

Caveat: data added by a composer to a sub-template's `View` instance is **not** currently propagated back into Smarty's variable scope — Smarty maintains its own data store and we only synthesise `View` objects so listeners can observe the template tree. View composers that *only* observe (logging, metrics, Debugbar) work today; composers that mutate template data are a known limitation.

### Debug tooling

`creating:` and `composing:` view events fire for every template Smarty loads — entries, `{extends}` parents, and `{include}` partials — so anything in the Laravel ecosystem that listens to those events sees the full render tree. Debugbar's **Views** tab, Telescope's **Views** watcher, and any other tool that hooks Laravel's view events should work without extra wiring, the same way they do for Blade.

### Template error source mapping

A runtime error inside a `.tpl` body — say `{$user->getAuthIdentifier()}` when `$user` is null — would naturally land on Smarty's compiled `<hash>_<file>.tpl.php` file under `storage/framework/smarty/compile/`, with no obvious link back to the template you actually wrote. This package rewrites that automatically.

- A custom compiler (`Debug\LineTrackingCompiler`) emits `/*__SLM:N*/` and `/*__SLF:/abs/path*/` markers into the compiled output during compilation. Installed via reflection at `Template` instantiation time, no vendor patching.
- `Debug\SourceMap` walks back from the compiled-file frame to the closest preceding marker.
- On Laravel 11+, `Debug\SmartyExceptionMapper` extends the framework's `BladeMapper` and is bound in its place, so the exception page rewrites every `.tpl.php` trace frame to the `.tpl` source — same treatment Blade enjoys for `.blade.php`.
- `SmartyEngine::remapException()` walks the full `getPrevious()` chain so errors raised inside a `{capture}` body still surface the user's real exception, not Smarty's `Not matching {capture}{/capture}` rethrow wrapper.

The mapping covers `{block}` bodies of `{extends}` children, `{include}`d partials (including `inline`), `{function}` bodies (both `{call}` and short-tag invocations), `{capture}` bodies, `{if}` condition expressions, and `Smarty\CompilerException`s raised at compile time. Laravel 10 has no `BladeMapper` to extend, so the trace-frame rewrite no-ops there — error messages still carry a `(View: /path/to/source.tpl)` suffix and `CompilerException` source paths/lines are preserved.

## Development

The package is developed with Orchestra Testbench.

```bash
composer install
vendor/bin/phpunit
```

Tests cover engine rendering, the Smarty-before-Blade extension priority, the resolver wiring, `composing:`/`creating:` event firing for parents and includes, the built-in plugins, paginator integration, and end-to-end `.tpl` source-line attribution for runtime and compile errors.

### Static analysis & code style

Three tools run on every pull request via the `Static analysis & code style` CI job, and are available locally via composer scripts:

| Command                  | Tool                                              | Purpose                                                                    |
|--------------------------|---------------------------------------------------|----------------------------------------------------------------------------|
| `composer analyse`       | [Larastan](https://github.com/larastan/larastan)  | PHPStan + Laravel rules, currently at level 9 (see `phpstan.neon`).        |
| `composer rector:check`  | [Rector](https://github.com/rectorphp/rector) + [rector-laravel](https://github.com/driftingly/rector-laravel) | Dry-run automated refactors using version-agnostic quality sets only — Laravel level sets are intentionally excluded so we don't rewrite code into a Laravel-13-only shape and break older support. |
| `composer pint:check`    | [Laravel Pint](https://github.com/laravel/pint)   | Default Laravel preset, no `pint.json` overrides.                          |

Apply fixes locally with `composer rector` and `composer pint`. The CI job runs all three with `--test` / `--dry-run`, so any drift fails the build.

## License

MIT.
