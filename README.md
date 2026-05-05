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
| `caching`       | `false`                                        | Toggles `Smarty::CACHING_LIFETIME_CURRENT`. |
| `cache_lifetime`| `3600`                                         | Cache lifetime in seconds when `caching` is on. |
| `force_compile` | `false`                                        | Recompile every render. Useful in development. |
| `debugging`     | `false`                                        | Smarty's debug console. |
| `escape_html`   | `true`                                         | Auto-escape `{$var}` outputs through `htmlspecialchars()`, matching Blade's `{{ }}`. Set to `false` to require explicit `\|escape`. |
| `plugins_paths` | `[]`                                           | Extra directories scanned for Smarty plugins. |
| `left_delimiter` / `right_delimiter` | `null`                    | Override Smarty's `{` / `}` delimiters. Useful next to inline JavaScript using the same braces. Leave `null` for defaults. |
| `compile_check` | `true`                                         | Recheck template mtimes on every render. Disable in production for a small per-render win — at the cost of needing an explicit `smarty:clear-compiled` after a deploy. |
| `default_modifiers` | `[]`                                       | Modifiers applied automatically to every `{$var}` output (e.g. `['strip']`). |
| `error_reporting`| `null`                                        | `error_reporting()`-style bitmask Smarty applies while rendering. `null` leaves PHP's level untouched. |

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
            $policy = new \Smarty\Security($smarty);
            $policy->php_modifiers = ['count'];
            $policy->disabled_tags = ['php'];
            $smarty->enableSecurity($policy);

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

## Built-in plugins

The package registers a curated set of Smarty plugins on every render that mirror Blade directives and Laravel helpers, so the common cases work out of the box.

### Auth & authorisation blocks

Block tags that wrap `auth()`, `Gate::allows()`, and friends. Their bodies short-circuit when the predicate is false, matching Blade's `@auth` / `@can` semantics — a `{$user->name}` inside `{auth}` won't blow up on a guest request.

```smarty
{auth}
  Welcome back, {auth()->user()->name|escape}.
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

`{auth}` and `{guest}` accept an optional `guard=` parameter and otherwise default to the application's primary guard. `{can}` / `{cannot}` accept `ability=` and an optional `model=` (passed as the gate's argument). `{canany}` / `{canall}` accept `abilities=[...]` and an optional `model=` — `{canany}` matches Blade's `@canany` (renders if any ability passes); `{canall}` is the equivalent of calling `Gate::check([...], $model)` (renders only when every ability passes).

### Form helpers

```smarty
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
| `{csrf_field}` | `csrf_field()` |
| `{method_field method="PUT"}` | `method_field('PUT')` |
| `{old field="title" default=...}` | `old('title', $default)` |
| `{error field="..."}...{/error}` | `@error('...')` — body renders only when there is a validation error; `$message` is bound inside |

### URLs & assets

| Tag | Equivalent |
|-----|------------|
| `{route name="posts.show" post=$post}` | `route('posts.show', ['post' => $post])` — every named param other than `name=` becomes a route parameter |
| `{url path="/login"}` | `url('/login')` |
| `{asset path="img/logo.svg"}` | `asset('img/logo.svg')` |

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
</head>
```

| Tag | Equivalent |
|-----|------------|
| `{vite entrypoints=[...] build_directory=...}` | Blade's `@vite([...], $buildDirectory)` — `build_directory` is optional |
| `{vite_react_refresh}` | Blade's `@viteReactRefresh` |

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

{if session key="status"}
  <div class="alert">{session key="status"}</div>
{/if}

<article>{$post->body|markdown nofilter}</article>
```

| Tag/modifier | Equivalent |
|--------------|------------|
| `{config key="app.name" default=...}` | `config('app.name', $default)` |
| `{session key="status" default=...}` | `session('status', $default)` |
| `\|markdown` modifier | `Illuminate\Support\Str::markdown($value)` — pair with `nofilter` to keep the rendered HTML, the same way you'd reach for Blade's `{!! !!}` |
| `\|json` modifier | `Js::from($value)` — JSON-encodes for safe JS embedding |
| `{service name="App\\Services\\Foo" assign="foo"}` | `resolve('App\\Services\\Foo')` and assign as `$foo` for the rest of the template |
| `{dump x=$x y=$y}` | `dump($x, $y)` — every named param is dumped |
| `{dd x=$x}` | `dd($x, ...)` — every named param is dumped, then halts |

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

## Artisan commands

Three commands ship for managing Smarty's compile and cache directories. They share the same Smarty instance the runtime uses, so configuration, plugins, and paths all match what `view()` sees.

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
