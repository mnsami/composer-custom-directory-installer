# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `vendor:acme` in `installer-paths` as an alias of `acme/*`, the spelling used by composer/installers and oomphinc/composer-installers-extender ([#58](https://github.com/mnsami/composer-custom-directory-installer/issues/58)).
- `?` and `[...]` glob syntax in patterns: any pattern containing `*`, `?` or `[` is matched with `fnmatch()` ([#62](https://github.com/mnsami/composer-custom-directory-installer/issues/62)).
- `extra.plugin-modifies-install-path: true`, so Composer loads this plugin before other plugins ([#55](https://github.com/mnsami/composer-custom-directory-installer/issues/55)).
- Integration test suite that boots a real Composer against fixture packages; CI on PHP 8.1–8.5, Windows and the lowest supported Composer, PHPStan at level max, Dependabot, weekly scheduled run ([#63](https://github.com/mnsami/composer-custom-directory-installer/issues/63), [#64](https://github.com/mnsami/composer-custom-directory-installer/issues/64)).
- README: Troubleshooting section, precedence and `vendor:` notes in the oomphinc migration guide, guidance on directories left behind after a path change ([#56](https://github.com/mnsami/composer-custom-directory-installer/issues/56), [#58](https://github.com/mnsami/composer-custom-directory-installer/issues/58), [#60](https://github.com/mnsami/composer-custom-directory-installer/issues/60)).
- `CHANGELOG.md`, `SECURITY.md`, `.gitattributes` export-ignore for dist archives, and `homepage`, `support`, `funding` in `composer.json` ([#65](https://github.com/mnsami/composer-custom-directory-installer/issues/65)).

### Changed
- Minimum Composer is 2.3 (`composer-plugin-api ^2.3`). Composer 2.0–2.2 declare `supports()` untyped and the plugin fataled at activation there; the resolver now refuses to install it instead ([#54](https://github.com/mnsami/composer-custom-directory-installer/issues/54)).
- Pattern matching is case-insensitive, and the resolved path no longer carries a trailing slash ([#62](https://github.com/mnsami/composer-custom-directory-installer/issues/62)).
- **Behaviour change:** an unknown `{$variable}` or an unknown flag in a path template throws `InvalidArgumentException` naming the placeholder. Previously an unknown variable resolved to an empty string and an unknown flag was ignored, which sent every matching package into one directory ([#59](https://github.com/mnsami/composer-custom-directory-installer/issues/59)).
- `installer-name` must be a string to override `{$name}`; `PackageUtils::getPackageInstallPath()` accepts the `PartialComposer` that Composer's installers hold ([#64](https://github.com/mnsami/composer-custom-directory-installer/issues/64)).

### Removed
- Dead PEAR support: `PearPlugin`, `PearInstaller` and the `PearPlugin` entry point in `extra.class`. `Composer\Installer\PearInstaller` does not exist in any Composer 2, so the plugin never registered anything ([#61](https://github.com/mnsami/composer-custom-directory-installer/issues/61)).

### Fixed
- A non-array `extra.installer-types` (a plain string) threw a `TypeError` on every Composer command; a string is now accepted as a single type ([#57](https://github.com/mnsami/composer-custom-directory-installer/issues/57)).
- The absolute-path guard also rejects backslash-leading and UNC paths ([#62](https://github.com/mnsami/composer-custom-directory-installer/issues/62)).
- README examples `/tools/{$name}/` and `../modules/{$name}/` were rejected by the path guards, and the "Why" section wrongly claimed packages must depend on composer/installers ([#56](https://github.com/mnsami/composer-custom-directory-installer/issues/56)).

## [2.2.1] - 2026-05-20

### Added
- README: table of contents, "Why this plugin?" section, real-world use cases, oomphinc/composer-installers-extender migration guide; Packagist keywords.

### Fixed
- Persistent warning from `PearPlugin` on every command under Composer 2.x.

## [2.2.0] - 2026-05-07

### Added
- `extra.installer-types`: route packages of any type, not only `library`.
- Formatting flags in path placeholders, `{$name|FP}` and friends: `F` (ucfirst), `P` (strip separators, camelCase), `U` (uppercase).
- README documentation for both features; `CLAUDE.md` for contributors.

### Changed
- Precedence is enforced globally across all `installer-paths` entries: exact package name first, then `type:`, then wildcard, regardless of entry order.
- Version removed from `composer.json`; branch alias `2.2.x-dev`.

### Security
- Resolved paths containing `..` or starting with `/` or a drive letter are rejected.

## [2.1.0] - 2026-05-07

### Added
- `type:` matching, `vendor/*` wildcard matching and the `{$type}` path variable.
- PHPUnit test suite, GitHub Actions CI, Docker-based development environment.

### Changed
- Requires PHP >= 8.1 and Composer 2 (`composer-plugin-api ^2.0`); PHP 5.3–8.0 and Composer 1 are no longer supported.

### Fixed
- `PearPlugin` class check under Composer 2.

## [2.0.0] - 2020-08-18

### Added
- Composer 2 compatibility (`composer-plugin-api ^1.0 || ^2.0`).

[Unreleased]: https://github.com/mnsami/composer-custom-directory-installer/compare/2.2.1...HEAD
[2.2.1]: https://github.com/mnsami/composer-custom-directory-installer/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/mnsami/composer-custom-directory-installer/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/mnsami/composer-custom-directory-installer/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/mnsami/composer-custom-directory-installer/releases/tag/2.0.0
