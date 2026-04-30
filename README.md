# laravel-smarty

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

- PHP `^8.3`
- Laravel `^11 | ^12 | ^13`
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

- [ ] **`@json($data)`** — `|json` modifier delegating to `Js::from()` for safe
      JS embedding.
- [ ] **`@inject`** — `{service name="App\\Services\\Metrics" assign="metrics"}`.
- [ ] **`@dump` / `@dd`** — wire to Laravel's `dump()` / `dd()` helpers.

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

## License

MIT.
