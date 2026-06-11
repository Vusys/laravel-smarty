# vusys/laravel-smarty

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vusys/laravel-smarty.svg)](https://packagist.org/packages/vusys/laravel-smarty)
[![Total Downloads](https://img.shields.io/packagist/dt/vusys/laravel-smarty.svg)](https://packagist.org/packages/vusys/laravel-smarty)
[![Tests](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml/badge.svg)](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/Vusys/laravel-smarty/graph/badge.svg)](https://codecov.io/gh/Vusys/laravel-smarty)
[![Mutation testing](https://img.shields.io/endpoint?style=flat&url=https://badge-api.stryker-mutator.io/github.com/Vusys/laravel-smarty/master)](https://dashboard.stryker-mutator.io/reports/github.com/Vusys/laravel-smarty/master)
[![tests](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/Vusys/laravel-smarty/badges/tests.json)](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml) [![assertions](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/Vusys/laravel-smarty/badges/assertions.json)](https://github.com/Vusys/laravel-smarty/actions/workflows/tests.yml) [![test LOC](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/Vusys/laravel-smarty/badges/test-ratio.json)](tests/) [![CI matrix](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/Vusys/laravel-smarty/badges/matrix.json)](.github/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-FF2D20?logo=laravel)](composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)
[![Rector](https://img.shields.io/badge/Rector-passing-brightgreen.svg)](rector.php)
[![Code Style: Pint](https://img.shields.io/badge/code%20style-Laravel%20Pint-FF2D20.svg?logo=laravel)](https://github.com/laravel/pint)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Replace Blade with [Smarty 5](https://www.smarty.net/) as the default view engine in a Laravel application.

**📚 Full documentation: [vusys.github.io/laravel-smarty](https://vusys.github.io/laravel-smarty/)**

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

To customise the bundled paginator templates, publish them with:

```bash
php artisan vendor:publish --tag=smarty-pagination-views
```

That copies the `pagination::*` presets (Tailwind, Bootstrap 3/4/5, Semantic UI, and their simple variants) into `resources/views/vendor/pagination/`, where your edits override both the package's bundled `.tpl` and Laravel's framework Blade views. See [docs/pagination.md](docs/pagination.md) for details.

## Quick start

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

`{$var}` output is HTML-escaped automatically (`escape_html` is on by default), matching Blade's `{{ }}` — no `|escape` needed. Use `{$var nofilter}` where you deliberately want raw output.

Render it like any Laravel view:

```php
Route::get('/', fn () => view('welcome', [
    'title' => 'Home',
    'name'  => 'World',
]));
```

Smarty resolves before Blade, so a `welcome.tpl` overrides an existing `welcome.blade.php` for the same view name.

## Documentation

| Topic | What's in it |
|-------|--------------|
| [Overview](docs/overview.md) | Why this package exists; how the Smarty bridge plugs into Laravel's view machinery. |
| [Quick start](docs/quick-start.md) | First template, route, and a `{extends}` inheritance example. |
| [Configuration](docs/configuration.md) | Every `config/smarty.php` key, plus the `SmartyFactory::configure()` hook for advanced wiring. |
| [Security policy](docs/security.md) | The shipped Balanced / Strict policies and when to use each. |
| [Built-in plugins](docs/plugins.md) | `{auth}`, `{can}`, `{feature}`, `{route}`, `{lang}`, `{vite}`, number modifiers, and the rest. |
| [Auto-shared wrapper objects](docs/wrapper-objects.md) | `$auth`, `$request`, `$session`, `$route`, `$errors` and their reserved-key behaviour. |
| [Pagination](docs/pagination.md) | Bundled paginator presets and how to publish them. |
| [Custom plugins](docs/custom-plugins.md) | File-based plugins, class-backed discovery, and the discovery cache. |
| [Artisan commands](docs/artisan.md) | `smarty:optimize`, the clear-* commands, and the plugin discovery cache commands. |
| [Laravel integration](docs/laravel-integration.md) | View composers, Debugbar/Telescope, and `.tpl` source-line error mapping. |
| [Troubleshooting](docs/troubleshooting.md) | Stale compiles after deploys, `ReservedTemplateVariable`, escaping surprises, Octane notes. |
| [Development](docs/development.md) | Running tests, static analysis, code style. |

## License

MIT.
