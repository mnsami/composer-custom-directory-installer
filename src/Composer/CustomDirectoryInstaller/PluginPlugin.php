<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin that registers the PluginInstaller for composer-plugin-type packages.
 *
 * Allows composer-plugin packages to be installed into custom directories as configured
 * via extra.installer-paths in the root composer.json.
 */
class PluginPlugin implements PluginInterface
{
    private ?PluginInstaller $installer = null;

    /**
     * Activates the plugin by registering the PluginInstaller.
     *
     * @param Composer    $composer The Composer instance.
     * @param IOInterface $io       The input/output interface.
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->installer = new PluginInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * Deactivates the plugin by removing the PluginInstaller.
     *
     * @param Composer    $composer The Composer instance.
     * @param IOInterface $io       The input/output interface.
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        if ($this->installer !== null) {
            $composer->getInstallationManager()->removeInstaller($this->installer);
        }
    }

    /**
     * Called when the plugin is uninstalled. No cleanup required.
     *
     * @param Composer    $composer The Composer instance.
     * @param IOInterface $io       The input/output interface.
     * @return void
     */
    public function uninstall(Composer $composer, IOInterface $io): void {}
}
