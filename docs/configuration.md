# Configuration

`config/smarty.php`:

| key             | default                                        | description |
|-----------------|------------------------------------------------|-------------|
| `extension`     | `tpl`                                          | File extension registered as the highest-priority view extension. |
| `compile_path`  | `storage_path('framework/smarty/compile')`     | Where Smarty writes compiled templates. |
| `cache_path`    | `storage_path('framework/smarty/cache')`       | Where Smarty writes its output cache. |
| `caching`       | `false`                                        | Toggles `Smarty::CACHING_LIFETIME_CURRENT`. Everything request- or locale-coupled re-evaluates per render on a warm cache: the auth/gate/feature blocks, `{env}` / `{production}`, the form, session, Vite and debug tags, the URL tags (`{route}` / `{url}` / `{asset}` and the signed variants), `{lang}` / `{lang_choice}` plus the `trans` / `trans_choice` and `Number` modifiers, and the five auto-shared wrapper objects (`$auth`, `$request`, `$session`, `$route`, `$errors` — see [Auto-shared wrapper objects](wrapper-objects.md)). Wrap your own request-coupled tags in `{nocache}…{/nocache}`, or declare class-backed plugins with `#[SmartyPlugin(cacheable: false)]`, for the same guarantee. |
| `cache_lifetime`| `3600`                                         | Cache lifetime in seconds when `caching` is on. |
| `force_compile` | `false`                                        | Recompile every render. Useful in development. |
| `debugging`     | `false`                                        | Smarty's debug console. |
| `escape_html`   | `true`                                         | Auto-escape `{$var}` outputs through `htmlspecialchars()`, matching Blade's `{{ }}`. Set to `false` to require explicit `\|escape`. |
| `plugins_paths` | `[]`                                           | Extra directories scanned for file-based Smarty plugins (`function.<name>.php` convention). |
| `plugin_namespaces` | `['App\\Smarty\\Plugins']`                 | PSR-4 namespaces scanned for class-backed plugins (`#[SmartyPlugin]` attribute or `*Modifier`/`*Function`/`*Block` suffix). Empty array disables namespace discovery. See [Custom plugins](custom-plugins.md). |
| `left_delimiter` / `right_delimiter` | `null`                    | Override Smarty's `{` / `}` delimiters. Useful next to inline JavaScript using the same braces. Leave `null` for defaults. |
| `compile_check` | `true`                                         | Recheck template mtimes on every render. Disable in production for a small per-render win — at the cost of needing an explicit `smarty:clear-compiled` after a deploy. |
| `default_modifiers` | `[]`                                       | Modifiers applied automatically to every `{$var}` output (e.g. `['strip']`). |
| `error_reporting`| `null`                                        | `error_reporting()`-style bitmask Smarty applies while rendering. `null` leaves PHP's level untouched. |
| `security`      | `null`                                         | Apply a `\Smarty\Security` policy. `null` (no security), `'balanced'`, `'strict'`, or a class-string extending `\Smarty\Security`. See [Security policy](security.md). |

Both directories are created automatically via Laravel's `Filesystem::ensureDirectoryExists()` if missing.

### `force_compile` vs `compile_check`

These two keys both control recompilation but kick in at different points:

- **`compile_check=true`** (default): Smarty stat's the source `.tpl` on every render and recompiles only when the source is newer than the compiled output. Cheap when nothing's changed; correct when it has.
- **`compile_check=false`**: skip the stat — trust whatever's already compiled. A small per-render win in production, on the assumption that deploys always run `php artisan smarty:clear-compiled` so the next render lazily recompiles.
- **`force_compile=true`**: recompile on every render, no stat check. Useful in dev when you're editing a custom compiler, prefilter, or postfilter and need the changes to take effect without manually clearing the compile cache.

Typical settings: `compile_check=true, force_compile=false` in dev (the defaults); `compile_check=false, force_compile=false` in production, with a `smarty:clear-compiled` in the deploy script.

### `error_reporting` example

A bitmask in PHP's `error_reporting()` format. Useful for muting notice-level warnings raised inside templates without changing the PHP-wide level:

```php
// config/smarty.php
'error_reporting' => E_ALL & ~E_NOTICE & ~E_WARNING,
```

Smarty restores the previous level after each render, so the change is scoped to template execution.

## Customising Smarty further

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
            //    Or use the shipped presets — see docs/security.md.
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
