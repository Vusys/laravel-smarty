# Contributing

Thanks for considering a contribution. Short version: open an issue first for anything
behavioural, keep PRs focused, and bring tests.

## Setup

```bash
git clone https://github.com/Vusys/laravel-smarty.git
cd laravel-smarty
composer install
```

## The bar a PR needs to clear

CI runs all of these; saving a round-trip locally:

```bash
composer test            # phpunit (random execution order)
composer analyse         # phpstan level 9 (larastan)
composer rector:check    # rector dry-run
composer pint:check      # code style
```

Mutation testing runs in CI on changed lines (Infection); the suite is held at ≥90% MSI.
If your change adds logic, expect to add the test that kills its mutants — registration
flags, guard clauses and cacheability decisions all have pinning tests you can crib from.

## Conventions worth knowing

- **Tests land with the code they pin, in the same PR.** Behavioural claims in
  docblocks should have a test backing them.
- **Output safety is non-negotiable**: function-plugin output bypasses `escape_html`,
  so anything user-coupled must escape its own output (see `PluginOutput::escape()`)
  with a `raw=true` opt-out where that makes sense.
- **Cacheability is part of a plugin's contract**: request- or locale-coupled output
  registers `cacheable=false` (functions/blocks) or compiles through
  `NocacheModifierCompiler` (modifiers), with a `CachingTest` proof.
- **Laravel 10 and 11 stay supported.** Don't use APIs newer than the floor without a
  guard (see `NumberPlugins` for the pattern).
- New first-party *modifiers* must be added to `StrictSecurityPolicy::$allowed_modifiers`
  — a sync test fails if you forget.

## Docs

User-facing changes need a docs touch (`docs/`) and a `CHANGELOG.md` entry under the
unreleased heading. The docs site builds with `mkdocs serve` if you have Python handy
(`pip install -r docs/requirements.txt`).
