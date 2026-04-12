<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin that registers the PearInstaller for PEAR-type packages.
 *
 * Note: PEAR installer support was removed in Composer 2.x. This plugin will log
 * a deprecation warning and skip registration when running under Composer 2.x.
 */
class PearPlugin implements PluginInterface
{
    private ?PearInstaller $installer = null;

    /**
     * Activates the plugin by registering the PearInstaller.
     *
     * Logs a warning and skips registration if PearInstaller is not available
     * (e.g. when running under Composer 2.x).
     *
     * @param Composer    $composer The Composer instance.
     * @param IOInterface $io       The input/output interface.
     * @return void
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        if (!class_exists('Composer\Installer\PearInstaller')) {
            $io->writeError('<warning>PearInstaller is not available (removed in Composer 2.x); PearPlugin skipping registration.</warning>');

            return;
        }
        $this->installer = new PearInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * Deactivates the plugin by removing the PearInstaller (if registered).
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
