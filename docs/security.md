# Security policy

Smarty templates can do a lot at runtime — raw `{php}` blocks, `{math equation=...}` (which `eval()`s its argument), `{fetch file=...}` (which reads files and URLs), `$_SERVER` / `$_GET` access, arbitrary static-class calls. For dev-authored templates that's fine. For anything where templates come from CMS admins, partners, or end users, those features are footguns.

The package ships two `\Smarty\Security` subclasses you can opt into with one config line:

| value      | class                                                              | use case |
|------------|--------------------------------------------------------------------|----------|
| `null`     | _no policy_                                                         | Default. Trusted, dev-authored templates only. |
| `balanced` | `Vusys\LaravelSmarty\Security\BalancedSecurityPolicy`              | Admin-authored / CMS templates. Blocks `{php}`, `{math}`, super-globals, and arbitrary static-class access. Leaves modifiers and constants alone. |
| `strict`   | `Vusys\LaravelSmarty\Security\StrictSecurityPolicy`                | User-submitted / multi-tenant templates. Inherits Balanced and additionally blocks `{fetch}` / `{eval}` / `{include_php}`, all constants, all stream wrappers, and switches modifiers to an explicit allow-list (Smarty 5's full default modifier set minus `regex_replace` for catastrophic-backtracking DoS, plus this package's own modifiers). |

Enable in `config/smarty.php`:

```php
'security' => 'balanced',
```

Or wire a policy yourself via `SmartyFactory::configure()` if you need non-default tweaks:

```php
SmartyFactory::configure(function (\Smarty\Smarty $smarty) {
    $policy = new \Vusys\LaravelSmarty\Security\StrictSecurityPolicy($smarty);
    $policy->trusted_constants = ['APP_VERSION'];           // allow specific constants
    $policy->allowed_modifiers[] = 'my_custom_modifier';    // append host-app modifiers
    $smarty->enableSecurity($policy);
});
```

**Subclassing.** Both shipped classes set everything as plain public properties, so you can subclass and override any single knob without touching the rest:

```php
class AppSecurityPolicy extends \Vusys\LaravelSmarty\Security\BalancedSecurityPolicy
{
    public $allow_constants = false;             // tighten this one default
    public $trusted_constants = ['APP_VERSION']; // but allow this single constant
}
```

Then point the config at it: `'security' => \App\Smarty\AppSecurityPolicy::class`.

**Custom modifiers under Strict.** `StrictSecurityPolicy::$allowed_modifiers` ships with Smarty's built-in formatting modifiers plus the modifiers this package registers (`currency`, `trans`, `markdown`, etc.). If your app registers its own modifier, append it via subclassing — anything not on the list is rejected at render time with a `\Smarty\Exception`.

**`markdown` produces raw HTML.** The package's `markdown` modifier wraps `Str::markdown()` and emits unescaped tags (`<strong>`, `<a>`, …). The Strict policy's threat model is "trusted data, untrusted template" — it doesn't gate the *data* templates render. If your app passes user-controlled strings into a template that uses `{$x|markdown nofilter}`, that's still a stored-XSS surface. Drop `markdown` from `$allowed_modifiers` in a subclass if your data-trust profile demands it.

**Toggling the policy after templates were already compiled.** Smarty caches compiled templates under `compile_path`. Switching `'security'` from `null` to `'strict'` does not invalidate previously compiled output, so the first render after a switch may still execute a template that was compiled with no policy attached. Run `php artisan smarty:clear-compiled` after changing the setting to be safe.

**Invalid config values.** If the `'security'` key isn't `null`, `'balanced'`, `'strict'`, or a class extending `\Smarty\Security`, the engine throws `InvalidArgumentException` on the first view render. Silent fallback to "no security" would be unsafe — the user assumes they're protected. Non-string values (`true`, an array, etc.) and unknown class names both fail with a descriptive message.

**Out of scope (for now).** A dedicated logging channel for security violations (Laravel's default exception reporter already captures `\Smarty\Exception`); auto-invalidating the compile cache when the policy changes (clear `compile_path` after toggling); and a publishable subclass stub.
