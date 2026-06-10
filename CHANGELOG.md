# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning is
[semver](https://semver.org/) with the usual pre-1.0 caveat that minor
releases may contain breaking changes (flagged below).

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
