# Security Policy

> Looking for how to *sandbox templates*? That's the
> [Security policy documentation](https://vusys.github.io/laravel-smarty/security/) —
> this file is about reporting vulnerabilities in the package itself.

## Supported versions

Pre-1.0, only the latest minor release receives security fixes. Update to the newest
`0.x` before reporting.

## Reporting a vulnerability

Please report suspected vulnerabilities privately via
[GitHub Security Advisories](https://github.com/Vusys/laravel-smarty/security/advisories/new)
— do **not** open a public issue for anything you believe is exploitable.

Include what you can of: the affected version, a minimal template/config that reproduces
the issue, and the impact you believe it has (XSS, sandbox escape, information
disclosure, …).

You can expect an acknowledgement within a few days. Fixes ship as a patch release with
a changelog entry crediting the reporter (unless you'd rather not be named).

## Scope notes

- The default configuration (`escape_html` on, no security policy) treats template
  *authors* as trusted. Bypasses of the Strict policy's sandbox by an untrusted template
  author are in scope; "a trusted template can run code" is by design.
- Vulnerabilities in Smarty itself should go to the
  [smarty-php/smarty](https://github.com/smarty-php/smarty/security) project — though
  feel free to flag here as well if the package's integration makes it worse.
