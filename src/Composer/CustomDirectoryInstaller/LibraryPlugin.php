<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin that registers the LibraryInstaller for library-type packages.
 *
 * Allows library packages to be installed into custom directories as configured
 * via extra.installer-paths in the root composer.json.
 */
class LibraryPlugin implements PluginInterface
{
    private LibraryInstaller $installer;

    /**
     * Activates the plugin by registering the LibraryInstaller.
     *
     * @param Composer    $composer The Composer instance.
     * @param IOInterface $io       The input/output interface.
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->installer = new LibraryInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * Deactivates the plugin by removing the LibraryInstaller.
     *
     * @param Composer    $composer The Composer instance.
     * @param IOInterface $io       The input/output interface.
     * @return void
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $composer->getInstallationManager()->removeInstaller($this->installer);
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
