# Overview

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
- The same `doCreateTemplate()` hook injects a `LineTrackingCompiler` onto every Smarty `Template` via reflection, so runtime errors raised inside a `.tpl` body can be walked back to the originating tag — see [Template error source mapping](laravel-integration.md#template-error-source-mapping).
