# Custom modifiers and plugins

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

## Class-backed plugins

Two **additional**, optional registration styles let you write plugins as classes instead of file-scoped functions. Both are wired up alongside `plugins_paths` — pick whichever ergonomics suit the team, or mix freely. Discovery is namespace-driven, so plugins from a third-party package and host-app plugins both land on the same Smarty instance without manual stitching.

### Convention by classname suffix

Drop a class under one of the configured `plugin_namespaces` and end its name with `Modifier`, `Function`, or `Block`. The package picks it up on first render and registers an instance through the container — so the class can declare constructor dependencies and they'll be auto-resolved.

```php
// app/Smarty/Plugins/SinceModifier.php
namespace App\Smarty\Plugins;

use Illuminate\Support\Carbon;

class SinceModifier
{
    public function __invoke(mixed $value): string
    {
        return $value === null ? '' : Carbon::parse($value)->diffForHumans();
    }
}
```

```smarty
Posted {$post->created_at|since}    {* Posted 3 hours ago *}
```

The tag/modifier name defaults to the classname with the suffix stripped and snake-cased (`SinceModifier` → `since`, `CspNonceFunction` → `csp_nonce`, `MultiWordBlock` → `multi_word`). Override by declaring a `public string $name = '...'` property on the class.

### Attribute-tagged

Wear a `#[SmartyPlugin]` attribute on any class with `__invoke()` to opt out of the suffix convention — useful for packages that want IDE-discoverable, type-checkable plugin declarations, or for keeping a domain-shaped classname.

```php
use Vusys\LaravelSmarty\Attributes\SmartyPlugin;

#[SmartyPlugin(type: 'modifier', name: 'since')]
final class TimeAgo
{
    public function __invoke(mixed $value): string
    {
        return $value === null ? '' : Carbon::parse($value)->diffForHumans();
    }
}
```

A class carrying the attribute is **never** also matched by the suffix convention — the attribute wins outright, so renaming the class won't double-register the plugin.

### Configuring discovery

```php
// config/smarty.php
'plugin_namespaces' => [
    'App\\Smarty\\Plugins',          // default
    'App\\Billing\\Smarty',          // mix as many as you like
],
```

Set to `[]` to disable namespace discovery entirely (the manual APIs below still work).

Third-party packages can register their own discovery namespace from a service provider's `boot()`, without forcing the host app to edit config:

```php
use Vusys\LaravelSmarty\LaravelSmarty;

public function boot(): void
{
    LaravelSmarty::discoverPluginsIn('Vendor\\BillingPack\\Smarty');
}
```

Or register a single class regardless of namespace — useful for one-offs, test fixtures, or classes you'd rather not move:

```php
LaravelSmarty::registerPluginClass(App\Reports\TimeAgo::class);
```

The class still has to either carry `#[SmartyPlugin]` or end in a recognised suffix; anything else throws.

### Discovery cache

Discovery walks the configured namespaces using Composer's PSR-4 prefix map. The result is cached as a PHP file under `bootstrap/cache/laravel-smarty-plugins.php` — production renders pay zero filesystem-walk cost. The cache fingerprint is derived from the configured + programmatically-registered namespaces (and any manually-registered classes), so adding a namespace invalidates the cache automatically.

```bash
php artisan smarty:plugins:cache    # discover and cache (run during deploy)
php artisan smarty:plugins:clear    # delete the cache file
```

### Plugin signatures

Class plugins follow Smarty's native plugin signatures, so an `__invoke` method is all you need:

| Type | `__invoke()` signature |
|------|------------------------|
| `modifier` | `__invoke(mixed $value, ...$extraArgs): mixed` — chainable like any other modifier. |
| `function` | `__invoke(array $params): string` — params come straight from `{tag key=value …}`. |
| `block` | `__invoke(array $params, ?string $content, \Smarty\Template $template, bool &$repeat): string` — `$content` is `null` on open, the body string on close. |

### Collision behaviour

Two classes that resolve to the same `(type, name)` pair (e.g. a convention-discovered `SinceModifier` and a manually-registered class with `public string $name = 'since'`) throw `PluginRegistrationException` at first render. We'd rather fail loud than silently shadow — the bug a "last-wins" rule invites is exactly the one this throws against.

A class plugin and a `function.<name>.php` file plugin sharing a name is allowed: Smarty looks up registered plugins first, so the class plugin shadows the file plugin. That's the same behaviour Smarty itself applies to any registered plugin and is documented in the [Smarty plugin docs](https://www.smarty.net/docs/en/plugins.tpl).
