# Laravel Smarty

Replace Blade with [Smarty 5](https://www.smarty.net/) as the default view engine in a
Laravel application — `view('welcome')` renders `welcome.tpl`, with Blade still available
as a fallback for any view that doesn't have a Smarty template.

```smarty
<h1>Hello, {$name}!</h1>   {* auto-escaped, like Blade's {{ }} *}
```

```php
Route::get('/', fn () => view('welcome', ['name' => 'World']));
```

## Highlights

- **Drop-in**: registers ahead of Blade on the view finder; both engines coexist.
- **Safe by default**: `{$var}` output is HTML-escaped, and the built-in plugins escape
  user-coupled output the way Blade's directives do.
- **Blade-parity plugins**: `{auth}`, `{can}`, `{feature}`, `{env}`, `{old}`, `{checked}`,
  `{route}`, `{vite}`, `|trans`, `|currency`, and friends.
- **Caching-correct**: with Smarty's output cache on, everything request- or
  locale-coupled re-evaluates per render instead of being baked in.
- **Sandboxable**: two shipped security policies for admin-authored and user-submitted
  templates.
- **Laravel-native errors**: exceptions map back to the `.tpl` source line, view
  composers and Debugbar see every sub-template.

## Where to go

| | |
|---|---|
| New here? | [Overview](overview.md) → [Quick start](quick-start.md) |
| Setting up | [Configuration](configuration.md), [Artisan commands](artisan.md) |
| Writing templates | [Built-in plugins](plugins.md), [Wrapper objects](wrapper-objects.md), [Pagination](pagination.md) |
| Extending | [Custom plugins](custom-plugins.md) |
| Locking down | [Security policy](security.md) |
| Something's off | [Troubleshooting](troubleshooting.md) |
| Contributing | [Development](development.md) |

## Requirements

PHP `^8.1` · Laravel `10 / 11 / 12 / 13` · `smarty/smarty ^5.4`

```bash
composer require vusys/laravel-smarty
```
