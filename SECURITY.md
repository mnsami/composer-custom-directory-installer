# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 2.x     | Yes, on the latest minor release |
| 1.x     | No |

## Reporting a vulnerability

Report vulnerabilities privately through GitHub's private vulnerability reporting:
https://github.com/mnsami/composer-custom-directory-installer/security/advisories/new

Do not open a public issue for a security report. Reports are acknowledged as soon as
possible and fixed in a patch release; reporters are credited in the advisory unless
they ask otherwise.

## What this plugin does, and what it guards

This is a Composer plugin: it runs inside `composer install` and `composer update`
once your project allows it in `config.allow-plugins`, and its only job is to decide
the directory a package is installed into. Every resolved path is validated after
`{$variable}` substitution:

- A path containing `..` is rejected (directory traversal guard).
- An absolute path is rejected: a leading `/` or `\`, a UNC prefix such as
  `\\server\share`, or a Windows drive letter.
- An unknown `{$variable}` or flag throws instead of resolving to an empty segment,
  so a typo cannot send several packages into the same directory.

Two things remain the project's responsibility. Composer empties a package's target
directory before installing into it, so no two packages may resolve to one directory:
give every multi-package pattern (`type:`, `vendor:`, `acme/*`) a distinguishing
variable such as `{$name}`. And a dependency's own `extra.installer-name` is
substituted into `{$name}`; the guards above apply to it, but review it when you
route packages you do not control.
