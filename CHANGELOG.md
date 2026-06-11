# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning is
[semver](https://semver.org/) with the usual pre-1.0 caveat that minor
releases may contain breaking changes (flagged below).

## [0.21.0] - 2026-06-11

Output is now safe by default, everything request- or locale-coupled is
nocache under the output cache, and the Blade-parity surface grows form,
environment and gate features.

### Security

- **`{old}` output is HTML-escaped** — matching Blade's `{{ old(...) }}`.
  Previously the user's flashed input was echoed verbatim (function-plugin
  output bypasses `escape_html`), so a failed validation reflected
  `"><script>` straight back into the form. Opt out per call with
  `raw=true`. Array old-input (array form fields) now renders as an empty
  string instead of `"Array"` plus a conversion warning.
- **`{lang}` / `{lang_choice}` output is HTML-escaped** — matching Blade's
  `{{ __(...) }}` and the already-escaped `|trans` / `|trans_choice`
  modifiers. Opt out per call with `raw=true` for translation lines that
  intentionally contain markup.
- **`|markdown` is sanitized**: rendered with `html_input: escape` and
  `allow_unsafe_links: false`, so HTML embedded in the markdown source is
  escaped and `javascript:`/`data:` links are stripped. Previously the
  CommonMark default passed author HTML through verbatim.
- **`StrictSecurityPolicy` bans the package's state-reaching tags**:
  `{config}`, `{service}`, `{session}`, `{dump}` and `{dd}` are now
  compile-time blocked under `'security' => 'strict'`. `{config}` could
  leak `APP_KEY`/DB credentials and `{service}` resolves arbitrary
  container bindings — neither belongs in untrusted templates.

### Changed

- **Locale-coupled modifiers re-evaluate on cache hits.** `|trans`,
  `|trans_choice`, `|currency`, `|file_size`, `|percentage`,
  `|abbreviate` and `|number_for_humans` now compile into `{nocache}`
  regions under `smarty.caching`. Smarty silently ignores the
  `cacheable` flag for *modifier* plugins (it only honours it for
  function/block plugins), so the package ships a compile-time
  `NocacheModifierCompiler` that marks the surrounding expression
  nocache — previously one user's locale was baked into the shared
  page cache.
- **`{route}` / `{url}` / `{asset}` are nocache.** URL generation reads
  the current request's host and scheme; a cached page could replay the
  wrong host (multi-tenant domains, `X-Forwarded-Host`). Same reasoning
  as the `$route` wrapper, which was already nocache.

### Added

- **Form-state helpers**: `{checked when=...}`, `{selected when=...}`,
  `{disabled when=...}`, `{readonly when=...}`, `{required when=...}` —
  Blade's `@checked` family. Emits the bare attribute token when the
  condition is truthy, nothing otherwise.
- **`{env names="local,staging"}` / `{production}` blocks** (Blade's
  `@env` / `@production`), with `inverse=true` for the negative arm and
  the same lazy-body and fail-closed semantics as the gate blocks.
  These are deliberately the only environment channel available to
  templates running under the Strict security policy.
- **`{error bag="login"}`** — named error-bag support on the `{error}`
  block, mirroring `@error('field', 'login')` for multi-form pages.
- **`args=[...]` on `{can}`/`{cannot}`/`{canany}`/`{canall}`** — the
  multi-argument form of `@can('update', [$post, $extra])` for policy
  methods with extra parameters. `model=` stays as the single-model
  shorthand.
- **`#[SmartyPlugin(cacheable: false)]`** — discovered plugins can now
  declare request-coupled output. The flag rides through the descriptor
  and the on-disk discovery cache into `registerPlugin()`; previously
  every discovered plugin was registered cacheable and silently baked
  into cached pages.
- **Discovered function plugins receive `$template`**, so class-backed
  function plugins can use the `assign=` idiom like the built-ins.
  Existing plugins declaring only `(array $params)` keep working.

### Fixed

- **Views sharing a basename resolve correctly.** The engine now hands
  Smarty the view's absolute path instead of `basename($path)`, so with
  both `dashboard.tpl` and `admin/dashboard.tpl` present,
  `view('admin.dashboard')` no longer renders the root `dashboard.tpl`.
  `smarty:clear-cache --file=` and `smarty:clear-compiled --file=` resolve
  relative names against the template dirs to keep matching.
