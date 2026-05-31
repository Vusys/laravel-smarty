# Laravel integration

## View composers

`composing:` and `creating:` events fire for every template Smarty loads, including `{extends}` parents and `{include}` partials, so view composers work the same as they do for Blade:

```php
View::composer('layouts.main', function ($view) {
    $view->with('user', auth()->user());
});
```

Data added by a composer via `$view->with(...)` (or `$view->withErrors(...)` etc.) is transcribed onto the actual sub-template before render, so the variables are visible inside `{extends}` layouts and `{include}` partials — same contract Blade gives. Both `composing:` and `creating:` listeners can mutate the View; either way the values reach the render scope. `View::share` precedence is unchanged: the global shared bag still feeds every render, with composer-added data layered on top (composer wins on key collision, matching Blade's `gatherData()` order).

## Debug tooling

`creating:` and `composing:` view events fire for every template Smarty loads — entries, `{extends}` parents, and `{include}` partials — so anything in the Laravel ecosystem that listens to those events sees the full render tree. Debugbar's **Views** tab, Telescope's **Views** watcher, and any other tool that hooks Laravel's view events should work without extra wiring, the same way they do for Blade.

## Template error source mapping

A runtime error inside a `.tpl` body — say `{$user->getAuthIdentifier()}` when `$user` is null — would naturally land on Smarty's compiled `<hash>_<file>.tpl.php` file under `storage/framework/smarty/compile/`, with no obvious link back to the template you actually wrote. This package rewrites that automatically.

- A custom compiler (`Debug\LineTrackingCompiler`) emits `/*__SLM:N*/` and `/*__SLF:/abs/path*/` markers into the compiled output during compilation. Installed via reflection at `Template` instantiation time, no vendor patching.
- `Debug\SourceMap` walks back from the compiled-file frame to the closest preceding marker.
- On Laravel 11+, `Debug\SmartyExceptionMapper` extends the framework's `BladeMapper` and is bound in its place, so the exception page rewrites every `.tpl.php` trace frame to the `.tpl` source — same treatment Blade enjoys for `.blade.php`.
- `SmartyEngine::remapException()` walks the full `getPrevious()` chain so errors raised inside a `{capture}` body still surface the user's real exception, not Smarty's `Not matching {capture}{/capture}` rethrow wrapper.

The mapping covers `{block}` bodies of `{extends}` children, `{include}`d partials (including `inline`), `{function}` bodies (both `{call}` and short-tag invocations), `{capture}` bodies, `{if}` condition expressions, and `Smarty\CompilerException`s raised at compile time. Laravel 10 has no `BladeMapper` to extend, so the trace-frame rewrite no-ops there — error messages still carry a `(View: /path/to/source.tpl)` suffix and `CompilerException` source paths/lines are preserved.
