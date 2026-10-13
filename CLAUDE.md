# CLAUDE.md

## Commands

All commands assume `vendor/` is installed. Use Docker via the Makefile targets if PHP is unavailable locally.

```bash
# Install dependencies
composer install
# or via Docker (builds image first)
make composer-install

# Run all tests (Unit + Integration)
vendor/bin/phpunit

# Run only the fast mock-based unit suite, or only the real-Composer integration suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
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

# Static analysis (PHPStan level max on src/, with Composer's phpstan/rules.neon)
vendor/bin/phpstan analyse
# or via Docker
docker run --rm -v "$(pwd)":/app -w /app composer-custom-directory-installer vendor/bin/phpstan analyse
```

CI runs both phpunit suites on PHP 8.1–8.5 inside `php-actions/phpunit` (the matrix PHP, not the runner's; Xdebug supplies the coverage driver and `coverage_text` prints the report), a `php-cs-fixer` dry-run job and a PHPStan job (both on the runner's PHP with `vendor/` resolved for PHP 8.1, because dependencies resolved for a newer PHP than the runner's can fail to parse), a lowest-Composer job on `composer/composer` 2.3 with PHP 8.1, and a Windows job (native PHP 8.4 via `shivammathur/setup-php`, both suites, exercising the path guards, `fnmatch()` and Composer's junction-based path repositories). PHPStan runs at level max with Composer's `phpstan/rules.neon`. A weekly scheduled run catches upstream breakage; Dependabot watches composer and github-actions.

Available Makefile targets: `composer-install`, `format`, `shell`, `build-docker-image`. There is no `make test` — use the Docker run command above directly.

The Docker image emits a `PHP Startup: Unable to load dynamic library 'zip'` warning on every run — this is harmless, the project doesn't use the zip extension.

The Docker image must be built first (`make composer-install` does this). If `composer.json` `require-dev` changes, run `docker run --rm -v "$(pwd)":/app -w /app composer-custom-directory-installer composer update` — the lock file must include dev deps or Composer classes won't load in tests.

## Architecture

This is a **Composer plugin** (`type: composer-plugin`) that installs packages into custom paths instead of the default `vendor/` directory. It registers two installers — one per Composer package type — via two corresponding plugin entry points listed in `extra.class` in `composer.json`.

### Plugin / Installer pairs

Each pair follows the same pattern: the Plugin implements `PluginInterface`, instantiates its Installer, and registers it with Composer's `InstallationManager`. The Installer overrides `getInstallPath()` by delegating to `PackageUtils`, then falling back to the parent if no custom path is configured.

| Plugin | Installer | Handles |
|---|---|---|
| `LibraryPlugin` | `LibraryInstaller` | `library` type (default); also any type listed in root `extra.installer-types` via `supports()` override |
| `PluginPlugin` | `PluginInstaller` | `composer-plugin` type |

### Path resolution (`PackageUtils`)

All path logic lives in `PackageUtils::getPackageInstallPath()`. The flow:

1. Extract `{$vendor}`, `{$name}`, `{$type}` from the package.
2. Check if the package's own `extra.installer-name` overrides `{$name}`.
3. Read `extra.installer-paths` from the **root** package's `composer.json`.
4. Call `mapCustomInstallPaths()` which uses a **three-pass** approach to enforce precedence **globally across all entries** (not per-entry):
   - Pass 1: Exact name match (`"vendor/name"`) — scans all entries first
   - Pass 2: Type prefix match (`"type:library"`) — scans all entries
   - Pass 3: Glob match via `fnmatch()` — any pattern containing `*`, `?` or `[` (`"vendor/*"`, `"acme/foo?"`) — scans all entries last; `"vendor:acme"` is rewritten to `"acme/*"` in this pass, so it has the same rank as a glob, not a tier of its own