- **`|markdown` and `|json` no longer require `nofilter`.** Both modifiers
  now compile through modifier compilers that mark their output raw —
  `Js::from()` output is script-safe by construction and markdown output
  is sanitized (see above). With `escape_html` enabled, `{$x|json}` was
  previously double-escaped, pushing users toward `nofilter` (which
  disables *all* protection).
- **Atomic discovery-cache writes.** The plugin cache is written to a
  temp file and `rename()`d into place; a concurrent request can no
  longer `require` a half-written file and 500 with a ParseError. A
  corrupt or schema-drifted cache file (including the pre-0.21 format)
  now triggers a clean rescan instead of throwing.
- **Overlapping plugin namespaces register cleanly.** Scanning
  `App\Smarty` and `App\Smarty\Plugins` together reached the nested
  classes twice and threw a duplicate-name exception citing the class
  as its own duplicate; identical descriptors now dedupe (true
  collisions still throw).
- **`smarty:optimize --force` actually forces recompiles.** Upstream
  `compileAll()` copies its force argument onto a clone it never uses,
  so `--force` silently no-opped whenever `smarty.force_compile` was
  off — i.e. in production, exactly where a deploy hook runs it. The
  command now toggles the live instance around the call.
- **`smarty:optimize` exits non-zero when templates fail to compile**
  (the vendor API swallows per-template exceptions), so deploy
  pipelines can gate on pre-compilation.
- `smarty:clear-cache` / `smarty:clear-compiled` reject a non-numeric
  `--expire` instead of casting it to 0 — which meant "clear
  everything", the opposite of the narrow clear the typo intended.
- `feature_active` added to `StrictSecurityPolicy::$allowed_modifiers` —
  the `{if 'x'|feature_active}` pattern recommended in the docs threw
  under Strict whenever Pennant was installed.
- `smarty:clear-compiled --file=` (empty value) now clears everything,
  aligned with `smarty:clear-cache`.

### Packaging

- `.gitattributes` with `export-ignore`: dist installs (`composer
  require`) no longer ship tests/, docs/, CI workflows and tooling
  configs.
- composer.json metadata: `keywords`, `homepage`, `authors`, `support`,
  and `suggest: laravel/pennant`.

### Documentation

- A stacks recipe (`{capture append=}`) covering the `@push`/`@stack`
  use case, with a clear note that `{push}`/`{stack}` was evaluated and
  won't be added.
- New troubleshooting page (stale compiles after deploys,
  `ReservedTemplateVariable`, escaping surprises, Octane notes
  consolidated); a real docs landing page; root `SECURITY.md`
  (vulnerability disclosure — distinct from the sandboxing docs) and
  `CONTRIBUTING.md`; README links the docs site, drops the redundant
  `|escape` from the quick start, and gains Packagist badges;
  `configuration.md` documents `plugin_namespaces`.

### Upgrade notes

- If a template relied on raw `{old}`/`{lang}`/`{lang_choice}` output
  (e.g. translation lines containing HTML), add `raw=true` to those calls.
- `{$x|markdown nofilter}` / `{$x|json nofilter}` keep working, but the
  `nofilter` is now redundant — remove it.
- If untrusted templates running under Strict used `{config}`/`{session}`,
  move that data into view data or the `$session` wrapper object.
- Compiled-template and cache file names changed (they key on the template
  path, which is now absolute). Run `php artisan smarty:clear-compiled`
  and `php artisan smarty:clear-cache` after upgrading; stale files from
  0.20.x are orphaned, not reused.
- The on-disk plugin-discovery cache format changed; it invalidates and
  rebuilds itself automatically on first load.
- If a template relied on a cached `|currency`/`|trans` result staying
  frozen across locale changes (unlikely but possible), that output now
  tracks the live locale.

## [0.20.10] - 2026-06-06

### Documentation

- New docs site at <https://vusys.github.io/laravel-smarty/>, built from
  `docs/` with Material for MkDocs — searchable, themed versions of the
  overview, quick-start, configuration, plugin, security, and development
  pages.
- README documents the `smarty-pagination-views` publish tag.

### Internal

- New `docs` CI workflow deploying via `actions/deploy-pages` with
  least-privilege permissions; `actions/checkout` v6, `actions/cache` v5,
  `actions/upload-artifact` v7. No published-package changes.

