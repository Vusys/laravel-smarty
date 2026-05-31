# Artisan commands

Five commands ship for managing Smarty's compile cache, output cache, and class-backed plugin discovery. They share the same Smarty instance the runtime uses, so configuration, plugins, and paths all match what `view()` sees.

## `smarty:optimize`

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

## `smarty:clear-compiled`

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

## `smarty:clear-cache`

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

## `smarty:plugins:cache`

Walks the configured `plugin_namespaces` (plus anything registered via `LaravelSmarty::discoverPluginsIn()`), discovers class-backed plugins, and writes the resolved map to `bootstrap/cache/laravel-smarty-plugins.php`. Run during deploy to skip the filesystem walk on cold starts.

```bash
php artisan smarty:plugins:cache
```

## `smarty:plugins:clear`

Deletes the discovery cache file. Useful when you've added a namespace or moved a plugin class — though the cache fingerprint also auto-invalidates when the configured namespaces or manually-registered classes change.

```bash
php artisan smarty:plugins:clear
```
