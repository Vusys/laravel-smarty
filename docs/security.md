# Security policy

Smarty templates can do a lot at runtime — raw `{php}` blocks, `{math equation=...}` (which `eval()`s its argument), `{fetch file=...}` (which reads files and URLs), `$_SERVER` / `$_GET` access, arbitrary static-class calls. For dev-authored templates that's fine. For anything where templates come from CMS admins, partners, or end users, those features are footguns.

The package ships two `\Smarty\Security` subclasses you can opt into with one config line:

| value      | class                                                              | use case |
|------------|--------------------------------------------------------------------|----------|
| `null`     | _no policy_                                                         | Default. Trusted, dev-authored templates only. |
| `balanced` | `Vusys\LaravelSmarty\Security\BalancedSecurityPolicy`              | Admin-authored / CMS templates. Blocks `{php}`, `{math}`, super-globals, and arbitrary static-class access. Leaves modifiers and constants alone. |
| `strict`   | `Vusys\LaravelSmarty\Security\StrictSecurityPolicy`                | User-submitted / multi-tenant templates. Inherits Balanced and additionally blocks `{fetch}` / `{eval}` / `{include_php}`, the package's state-reaching helpers `{config}` / `{service}` / `{session}` / `{dump}` / `{dd}` (config keys, container bindings, and session state have no place in untrusted templates — the `$auth`/`$session`/`$request` wrapper objects remain the sanctioned read channel), all constants, all stream wrappers, and switches modifiers to an explicit allow-list (Smarty 5's full default modifier set minus `regex_replace` for catastrophic-backtracking DoS, plus this package's own modifiers). |

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

**Reading the environment from sandboxed templates.** `{config key='app.env'}` is banned
under Strict (it reads *any* config key), so the `{env names=...}` and `{production}`
blocks are deliberately the only environment channel available to untrusted templates —
a yes/no gate that leaks nothing sensitive. They stay usable under both shipped policies.

**`markdown` produces raw (but sanitized) HTML.** The package's `markdown` modifier wraps `Str::markdown()` with `html_input: escape` and `allow_unsafe_links: false`, so HTML embedded in the source is escaped and `javascript:`/`data:` links are stripped — every tag in the output comes from CommonMark itself, never from the input. The rendered HTML is emitted without a `nofilter`. If your data-trust profile demands zero HTML from a modifier, drop `markdown` from `$allowed_modifiers` in a subclass.

**Toggling the policy after templates were already compiled.** Smarty caches compiled templates under `compile_path`. Switching `'security'` from `null` to `'strict'` does not invalidate previously compiled output, so the first render after a switch may still execute a template that was compiled with no policy attached. Run `php artisan smarty:clear-compiled` after changing the setting to be safe.

**Invalid config values.** If the `'security'` key isn't `null`, `'balanced'`, `'strict'`, or a class extending `\Smarty\Security`, the engine throws `InvalidArgumentException` on the first view render. Silent fallback to "no security" would be unsafe — the user assumes they're protected. Non-string values (`true`, an array, etc.) and unknown class names both fail with a descriptive message.

**Out of scope (for now).** A dedicated logging channel for security violations (Laravel's default exception reporter already captures `\Smarty\Exception`); auto-invalidating the compile cache when the policy changes (clear `compile_path` after toggling); and a publishable subclass stub.

## Verifying a policy

Policies are programmatically testable — instantiate one against a throwaway `Smarty` instance and render a template fragment that *should* be blocked. The render throws a `\Smarty\Exception` you can assert against:

```php
use Smarty\Smarty;
use Vusys\LaravelSmarty\Security\StrictSecurityPolicy;

$smarty = new Smarty;
$smarty->setTemplateDir(__DIR__.'/fixtures');
$smarty->enableSecurity(new StrictSecurityPolicy($smarty));

try {
    $smarty->fetch('eval://{fetch file="https://example.com"}');
    throw new \LogicException('Policy did not block {fetch}');
} catch (\Smarty\Exception $e) {
    // expected — {fetch} is blocked under Strict
}
```

The `eval://` resource lets you pass template source directly, so you don't need fixture files to assert against per-tag behaviour. The same approach works inside a phpunit/Pest test — useful when you've subclassed a policy and want to verify your tweaks didn't accidentally loosen something else.
