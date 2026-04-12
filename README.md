composer-custom-directory-installer
===================================

A Composer plugin to install packages in custom directories outside the default `vendor` folder.

This is not another `composer-installer` library for supporting non-composer package types such as `application`. It only adds flexibility for installing standard `composer` package types in custom paths.

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

`installer-paths` supports three matching strategies, evaluated in order of precedence:

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

Resolved install paths are validated to prevent directory traversal attacks. A path resolving to a value that contains `..` will throw an `InvalidArgumentException`.

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

Existing `installer-paths` configurations (exact package names) are fully backwards-compatible and require no changes.

Note
----

Composer `type: project` is not supported by this installer, as packages with type `project` only make sense to be used with application shells like `symfony/framework-standard-edition`.
