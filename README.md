composer-custom-directory-installer
===================================

A composer plugin to install different types of Composer packages in custom directories outside the default `vendor` folder.

This is not another `composer-installer` library for supporting non-composer package types i.e. `application` .. etc. This is only to add the flexibility of installing composer packages outside the vendor folder. This package only supports `composer` package types,

https://getcomposer.org/doc/04-schema.md#type

> The type of the package. It defaults to library.
>
> Package types are used for custom installation logic. If you have a package that needs some special logic, you can define a custom type. This could be a symfony-bundle, a wordpress-plugin or a typo3-module. These types will all be specific to certain projects, and they will need to provide an installer capable of installing packages of that type.

Requirements
------------

- PHP >= 8.0
- Composer 2.x

How to use
----------

- Include the composer plugin into your `composer.json` `require` section:

```json
"require": {
  "php": ">=8.0",
  "mnsami/composer-custom-directory-installer": "2.*",
  "monolog/monolog": "*"
}
```

- In the `extra` section define the custom directory you want the package to be installed in:

```json
"extra": {
  "installer-paths": {
    "./monolog/": ["monolog/monolog"]
  }
}
```

By adding the `installer-paths` part, you are telling composer to install the `monolog` package inside the `monolog` folder in your root directory.

Path Variables
--------------

You can use the following variables in your `installer-paths` to build dynamic paths:

| Variable    | Description                                      | Example value      |
|-------------|--------------------------------------------------|--------------------|
| `{$vendor}` | The vendor portion of the package name           | `monolog`          |
| `{$name}`   | The package name (or `installer-name` override)  | `monolog`          |
| `{$type}`   | The Composer package type                        | `library`          |

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

The `installer-paths` configuration supports three matching strategies, applied in order of precedence:

### 1. Exact package name (highest precedence)

```json
"installer-paths": {
  "./libs/monolog/": ["monolog/monolog"]
}
```

### 2. Package type prefix

Match all packages of a given Composer type using the `type:` prefix:

```json
"installer-paths": {
  "./wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
}
```

### 3. Wildcard vendor glob (lowest precedence)

Match all packages from a vendor using `*`:

```json
"installer-paths": {
  "./acme-libs/{$name}/": ["acme/*"]
}
```

Custom `installer-name`
-----------------------

You can override the `{$name}` variable for a specific package by setting `installer-name` in its `extra` section:

```json
"extra": {
  "installer-name": "my-custom-name"
}
```

Security
--------

Resolved install paths are validated to prevent directory traversal attacks. A path containing `..` will throw an `InvalidArgumentException`.

Note
----

Composer `type: project` is not supported in this installer, as packages with type `project` only make sense to be used with application shells like `symfony/framework-standard-edition`, to be required by another package.