## [0.20.9] - 2026-06-05

### Changed

- **`{dump}` and `{dd}` no-op outside `local` / `testing`.** A stray
  `{dump $secret}` left in a production template would dump to the
  response body, and a forgotten `{dd}` would halt the request
  mid-render; both now check the app environment and silently do nothing
  anywhere else.

### Internal

- `declare(strict_types=1)` across the remaining plugin classes; mutation
  testing pass (MSI ~88% → ~92%); a few undocumented methods tightened to
  protected/private.

## [0.20.8] - 2026-05-31

### Documentation

- The ~800-line README is split into per-topic pages under `docs/`,
  leaving a slim landing page. The split filled previously-masked gaps:
  block-state exception safety under Octane, a worked block-plugin
  example, the full `LaravelSmarty::*` programmatic API,
  discovery-cache mtime fingerprinting, the auto-escape default,
  `force_compile` vs `compile_check`, verifying a security policy from a
  test, and wrapper-object / view-composer examples. No runtime changes.

## [0.20.7] - 2026-05-09

### Fixed

- **Mutating view composers now work on `{extends}` layouts and
  `{include}`-d partials.** `$view->with(...)` inside a composer landed
  on a synthetic `View` object that was never rendered, so the data
  silently vanished. The resource now transcribes composer/creator data
  onto the actual Smarty template; composer values override shared data
  on collision, matching Blade's precedence.

## [0.20.6] - 2026-05-09

### Fixed

- **The plugin-discovery cache self-invalidates on file changes.** The
  fingerprint only covered the namespace list, so adding, removing, or
  editing a plugin class inside a known namespace served a stale cache
  ("unknown tag") until a manual `smarty:plugins:clear`. The fingerprint
  now folds in the path + mtime of every `.php` file under the scanned
  directories.

## [0.20.5] - 2026-05-09

### Added

- **`inverse=true` on `{feature}` / `{canany}` / `{canall}`** — an
  "else"-arm form of each block. `{feature}` keeps its optional-Pennant
  guard in both arms; an empty `abilities=[]` fails closed in both arms.
- **`feature_active(name, for)` modifier** for `{if}`-position Pennant
  checks (registered only when Pennant is installed), and
  **`$auth->canAny()` / `$auth->canAll()`** on the auth wrapper, both
  fail-closed on an empty ability list.

## [0.20.4] - 2026-05-09

### Fixed

- **Stock Laravel apps no longer 500 on every request.** Laravel's
  `ShareErrorsFromSession` middleware shares `errors` on every request;
  the reserved-variable guard treated that as a collision and threw
  `ReservedTemplateVariable`. A `ViewErrorBag` under `errors` is now
  silently superseded by the package's `$errors` wrapper (which wraps
  the same bag); any other type, and the other reserved keys, still
  throw.

## [0.20.3] - 2026-05-09

### Internal

- `declare(strict_types=1)` on the Laravel-facing entry points. Plugin
  classes deliberately stay coercive — template inputs are routinely
  untyped strings.

## [0.20.2] - 2026-05-09

### Internal

- Deterministic test coverage of the discovery-cache load branch
  (previously order-dependent via `EngineResolver` caching).

## [0.20.1] - 2026-05-09

### Internal

- 100% line coverage of `src/`; the three genuinely unreachable
  defensive blocks are documented `@codeCoverageIgnore`. New
  `composer test` / `test:coverage` / `test:coverage-html` scripts.

## [0.20.0] - 2026-05-09

### Added

- **Class-backed plugin auto-discovery.** Two registration channels
  alongside the existing `plugins_paths` file convention: classname
  suffix (`SinceModifier` → `since`, with `public string $name` to
  override) and the `#[SmartyPlugin(type:, name:)]` attribute, which
  always wins over the convention. Plugin instances resolve through the
  container, so constructor DI works.
- `plugin_namespaces` config key (default `['App\\Smarty\\Plugins']`),
  `LaravelSmarty::discoverPluginsIn()` for third-party packages, and
  `LaravelSmarty::registerPluginClass()` for one-offs.
- Discovery cache at `bootstrap/cache/laravel-smarty-plugins.php` with
  `smarty:plugins:cache` / `smarty:plugins:clear` commands.
