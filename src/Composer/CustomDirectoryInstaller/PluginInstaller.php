<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Package\PackageInterface;
use Composer\Installer\PluginInstaller as BasePluginInstaller;

/**
 * Custom installer for composer-plugin-type packages.
 *
 * Delegates to PackageUtils::getPackageInstallPath() to resolve custom paths
 * before falling back to the default Composer plugin install path.
 */
class PluginInstaller extends BasePluginInstaller
{
    /**
     * Returns the installation path for a package.
     *
     * Checks for a custom path configured via extra.installer-paths in the root
     * composer.json. Falls back to the default plugin path if none is found.
     *
     * @param PackageInterface $package The package being installed.
     * @return string The resolved installation path.
     */
    public function getInstallPath(PackageInterface $package): string
    {
        $path = PackageUtils::getPackageInstallPath($package, $this->composer);

        if (!empty($path)) {
            return $path;
        }

        /*
         * In case, the user didn't provide a custom path
         * use the default one, by calling the parent::getInstallPath function
         */
        return parent::getInstallPath($package);
    }
}
