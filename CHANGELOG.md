# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning is
[semver](https://semver.org/) with the usual pre-1.0 caveat that minor
releases may contain breaking changes (flagged below).

## [Unreleased — 0.23.0]

Blade-parity feature release.

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

### Documentation

- A stacks recipe (`{capture append=}`) covering the `@push`/`@stack`
  use case, with a clear note that `{push}`/`{stack}` was evaluated and
  won't be added.

## [Unreleased — 0.22.0]

Caching-correctness release: everything request- or locale-coupled is now
nocache, and the output cache finally has positive-control coverage.

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

- **`#[SmartyPlugin(cacheable: false)]`** — discovered plugins can now
  declare request-coupled output. The flag rides through the descriptor
  and the on-disk discovery cache into `registerPlugin()`; previously
  every discovered plugin was registered cacheable and silently baked
  into cached pages.
- **Discovered function plugins receive `$template`**, so class-backed
  function plugins can use the `assign=` idiom like the built-ins.
  Existing plugins declaring only `(array $params)` keep working.

### Fixed

- **Atomic discovery-cache writes.** The plugin cache is written to a
  temp file and `rename()`d into place; a concurrent request can no
  longer `require` a half-written file and 500 with a ParseError. A
  corrupt or schema-drifted cache file (including pre-0.22 formats) now
  triggers a clean rescan instead of throwing.
- **Overlapping plugin namespaces register cleanly.** Scanning
  `App\Smarty` and `App\Smarty\Plugins` together reached the nested
  classes twice and threw a duplicate-name exception citing the class
  as its own duplicate; identical descriptors now dedupe (true
  collisions still throw).

### Upgrade notes

- The on-disk plugin-discovery cache format changed; it invalidates and
  rebuilds itself automatically on first load.
- If a template relied on a cached `|currency`/`|trans` result staying
  frozen across locale changes (unlikely but possible), that output now
  tracks the live locale.

## [Unreleased — 0.21.0]

Security release: output is now safe by default.

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
- `feature_active` added to `StrictSecurityPolicy::$allowed_modifiers` —
  the `{if 'x'|feature_active}` pattern recommended in the docs threw
  under Strict whenever Pennant was installed.
- `smarty:clear-compiled --file=` (empty value) now clears everything,
  aligned with `smarty:clear-cache`.

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
