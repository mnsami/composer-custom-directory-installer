<?php

namespace Acme;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Minimal fixture: a package of type composer-plugin that does nothing.
 */
class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }
}
