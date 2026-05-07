composer-custom-directory-installer
===================================

A Composer plugin to install packages in custom directories outside the default `vendor` folder.

This is not another `composer-installer` library for supporting non-composer package types such as `application`. By default it handles `library`-type packages, but you can extend it to any Composer package type via `extra.installer-types` in your root `composer.json`.

https://getcomposer.org/doc/04-schema.md#type

> The type of the package. It defaults to library.
>
> Package types are used for custom installation logic. If you have a package that needs some special logic, you can define a custom type. This could be a symfony-bundle, a wordpress-plugin or a typo3-module. These types will all be specific to certain projects, and they will need to provide an installer capable of installing packages of that type.

Requirements
------------

- PHP >= 8.1
- Composer 2.x

Installation
------------

Add the plugin to the `require` section of your `composer.json`:

```json
"require": {
    "mnsami/composer-custom-directory-installer": "^2.1"
}
```

**Important — Composer 2.2+ plugin trust:**  
Composer 2.2 and later require you to explicitly allow third-party plugins. Add the following to your `composer.json`:

```json
"config": {
    "allow-plugins": {
        "mnsami/composer-custom-directory-installer": true
    }
}
```

Without this, Composer will either prompt interactively or block the plugin entirely in non-interactive (CI) environments.

How to use
----------

In the `extra` section of your root `composer.json`, define the custom directory for each package:

```json
"extra": {
    "installer-paths": {
        "./monolog/": ["monolog/monolog"]
    }
}
```

This tells Composer to install `monolog/monolog` into the `./monolog/` directory instead of `vendor/monolog/monolog`.

Path Variables
--------------

You can use the following variables in your `installer-paths` to build dynamic paths:

| Variable    | Description                                     | Example value      |
|-------------|-------------------------------------------------|--------------------|
| `{$vendor}` | The vendor portion of the package name          | `monolog`          |
| `{$name}`   | The package name (or `installer-name` override) | `monolog`          |
| `{$type}`   | The Composer package type                       | `library`          |

### Path Variable Flags

You can append transformation flags after a pipe (`|`) to modify how a variable is substituted:

```
{$token|flags}
```

Flags are applied **left-to-right** in the order given:

| Flag | Transformation | Example input | Example output |
|------|----------------|---------------|----------------|
| `F`  | Capitalize first letter (`ucfirst`) | `my-package` | `My-package` |
| `P`  | Strip hyphens/underscores and capitalize each following word | `my-package` | `myPackage` |
| `U`  | Uppercase all characters | `my-package` | `MY-PACKAGE` |

Flags can be combined. `FP` together produces **PascalCase** (capitalize first + strip separators):

| Expression | Input | Output |
|------------|-------|--------|
| `{$name\|F}` | `my-package` | `My-package` |
| `{$name\|P}` | `my-package` | `myPackage` |
| `{$name\|FP}` | `my-package` | `MyPackage` |
| `{$name\|U}` | `my-package` | `MY-PACKAGE` |
| `{$vendor\|U}` | `acme` | `ACME` |

**Example:**

```json
"installer-paths": {
    "src/{$vendor|U}/{$name|FP}/": ["acme/my-package"],
    "modules/{$name|FP}/":         ["type:drupal-module"]
}
```

For a package `acme/my-package` (type `library`), this resolves to `src/ACME/MyPackage/`.

```json
"extra": {
    "installer-paths": {
        "./customlibs/{$vendor}/db/{$name}": ["doctrine/orm"],
        "./custom/{$type}/{$vendor}/{$name}": ["acme/*"]
    }
}
```

Matching Strategies
-------------------

`installer-paths` supports three matching strategies. Precedence is evaluated **globally across all entries**: all entries are first scanned for an exact name match, then for a type match, then for a wildcard match. An exact match in a later-listed entry always wins over a wildcard match in an earlier-listed entry.

### 1. Exact package name (highest precedence)

Matches one specific package:

```json
"installer-paths": {
    "./libs/monolog/": ["monolog/monolog"]
}
```

### 2. Package type prefix

Matches all packages of a given Composer type using the `type:` prefix:

```json
"installer-paths": {
    "./wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
}
```

### 3. Wildcard vendor glob (lowest precedence)

Matches all packages from a given vendor using `*`:

```json
"installer-paths": {
    "./acme-libs/{$name}/": ["acme/*"]
}
```

Custom `installer-name`
-----------------------

A package can override the `{$name}` variable by setting `installer-name` in its own `extra` section (inside the *package's* `composer.json`, not the root project):

```json
"extra": {
    "installer-name": "my-custom-name"
}
```

When set, `{$name}` in the path template will resolve to `my-custom-name` instead of the package's actual name.

Supporting Custom Package Types
--------------------------------

By default the plugin only handles the `library` package type. To install packages of other types (e.g. `drupal-module`, `wordpress-plugin`) into custom directories, declare those types in `extra.installer-types`:

```json
"extra": {
    "installer-types": ["drupal-module", "wordpress-plugin"],
    "installer-paths": {
        "web/modules/{$name}/": ["type:drupal-module"],
        "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
}
```

The plugin will claim any package whose type appears in `installer-types` and apply the matching `installer-paths` rule. Without this list, packages of non-`library` types are left to Composer's default installer.

Complete example
----------------

```json
{
    "require": {
        "mnsami/composer-custom-directory-installer": "^2.1",
        "monolog/monolog": "*",
        "acme/foo": "*",
        "acme/bar": "*"
    },
    "config": {
        "allow-plugins": {
            "mnsami/composer-custom-directory-installer": true
        }
    },
    "extra": {
        "installer-types": ["wordpress-plugin"],
        "installer-paths": {
            "./logger/":              ["monolog/monolog"],
            "./acme/{$name}/":        ["acme/*"],
            "./plugins/{$name}/":     ["type:wordpress-plugin"]
        }
    }
}
```

Security
--------

Resolved install paths are validated against two attack vectors:

- **Directory traversal** — a resolved path containing `..` throws an `InvalidArgumentException`.
- **Absolute path injection** — a resolved path that is absolute (starting with `/` or a Windows drive letter) throws an `InvalidArgumentException`. This can occur when a package's `installer-name` is set to an absolute path value.

Both checks apply after all `{$variable}` substitutions are complete.

Upgrading from v1.x
--------------------

| | v1.x | v2.x |
|---|---|---|
| PHP | >= 5.3 | >= 8.1 |
| Composer | 1.x / 2.x | 2.x only |
| Require string | `"1.*"` | `"^2.1"` |
| `type:` matching | No | Yes |
| Wildcard `vendor/*` | No | Yes |
| `{$type}` variable | No | Yes |
| `allow-plugins` needed | No | Yes (Composer 2.2+) |
| `installer-types` support | No | Yes |
| Path variable flags (`\|F`, `\|P`, `\|U`) | No | Yes |

Existing `installer-paths` configurations (exact package names) are fully backwards-compatible and require no changes.

Note
----

Composer `type: project` is not supported by this installer, as packages with type `project` only make sense to be used with application shells like `symfony/framework-standard-edition`.
