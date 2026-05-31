# Development

The package is developed with Orchestra Testbench.

```bash
composer install
vendor/bin/phpunit
```

Tests cover engine rendering, the Smarty-before-Blade extension priority, the resolver wiring, `composing:`/`creating:` event firing for parents and includes, the built-in plugins, paginator integration, and end-to-end `.tpl` source-line attribution for runtime and compile errors.

## Static analysis & code style

Three tools run on every pull request via the `Static analysis & code style` CI job, and are available locally via composer scripts:

| Command                  | Tool                                              | Purpose                                                                    |
|--------------------------|---------------------------------------------------|----------------------------------------------------------------------------|
| `composer analyse`       | [Larastan](https://github.com/larastan/larastan)  | PHPStan + Laravel rules, currently at level 9 (see `phpstan.neon`).        |
| `composer rector:check`  | [Rector](https://github.com/rectorphp/rector) + [rector-laravel](https://github.com/driftingly/rector-laravel) | Dry-run automated refactors using version-agnostic quality sets only — Laravel level sets are intentionally excluded so we don't rewrite code into a Laravel-13-only shape and break older support. |
| `composer pint:check`    | [Laravel Pint](https://github.com/laravel/pint)   | Default Laravel preset, no `pint.json` overrides.                          |

Apply fixes locally with `composer rector` and `composer pint`. The CI job runs all three with `--test` / `--dry-run`, so any drift fails the build.