5. Substitute `{$...}` variables with `templatePath()`; if a placeholder has a `|flags` suffix (e.g. `{$name|FP}`), apply transformations via `applyFlags()` — `F` = ucfirst, `P` = strip separators + camelCase, `U` = strtoupper. An unknown variable or flag throws `InvalidArgumentException` naming the placeholder.
6. Reject resolved paths containing `..` (traversal guard) or starting with `/`, `\` (which covers UNC `\\server\share`) or a drive letter (absolute path guard). Return the path with its trailing `/` stripped.

### Namespace

All source files use `Composer\CustomDirectoryInstaller\`. Tests use `App\Tests\Unit\`.

### Test structure

Two PHPUnit testsuites:

- `tests/Unit/` — mock-based, one file per source class, plus `ReadmeInstallerPathsTest`, which feeds every `installer-paths` example in the README through the resolver. Protected/private methods in `PackageUtils` are tested via `ReflectionMethod` (see `callProtected()` helper in `PackageUtilsTest`).
- `tests/Integration/InstallTest.php` — boots a real Composer in a temp project with `Factory::create()` + `Installer`, pulling this checkout and the packages under `tests/Integration/fixtures/` through `path` repositories (Packagist disabled), then asserts where packages land on disk. `composer/installers` is a dev dependency only so the co-install case can reference `vendor/composer/installers` the same way.

## Gotchas

**Precedence is global across entries, not per-entry.** An exact match in entry 2 beats a wildcard in entry 1. The three-pass algorithm in `mapCustomInstallPaths()` scans all entries for exact matches before considering type or wildcard matches in any entry.

**Matching is case-insensitive.** `mapCustomInstallPaths()` lowercases the package name, the type and every pattern before comparing, because Composer names and types are canonically lowercase. Non-string patterns are dropped rather than compared.

**Composer's installers hold a `PartialComposer`, not a `Composer`.** `PackageUtils::getPackageInstallPath()` accepts `PartialComposer` (which `Composer` extends) so that passing the installer's `$this->composer` type-checks at PHPStan level max.

**`installer-name` affects substitution, not matching.** A package's `extra.installer-name` overrides the `{$name}` variable used in path templates, but pattern matching still uses the original `vendor/name` from `getPrettyName()`. A pattern of `"acme/foo"` will NOT match a package named `acme/bar` even if that package sets `installer-name: foo`.

**`failOnDeprecation` is enabled in `phpunit.xml`.** The unit suite fails on any PHP deprecation notice, including those from Composer internals. New code that calls deprecated Composer API methods will break CI even if logic is correct. The integration tests opt out with `#[WithoutErrorHandler]`: Composer probes not-yet-written autoload files through its `Silencer`, and PHPUnit would count every silenced read as a warning.

**`Factory::create()` disables local plugins for any config path other than `./composer.json`.** `InstallTest` therefore `chdir()`s into the temp project and passes `null`; passing the absolute path silently runs without this plugin.

**On a fresh install, Composer installs plugins in operation (alphabetical) order.** `plugin-modifies-install-path` only orders the *loading* of already-installed plugins. A `composer-plugin` package whose name sorts before `mnsami/composer-custom-directory-installer` is installed by Composer's own `PluginInstaller` into `vendor/` on the first run; `InstallTest` installs this plugin first, then adds the plugin package. Library packages are unaffected because all plugins are installed before them. Composer 2.3 additionally leaves an empty `vendor/<vendor>/` directory behind on a fresh install: it computes download paths for the whole batch before activating this plugin, so `DownloadManager` pre-creates the default parent directory. The package itself still lands at the custom path.

**`composer/installers` in `require-dev` is an integration-test fixture, not a dependency.** Root `config.allow-plugins` sets it to `false` so Composer installs it without activating it at the repo root.

**`installer-types` is required for non-`library` types to be claimed.** A `type:` pattern in `installer-paths` alone is not enough — the type must also appear in `extra.installer-types` for `LibraryInstaller::supports()` to claim it. Without the list entry, Composer never asks the plugin to install that package at all.

**Unknown variables and flags throw.** `{$nmae}` and `{$name|XZ}` raise `InvalidArgumentException` from `templatePath()` / `applyFlags()` with the placeholder in the message. They used to resolve to an empty string or pass through unchanged, which sent every matching package into one directory.
