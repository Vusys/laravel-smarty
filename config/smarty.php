<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View File Extension
    |--------------------------------------------------------------------------
    |
    | The file extension used for Smarty templates. The package registers
    | this as the highest-priority extension on the view finder, so that
    | `view('welcome')` resolves to `welcome.tpl` before `welcome.blade.php`.
    |
    */

    'extension' => 'tpl',

    /*
    |--------------------------------------------------------------------------
    | Compile / Cache Directories
    |--------------------------------------------------------------------------
    |
    | Smarty needs writable directories for its compiled templates and
    | cached output. Defaults live under storage/framework/smarty.
    |
    */

    'compile_path' => storage_path('framework/smarty/compile'),
    'cache_path' => storage_path('framework/smarty/cache'),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Whether Smarty should cache rendered output. Mirrors the
    | Smarty::CACHING_OFF / CACHING_LIFETIME_CURRENT constants.
    |
    */

    'caching' => false,
    'cache_lifetime' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Force Compile
    |--------------------------------------------------------------------------
    |
    | When true, Smarty recompiles templates on every request. Useful in
    | development; disable in production for performance.
    |
    */

    'force_compile' => false,

    /*
    |--------------------------------------------------------------------------
    | Debugging
    |--------------------------------------------------------------------------
    */

    'debugging' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-escape HTML
    |--------------------------------------------------------------------------
    |
    | When true, every `{$var}` output is run through htmlspecialchars(),
    | matching Blade's `{{ }}` behavior. Templates that need raw output can
    | use the `nofilter` flag (`{$var nofilter}`) for individual writes.
    | Set to false to opt out and require explicit `|escape` everywhere.
    |
    */

    'escape_html' => true,

    /*
    |--------------------------------------------------------------------------
    | Plugins Directories
    |--------------------------------------------------------------------------
    |
    | Additional directories to scan for custom Smarty plugins. These are
    | the canonical Smarty plugin paths — files named `function.<name>.php`,
    | `modifier.<name>.php`, etc. The class-backed `plugin_namespaces` form
    | below is *additional*, not a replacement.
    |
    */

    'plugins_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Class-Backed Plugin Namespaces
    |--------------------------------------------------------------------------
    |
    | PSR-4 namespaces scanned for class-backed plugins. Two registration
    | styles are supported per class: a `#[SmartyPlugin]` attribute, or
    | the `*Modifier` / `*Function` / `*Block` classname-suffix
    | convention. Set to an empty array to disable namespace discovery
    | (the manual `LaravelSmarty::registerPluginClass()` API still works).
    | Third-party packages can add their own namespaces from a service
    | provider's boot() via `LaravelSmarty::discoverPluginsIn(...)`.
    |
    */

    'plugin_namespaces' => [
        'App\\Smarty\\Plugins',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tag Delimiters
    |--------------------------------------------------------------------------
    |
    | Override Smarty's default `{` and `}` delimiters. Useful when templates
    | live alongside JavaScript that uses the same braces, or when bridging
    | from a legacy templating system that used non-standard markers.
    | Set both to `null` (default) to leave Smarty's behaviour untouched.
    |
    */

    'left_delimiter' => null,
    'right_delimiter' => null,

    /*
    |--------------------------------------------------------------------------
    | Compile Check
    |--------------------------------------------------------------------------
    |
    | When `true`, Smarty checks the template source's mtime on every render
    | and recompiles when stale. Disable in production for a small per-render
    | win — at the cost of needing an explicit `smarty:clear-compiled` after
    | a deploy.
    |
    */

    'compile_check' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Modifiers
    |--------------------------------------------------------------------------
    |
    | Modifiers applied automatically to every `{$var}` output. With
    | `escape_html` already on by default this is rarely needed, but it's
    | the right place to add things like `['strip']` or a custom
    | `['my_normalize']` modifier across the whole template tree.
    |
    */

    'default_modifiers' => [],

    /*
    |--------------------------------------------------------------------------
    | Error Reporting
    |--------------------------------------------------------------------------
    |
    | An `error_reporting()`-style bitmask Smarty applies while rendering.
    | Defaults to `null` — leave PHP's current `error_reporting` level
    | untouched. Set e.g. `E_ALL & ~E_NOTICE` to mask noisy notices in
    | templates without changing the rest of the application's level.
    |
    */

    'error_reporting' => null,

    /*
    |--------------------------------------------------------------------------
    | Security Policy
    |--------------------------------------------------------------------------
    |
    | Apply a \Smarty\Security policy to every render. Accepts:
    |
    |   null        — no security (default, backwards compatible).
    |   'balanced'  — Vusys\LaravelSmarty\Security\BalancedSecurityPolicy:
    |                 sensible defaults for admin-authored templates.
    |   'strict'    — Vusys\LaravelSmarty\Security\StrictSecurityPolicy:
    |                 hardened defaults for untrusted, user-submitted
    |                 templates (modifier allow-list, no constants, no
    |                 streams, no {fetch}/{eval}/{include_php}).
    |   class-string — a custom subclass of \Smarty\Security.
    |
    | Closures are intentionally not accepted — they break `config:cache`.
    | For dynamic construction use SmartyFactory::configure() in a service
    | provider's boot() and call $smarty->enableSecurity($policy) yourself.
    |
    */

    'security' => null,

];
