# Artisan commands

Five commands ship for managing Smarty's compile cache, output cache, and class-backed plugin discovery. They share the same Smarty instance the runtime uses, so configuration, plugins, and paths all match what `view()` sees.

A few of the option names show up in multiple commands. They reflect Smarty concepts most apps never explicitly use, but they're available if you need them:

- **`--compile-id`** — Smarty lets you compile the same `.tpl` source into multiple outputs by passing a `$compile_id` argument at render time (e.g. one compiled variant per locale, per theme, etc.). Most apps leave this unset and a single compiled file per template is fine.
- **`--cache-id`** — Same idea, but for the output cache: groups related cached fragments under a string key so you can flush, say, "everything cached on behalf of user 42" in one call.
- **`--expire`** — A wall-clock TTL filter, in seconds. `--expire=86400` means "only clear entries written more than 24 hours ago." Useful for routine cleanup jobs that should leave fresh entries alone.

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

The command continues through the full template list even when individual templates fail to compile — Smarty's per-template trail is forwarded to stdout, so failures are visible but don't abort the run. The exit code is always `0`. If your deploy pipeline needs to fail on any compile error, parse the output (Smarty prefixes failures with the template path) or run a follow-up `view()` smoke test against the offending templates.

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
php artisan smarty:clear-cache --expire=86400        # only drop entries older than a day
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

Deletes the discovery cache file. Rarely needed by hand: the fingerprint auto-invalidates both when the registered namespaces / classes change *and* when any `*.php` file inside a scanned namespace is added, removed, or modified (it's path+mtime based). Reach for this command when you want to force a re-scan that isn't tied to a code change — e.g. after a Composer autoload reshuffle that adds a new PSR-4 root.

```bash
php artisan smarty:plugins:clear
```
