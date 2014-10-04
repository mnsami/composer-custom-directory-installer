composer-custom-directory-installer
===================================

A composer plugin, to install differenty types of composer packages in custom directories outside the default composer default installation path which is in the `vendor` folder.

Installation
------------

- Include the composer plugin into your `composer.json` `require` section::

```
  "require":{
    "php": ">=5.3",
    "mnsami/composer-custom-directory-installer": "1.0.*",
    "monolog/monolog": "*"
  }
```

- In the `extra` section define the custom directory you want to the package to be installed in::

```
  "extra":{
    "installer-paths":{
      "./monolog/": ["monolog/monolog"]
    }
```

 by adding the `installer-paths` part, you are telling composer to install the `monolog` package inside the `monolog` folder in your root directory.
