# Quick start

Create `resources/views/welcome.tpl`:

```smarty
<!doctype html>
<html lang="en">
<head><title>{$title}</title></head>
<body>
  <h1>Hello, {$name}!</h1>
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

### Output is auto-escaped by default

`{$var}` is run through `htmlspecialchars()` automatically — same contract as Blade's `{{ }}` — so the examples above don't need an explicit `|escape`. That behaviour is controlled by `escape_html` (default `true`) in [Configuration](configuration.md).

When you intentionally want raw HTML — Markdown output, a trusted fragment, etc. — chain `nofilter` (or use a modifier whose output is already safe HTML, paired with `nofilter`):

```smarty
<article>{$post->body|markdown nofilter}</article>
```

You can still write `|escape` explicitly; it's a no-op on already-escaped output, just harmless noise.

## Template inheritance

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
      <h2>{$post.title}</h2>
      <p>{$post.body|truncate:140:"…"}</p>
    </article>
  {/foreach}
{/block}
```

View composers (`View::composer('layouts.main', …)`) fire for the parent layout as well as the child, so data attached to the layout via `$view->with(...)` is visible inside both `{block}` bodies and any `{include}`d partials. See [Laravel integration](laravel-integration.md#view-composers) for the contract.
