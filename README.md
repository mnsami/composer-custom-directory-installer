composer-installer-plugin
=========================

A composer plugin, to install differenty types of composer packages in custom directories outside the default composer default installation path which is in the `vendor` folder.

Usage
=====

First, you need to add it to your repositories section

  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:mnabil/composer-installer-plugin.git"
    }
  ]

Second, add it to your require section, here I'm taking the famous `monolog` composer pacakge to install

  "require":{
    "php": ">=5.3",
    "mnabil/composer-installer-plugin": "*",
    "monolog/monolog": "*"
  }

Third, to instruct the plugin to install the `monolog` package in a custom directory, use the `extra` arbitrary

  "extra":{
    "installer-paths":{
      "./monolog/": ["monolog/monolog"]
    }
  }

Putting it in the same like above, it will instruct composer to install `monolog` in monolog folder inside the directory `composer install` is ran from.