- Two classes resolving to the same `(type, name)` throw
  `PluginRegistrationException` at first render; a class plugin shadows
  a same-named file plugin (Smarty's own precedence).

## [0.19.0] - 2026-05-09

### Added

- **`$errors` auto-shared wrapper around `ViewErrorBag`** — fifth peer
  of `$auth` / `$request` / `$session` / `$route`, with `any()`,
  `has()`, `count()`, `all()`, `first()`, `get()` and `getBag()`
  (returning a scoped sub-wrapper). Always non-null; tolerates missing
  session bindings. `errors` joins the reserved view-data keys.

## [0.18.0] - 2026-05-09

### Added

- **`{feature}` Pennant block** mirroring Blade's `@feature`, with
  `for=` subject scoping. Non-cacheable; silently no-ops when
  `laravel/pennant` isn't installed.

## [0.17.0] - 2026-05-09

### Added

- **`{csp_nonce}`**, **`{vite_asset}`** and **`{vite_content}`** —
  wrappers over the remaining template-shaped `Vite` methods, all
  non-cacheable (per-request nonces, hot-vs-built URLs).

## [0.16.0] - 2026-05-09

### Added

- **`{signed_route}` / `{temporary_signed_route}`** wrapping
  `URL::signedRoute()` / `URL::temporarySignedRoute()` (`expiration=`
  takes seconds or a `DateTimeInterface`). Non-cacheable so signatures
  stay fresh under a warm output cache.

## [0.15.0] - 2026-05-09

### Added

- **Opt-in security-policy presets**: `'security' => 'balanced'`
  (admin/CMS templates — blocks `{php}`, `{math}`, super-globals,
  arbitrary static classes) and `'strict'` (user-submitted templates —
  additionally blocks `{fetch}`/`{eval}`/`{include_php}`, stream
  wrappers, constants, and switches to a modifier allow-list), or any
  class-string extending `\Smarty\Security`. Invalid values fail fast on
  first render; the bare `\Smarty\Security` class is rejected as a
  footgun.

### Internal

- `StrictSecurityPolicy::isTrustedStream()` overrides an upstream bug
  where an empty `$streams` array means "all trusted" instead of "all
  denied".

## [0.14.0] - 2026-05-09

### Added

- **Four read-only auto-shared wrapper objects** — `$auth` (null for
  guests), `$request`, `$session`, `$route` — usable in any expression
  context (`{if}` operands, `{include}` parameters), not just output
  position. All nocache. The four names become reserved view-data keys
  and throw `ReservedTemplateVariable` on collision.

### Breaking

- **`$session` is no longer a plain array** — `{$session.foo}` becomes
  `{$session->foo}` or `{$session->get('foo')}`.
- `view('foo', ['session' => ...])` (and the other reserved keys)
  throws instead of overriding the auto-share.

## [0.13.0] - 2026-05-08

### Added

- **`{csrf_token}`** — companion to `{csrf_field}` emitting the raw
  token for `<meta>` tags and AJAX headers. Non-cacheable.

## [0.12.0] - 2026-05-07

### Fixed

- **Block-plugin state resets at the render boundary.** If a body threw
  inside `{auth}` / `{error}`, the pushed `$user` / `$message` frame
  leaked — harmless under PHP-FPM, monotonic growth under
  Octane / Swoole / RoadRunner. State now lives in `BlockState` and the
  engine resets it in a `finally`.

### Internal

- The caching contract (`cacheable=false`) is now pinned by a
  re-evaluation test for every documented request-coupled plugin, and
  the `function.` / `block.` file-plugin conventions and the clear
  commands' `--file=` pass-through gained their first direct tests.

## [0.11.0] - 2026-05-06

### Fixed

- **Built-in plugins are cache-safe.** Under `smarty.caching`, every
  request-coupled plugin (`{auth}`, `{csrf_field}`, `{old}`,
  `{session}`, `{vite}`, `{lang}`, the gate blocks, …) registered
  cacheable, so the first render's output — a guest's empty auth body,
  one session's CSRF token — was baked into the shared cache. All now
  register `cacheable=false`; `$session` is assigned nocache.
- **The bundled pagination templates actually render.** Laravel's own
  Blade pagination views won the view-finder lookup, so the package's
  `.tpl` ports were never used; the namespace is now prepended. Five
  presets also used `is_string()` inline, which Smarty can't compile —
  hidden until the templates started winning.

### Added

- `vendor:publish --tag=smarty-pagination-views` for user overrides,
  resolved ahead of both the bundled `.tpl` and Laravel's Blade.

## [0.10.0] - 2026-05-06

### Fixed

- **`{session}` flash data is usable in conditions** — the plugin
  accepts `assign=`, and `$session` is auto-shared on every render so
  `{if $session.status}` works without a controller pre-assign.
- **`{auth}` exposes the user** — the authenticated user is bound as
  `$user` for the body's duration (the documented
  `{auth()->user()->name}` form never compiled: `auth` is a block tag).

