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

The tag/modifier name defaults to the classname with the suffix stripped and snake-cased (`SinceModifier` → `since`, `CspNonceFunction` → `csp_nonce`, `MultiWordBlock` → `multi_word`). Override by declaring a `public string $name = '...'` property on the class — the convention picks that up before falling back to the snake-cased default:

```php
class TimeAgoModifier
{
    public string $name = 'time_ago';  // tag becomes {…|time_ago} instead of {…|time_ago_modifier}'s default

    public function __invoke(mixed $value): string { /* … */ }
}
```

Classes that carry `#[SmartyPlugin]` skip the convention entirely; the attribute's `name:` argument is authoritative there. So the priority resolves cleanly: **attribute > `$name` property > snake-cased classname**.

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

The attribute also carries the plugin's cacheability. If the output depends on request
state — auth, session, locale, the current URL — declare `cacheable: false` and, under
`smarty.caching`, the call compiles into a `{nocache}` region that re-evaluates on every
cache hit instead of baking the first render's output into the cached page:

```php
#[SmartyPlugin(type: 'function', name: 'greeting', cacheable: false)]
final class LocaleGreeting
{
    public function __invoke(array $params): string
    {
        return __('messages.greeting');
    }
}
```

Smarty only honours the flag for `function` and `block` plugins; a modifier's output
follows the cacheability of the expression it appears in. Suffix-convention classes have
no opt-out channel and always register cacheable — use the attribute when you need the
flag.

#### Stacking attributes — one class, multiple tags

`#[SmartyPlugin]` is repeatable: apply it more than once to register the same class under several names or types. Every instance is validated and registered independently, so each can carry its own `cacheable` flag:

```php
#[SmartyPlugin(type: 'modifier', name: 'since')]
#[SmartyPlugin(type: 'modifier', name: 'time_ago', cacheable: false)]
final class TimeAgo
{
    public function __invoke(mixed $value): string
    {
        return $value === null ? '' : Carbon::parse($value)->diffForHumans();
    }
}
```

Templates can now call either `{$date|since}` or `{$date|time_ago}`. A common use case is providing both a short alias and a more descriptive name from the same implementation, or exposing the same logic as both a `modifier` and a `function`.

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

#### Full programmatic API

| Method | Purpose |
|--------|---------|
| `LaravelSmarty::discoverPluginsIn(...$namespaces)` | Add namespaces to the discovery scan. Idempotent — duplicates are silently coalesced, so a package can call it on every boot without worrying about double-registration. |
| `LaravelSmarty::registerPluginClass($class)` | Register a single class outside any scanned namespace. |
| `LaravelSmarty::rebuildDiscoveryCache()` | Force a fresh scan and rewrite the on-disk cache. Backs the `smarty:plugins:cache` command; also handy from a deploy hook that needs the rebuild inline. |
| `LaravelSmarty::flushDiscoveredCache()` | Drop in-memory and on-disk discovery state. Backs `smarty:plugins:clear`. Tests use this to isolate plugin state between cases. |
| `LaravelSmarty::namespaces()` | Read-only introspection of the namespaces currently registered for scanning (config + programmatic). Useful in debugging and in service-provider assertions. |

### Discovery cache

Discovery walks the configured namespaces using Composer's PSR-4 prefix map. The result is cached as a PHP file under `bootstrap/cache/laravel-smarty-plugins.php` — production renders pay zero filesystem-walk cost.

The cache fingerprint covers two layers:

1. **The configured + programmatically-registered namespaces and manually-registered classes.** Adding or removing a namespace invalidates the cache.
2. **The path + mtime of every `*.php` file under those namespaces' directories.** Adding, renaming, or modifying a plugin class invalidates the cache on the next render — no explicit `smarty:plugins:clear` required.

So the cache is self-healing for the editing workflow: you only need `smarty:plugins:clear` if you want to force a re-scan independent of any code change (e.g. after a Composer autoload change that adds a new PSR-4 root).

```bash
php artisan smarty:plugins:cache    # discover and cache (run during deploy)
php artisan smarty:plugins:clear    # delete the cache file
```

### Plugin signatures

Class plugins follow Smarty's native plugin signatures, so an `__invoke` method is all you need:

| Type | `__invoke()` signature |
|------|------------------------|
| `modifier` | `__invoke(mixed $value, ...$extraArgs): mixed` — chainable like any other modifier. |
| `function` | `__invoke(array $params, \Smarty\Template $template): string` — params come straight from `{tag key=value …}`; `$template` enables the `assign=` idiom (`$template->assign($params['assign'], $value)`). Declare only `(array $params)` if you don't need it. |
| `block` | `__invoke(array $params, ?string $content, \Smarty\Template $template, bool &$repeat): string` — `$content` is `null` on open, the body string on close. |

### Worked example: a block plugin

The block signature is the only one with two phases, so it's worth a full example. A `BadgeBlock` that wraps its body in a styled span:

```php
// app/Smarty/Plugins/BadgeBlock.php
namespace App\Smarty\Plugins;

use Smarty\Template;

final class BadgeBlock
{
    public function __invoke(
        array $params,
        ?string $content,
        Template $template,
        bool &$repeat,
    ): string {
        // Open call: $content is null, nothing to emit yet. Returning ''
        // lets the body render so we get it on the close call.
        if ($content === null) {
            return '';
        }

        // Close call: $content is the rendered body (already auto-escaped
        // by `escape_html=true` if the body printed any {$var}). $params
        // values come straight from the template author, so attribute
        // values should still be escaped before being interpolated.
        $variant = htmlspecialchars((string) ($params['variant'] ?? 'default'));

        return "<span class=\"badge badge-{$variant}\">{$content}</span>";
    }
}
```

```smarty
{badge variant="success"}Saved{/badge}
{badge variant="warning"}Pending review{/badge}
```

A few notes on the signature that aren't obvious from the table:

- `$repeat` is a by-reference flag. Set it to `true` from the close call to make Smarty re-invoke the block with the same body — useful for plugins that loop their body N times (rare for HTML blocks, common for query-result iterators). Leave it alone for the normal "render once" case.
- `$template` gives you escape hatches: read template vars via `$template->getTemplateVars($name)`, assign new ones with `$template->assign($name, $value)`. The auth/error blocks shipped with the package use this to bind `$user` / `$message` for the duration of the body.
- Block-state cleanup (the `BlockState::reset()` mechanism described in [Built-in plugins](plugins.md#block-state-safety-under-exceptions)) only applies to the package's own stacks. If your block plugin holds its own state across open/close, make sure it tolerates the close phase never firing — or use `BlockState::push()` / `pop()` to get the same exception-safe behaviour for free.

### Collision behaviour

Two classes that resolve to the same `(type, name)` pair (e.g. a convention-discovered `SinceModifier` and a manually-registered class with `public string $name = 'since'`) throw `PluginRegistrationException` at first render. We'd rather fail loud than silently shadow — the bug a "last-wins" rule invites is exactly the one this throws against.

A class plugin and a `function.<name>.php` file plugin sharing a name is allowed: Smarty looks up registered plugins first, so the class plugin shadows the file plugin. That's the same behaviour Smarty itself applies to any registered plugin and is documented in the [Smarty plugin docs](https://www.smarty.net/docs/en/plugins.tpl).
