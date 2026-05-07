# CLAUDE.md

## Commands

All commands assume `vendor/` is installed. Use Docker via the Makefile targets if PHP is unavailable locally.

```bash
# Install dependencies
composer install
# or via Docker (builds image first)
make composer-install

# Run tests
vendor/bin/phpunit
# or via Docker (no local PHP needed)
docker run --rm -v "$(pwd)":/app -w /app composer-custom-directory-installer vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit tests/Unit/PackageUtilsTest.php

# Check code style (dry-run)
vendor/bin/php-cs-fixer fix src --dry-run --diff --config .php-cs-fixer.dist.php

# Auto-fix code style
vendor/bin/php-cs-fixer fix src
# or via Docker
make format

# Check code style via Docker (dry-run, CI-equivalent)
docker run --rm -v "$(pwd)":/app -w /app composer-custom-directory-installer vendor/bin/php-cs-fixer fix src --dry-run --diff --config .php-cs-fixer.dist.php
```

CI runs `phpunit` + `php-cs-fixer` dry-run against PHP 8.1, 8.2, and 8.3.

Available Makefile targets: `composer-install`, `format`, `shell`, `build-docker-image`. There is no `make test` — use the Docker run command above directly.

The Docker image emits a `PHP Startup: Unable to load dynamic library 'zip'` warning on every run — this is harmless, the project doesn't use the zip extension.

The Docker image must be built first (`make composer-install` does this). If `composer.json` `require-dev` changes, run `docker run --rm -v "$(pwd)":/app -w /app composer-custom-directory-installer composer update` — the lock file must include dev deps or Composer classes won't load in tests.

## Architecture

This is a **Composer plugin** (`type: composer-plugin`) that installs packages into custom paths instead of the default `vendor/` directory. It registers three installers — one per Composer package type — via three corresponding plugin entry points listed in `extra.class` in `composer.json`.

### Plugin / Installer pairs

Each pair follows the same pattern: the Plugin implements `PluginInterface`, instantiates its Installer, and registers it with Composer's `InstallationManager`. The Installer overrides `getInstallPath()` by delegating to `PackageUtils`, then falling back to the parent if no custom path is configured.

| Plugin | Installer | Handles |
|---|---|---|
| `LibraryPlugin` | `LibraryInstaller` | `library` type (default) |
| `PearPlugin` | `PearInstaller` | `pear-library` type (deprecated in Composer 2.x) |
| `PluginPlugin` | `PluginInstaller` | `composer-plugin` type |

### Path resolution (`PackageUtils`)

All path logic lives in `PackageUtils::getPackageInstallPath()`. The flow:

1. Extract `{$vendor}`, `{$name}`, `{$type}` from the package.
2. Check if the package's own `extra.installer-name` overrides `{$name}`.
3. Read `extra.installer-paths` from the **root** package's `composer.json`.
4. Call `mapCustomInstallPaths()` which uses a **three-pass** approach to enforce precedence **globally across all entries** (not per-entry):
   - Pass 1: Exact name match (`"vendor/name"`) — scans all entries first
   - Pass 2: Type prefix match (`"type:library"`) — scans all entries
   - Pass 3: Wildcard glob match (`"vendor/*"`) — scans all entries last
5. Substitute `{$...}` variables with `templatePath()`.
6. Reject resolved paths containing `..` (traversal guard) or starting with `/` or a drive letter (absolute path guard).

### Namespace

All source files use `Composer\CustomDirectoryInstaller\`. Tests use `App\Tests\Unit\`.

### Test structure

Tests live in `tests/Unit/` — one file per source class. Protected/private methods in `PackageUtils` are tested via `ReflectionMethod` (see `callProtected()` helper in `PackageUtilsTest`).

## Gotchas

**Precedence is global across entries, not per-entry.** An exact match in entry 2 beats a wildcard in entry 1. The three-pass algorithm in `mapCustomInstallPaths()` scans all entries for exact matches before considering type or wildcard matches in any entry.

**`installer-name` affects substitution, not matching.** A package's `extra.installer-name` overrides the `{$name}` variable used in path templates, but pattern matching still uses the original `vendor/name` from `getPrettyName()`. A pattern of `"acme/foo"` will NOT match a package named `acme/bar` even if that package sets `installer-name: foo`.

**`PearPlugin` is always a no-op in Composer 2.x.** `Composer\Installer\PearInstaller` was removed in Composer 2.x. `PearPlugin::activate()` checks `class_exists()` and skips registration if the class is absent, so PEAR tests are always skipped in the dev environment. `$installer` is nullable (`?PearInstaller = null`) and `deactivate()` guards against it being null.

**`failOnDeprecation` is enabled in `phpunit.xml`.** The test suite fails on any PHP deprecation notice, including those from Composer internals. New code that calls deprecated Composer API methods will break CI even if logic is correct.