## [0.9.0] - 2026-05-05

### Added

- **`{canany}` / `{canall}` blocks** mirroring `@canany` and an
  every-ability check, completing the `{can}` / `{cannot}` family.
  Empty `abilities=` short-circuits to denied.

## [0.8.0] - 2026-05-05

### Added

- **Five Laravel-helper plugin families**: `{class}` / `{style}`
  (`Arr::toCssClasses()` / `toCssStyles()`), `{config}` / `{session}`,
  `|markdown` (`Str::markdown()`), `{lang_choice}` / `|trans_choice`
  for pluralisation, and the `Number` modifier suite (`|currency`,
  `|file_size`, `|percentage`, `|abbreviate`, `|number_for_humans`) —
  skipped quietly on Laravel 10 where `Number` doesn't exist.

## [0.7.0] - 2026-05-05

### Added

- **Smarty runtime errors map back to the `.tpl` source line** on the
  Laravel exception page (Blade parity on Laravel 11+): a
  line-tracking compiler injects source markers into compiled output,
  and a `BladeMapper`-based exception mapper rewrites `.tpl.php` frames.
  The engine walks the `getPrevious()` chain so errors inside
  `{capture}` surface the real exception.

## [0.6.1] - 2026-05-04

### Internal

- Rector fix: `app()` → `resolve()` in `VitePlugins`.

## [0.6.0] - 2026-05-04

### Added

- **`{vite}` and `{vite_react_refresh}`** tags.

### Changed

- `{error}` and the `{auth}` / `{guest}` / `{can}` / `{cannot}` blocks
  short-circuit body evaluation on the failing branch.

## [0.5.1] - 2026-05-01

### Internal

- Rector follow-ups to the 0.5.0 work; no behaviour change.

## [0.5.0] - 2026-05-01

### Internal

- **PHPStan level 6 → 9**, fixing real type errors at each step rather
  than baselining (contract-safe container access, nullable
  `getSource()` / `preg_replace()` boundaries, a typed config shape).
  Level 10 was evaluated and skipped — Smarty plugin closures inherently
  receive mixed param bags.

## [0.4.0] - 2026-05-01

### Added

- **Four curated config keys**: `left_delimiter` / `right_delimiter`,
  `compile_check`, `default_modifiers`, `error_reporting`.
- **`SmartyFactory::configure()`** — a service-provider hook with the
  final say over each Smarty instance, for everything the curated keys
  don't cover. Lives on the factory so it stays safe under
  `config:cache` (closures aren't serialisable).

## [0.3.0] - 2026-05-01

### Internal

- Static-analysis toolchain: Larastan (level 6), Rector
  (version-agnostic quality sets only, PHP pinned to 8.1 so refactors
  can't break the Laravel 10–13 matrix), and Pint, with a CI job and
  `composer analyse` / `rector` / `pint` scripts. No runtime changes.

## [0.2.0] - 2026-05-01

### Added

- **Artisan commands**: `smarty:optimize` (pre-compile all templates in
  a deploy step), `smarty:clear-compiled`, and `smarty:clear-cache`,
  sharing the same configured Smarty instance the runtime uses.

## [0.1.0] - 2026-05-01

Initial release: Smarty 5 as a Laravel view engine. `.tpl` registers
ahead of `.blade.php` so both engines coexist; `view()` returns a normal
`View`. View composers and debugbar see the full template tree (including
`{extends}` / `{include}` sub-templates) via a bridged
`doCreateTemplate()`. Ships the core plugin set (auth/gate blocks, form,
translation, URL and helper plugins), Smarty ports of Laravel's
pagination templates, curated config for compile/cache paths, caching,
auto-escaping and plugin paths. PHP ^8.1, Laravel 10–13,
smarty/smarty ^5.4.
