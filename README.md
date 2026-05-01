# laravel-smarty

[![Tests](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml/badge.svg)](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-FF2D20?logo=laravel)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg)](phpstan.neon)
[![Rector](https://img.shields.io/badge/Rector-passing-brightgreen.svg)](rector.php)
[![Code Style: Pint](https://img.shields.io/badge/code%20style-Laravel%20Pint-FF2D20.svg?logo=laravel)](https://github.com/laravel/pint)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Replace Blade with [Smarty 5](https://www.smarty.net/) as the default view engine
in a Laravel application.

## Why this exists

Blade is the right answer for most Laravel apps, but a few situations push
teams towards Smarty:

- **Migrating a legacy PHP app into Laravel** where thousands of `.tpl`
  templates already exist and rewriting them all to Blade is not on the table.
- **Designer / non-PHP authors** who already know Smarty's syntax and modifier
  pipeline (`{$var|truncate:80|escape}`).
- **Stricter sandboxing** — Smarty's security policy can lock down what
  templates are allowed to do, which is harder to retrofit on Blade.
- **Per-team preference** for Smarty's tag style and inheritance model.

This package wires Smarty into Laravel's view machinery so you keep using
`view('foo', $data)` from controllers and `view()` returns a `View` instance
that renders Smarty under the hood.

## How it works

- The `.tpl` extension is registered ahead of `.blade.php` on Laravel's view
  finder, so `view('welcome')` resolves `welcome.tpl` first and falls back to
  `welcome.blade.php` if no Smarty template exists. Both engines coexist —
  this is a soft replacement, not a forced rewrite.
- A `SmartyEngine` implements `Illuminate\Contracts\View\Engine` and is
  registered on the `view.engine.resolver` for the `smarty` engine name.
- A `SmartyFactory` builds a configured `Smarty` instance per resolver
  invocation, wired up with the configured compile/cache directories,
  caching settings, and plugin paths.
- A `BridgedSmarty` subclass overrides `doCreateTemplate()` so that every
  sub-template loaded via `{extends}` or `{include}` fires Laravel's
  `creating:` and `composing:` events with a real `Illuminate\View\View`
  instance. This means **view composers and `barryvdh/laravel-debugbar`'s
  view collector see the full template tree** on every render — same surface
  Blade exposes, even when Smarty's compile cache is warm.

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

Smarty resolves before Blade, so a `welcome.tpl` overrides an existing
`welcome.blade.php` for the same view name.

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

Both directories are created automatically via Laravel's
`Filesystem::ensureDirectoryExists()` if missing.

## Artisan commands

Three commands ship for managing Smarty's compile and cache directories.
They share the same Smarty instance the runtime uses, so configuration,
plugins, and paths all match what `view()` sees.

### `smarty:optimize`

Pre-compiles every template found under the configured view paths. Useful
in deploy pipelines to amortise the first-render compile cost.

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

Clears Smarty's rendered output cache (only relevant when `smarty.caching`
is enabled).

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

## Custom modifiers and plugins

Drop a file into a directory listed in `plugins_paths`:

```php
// resources/smarty/plugins/modifier.currency.php
<?php

function smarty_modifier_currency(int|float|null $amount, string $symbol = '£'): string
{
    return $amount === null ? '' : $symbol.number_format((float) $amount, 2);
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
{$post.price|currency}      {* £4.50    *}
{$post.price|currency:"$"}  {* $4.50    *}
```

The same convention applies to `function.<name>.php`, `block.<name>.php`,
etc. — see the [Smarty plugin docs](https://www.smarty.net/docs/en/plugins.tpl).

## Laravel integration

### View composers

`composing:` and `creating:` events fire for every template Smarty loads,
including `{extends}` parents and `{include}` partials, so view composers
work the same as they do for Blade:

```php
View::composer('layouts.main', function ($view) {
    $view->with('user', auth()->user());
});
```

Caveat: data added by a composer to a sub-template's `View` instance is **not**
currently propagated back into Smarty's variable scope — Smarty maintains its
own data store and we only synthesise `View` objects so listeners can observe
the template tree. View composers that *only* observe (logging, metrics,
Debugbar) work today; composers that mutate template data are on the roadmap.

### Debugbar

If `barryvdh/laravel-debugbar` is installed, its **Views** tab will list every
template rendered for the request — entry, layout, and partials — exactly like
it does for Blade.

## Roadmap

The following Blade features are not yet exposed as Smarty equivalents.
Ordered roughly by impact.

### High priority — basic forms and routing

- [x] **`@csrf` equivalent** — Smarty function `{csrf_field}` emitting
      `<input type="hidden" name="_token" value="...">`.
- [x] **`@method('PUT')` equivalent** — `{method_field method="PUT"}` for form
      method spoofing.
- [x] **Route / URL / asset helpers** — `{route name="users.show" id=$user->id}`,
      `{url path="/foo"}`, `{asset path="img.png"}`.
- [x] **Translations** — `{lang key="messages.welcome"}` plus a `|trans`
      modifier for the inline form.
- [x] **`old()`** — `{old field="email" default=$user->email}` for repopulating
      forms after validation failure.
- [x] **Auto-escape by default** — enable `setEscapeHtml(true)` so `{$var}`
      is `e()`'d like Blade's `{{ }}`. Configurable for opt-out via
      `smarty.escape_html`.

### Medium priority — auth, validation, layout

- [x] **`@auth` / `@guest`** — block tags `{auth}…{/auth}` and
      `{guest}…{/guest}`. Optional `guard="api"` parameter.
- [x] **`@can`** — block tag `{can ability="update" model=$post}…{/can}` plus
      `{cannot}` for the inverse.
- [x] **`@error('field')`** — short-circuit access to the first validation
      error: `{error field="email"}<p class="err">{$message}</p>{/error}`.
- [ ] **`@push` / `@stack`** — cross-template accumulation of scripts/styles.
      Smarty's `{capture}` is per-template; stacks aggregate across the
      whole inheritance + include tree.
- [x] **Pagination templates** — ship `views/pagination/*.tpl` and register
      them so `$paginator->links()` works without falling back to Blade.
      Tailwind, Bootstrap 3/4/5 (full + simple) and Semantic UI variants
      are all included.
- [ ] **View composer data flow-through** — propagate composer-injected data
      from sub-template `View` instances back into Smarty's variable scope.

### Low priority — quality of life

- [x] **`@json($data)`** — `|json` modifier delegating to `Js::from()` for safe
      JS embedding. Use with `nofilter` to bypass auto-escape inside `<script>`:
      `var data = {$posts|json nofilter};`.
- [x] **`@inject`** — `{service name="App\\Services\\Metrics" assign="metrics"}`.
- [x] **`@dump` / `@dd`** — wire to Laravel's `dump()` / `dd()` helpers.
      Usage: `{dump value=$user}`, `{dd value=$user}`.

### Architecturally interesting — own milestone

- [ ] **Blade components and slots** — Smarty has no native class-backed
      component system. Two routes worth considering:
  - Document `{include}` + `{block}` as the substitute and stop there.
  - Build a thin component bridge so `<x-foo>` resolves to a Smarty
      template + companion class with slot support. Real work, debatable
      scope; would need its own RFC.

## Development

The package is developed with Orchestra Testbench.

```bash
composer install
vendor/bin/phpunit
```

Tests cover engine rendering, the Smarty-before-Blade extension priority,
the resolver wiring, and `composing:`/`creating:` event firing for parents
and includes.

### Static analysis & code style

Three tools run on every pull request via the `Static analysis & code
style` CI job, and are available locally via composer scripts:

| Command                  | Tool                                              | Purpose                                                                    |
|--------------------------|---------------------------------------------------|----------------------------------------------------------------------------|
| `composer analyse`       | [Larastan](https://github.com/larastan/larastan)  | PHPStan + Laravel rules, currently at level 6 (see `phpstan.neon`).        |
| `composer rector:check`  | [Rector](https://github.com/rectorphp/rector) + [rector-laravel](https://github.com/driftingly/rector-laravel) | Dry-run automated refactors using version-agnostic quality sets only — Laravel level sets are intentionally excluded so we don't rewrite code into a Laravel-13-only shape and break older support. |
| `composer pint:check`    | [Laravel Pint](https://github.com/laravel/pint)   | Default Laravel preset, no `pint.json` overrides.                          |

Apply fixes locally with `composer rector` and `composer pint`. The CI
job runs all three with `--test` / `--dry-run`, so any drift fails the
build.

## License

MIT.
