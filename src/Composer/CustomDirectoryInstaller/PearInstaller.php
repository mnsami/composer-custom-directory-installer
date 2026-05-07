<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Package\PackageInterface;
use Composer\Installer\PearInstaller as BasePearInstaller;

/**
 * Custom installer for PEAR-type packages.
 *
 * Delegates to PackageUtils::getPackageInstallPath() to resolve custom paths
 * before falling back to the default PEAR install path.
 *
 * Note: BasePearInstaller was removed in Composer 2.x. This class is kept for
 * environments that still have it available but is considered deprecated.
 */
class PearInstaller extends BasePearInstaller
{
    /**
     * Returns the installation path for a package.
     *
     * Checks for a custom path configured via extra.installer-paths in the root
     * composer.json. Falls back to the default PEAR path if none is found.
     *
     * @param PackageInterface $package The package being installed.
     * @return string The resolved installation path.
     */
    public function getInstallPath(PackageInterface $package): string
    {
        $path = PackageUtils::getPackageInstallPath($package, $this->composer);

        if ($path !== null) {
            return $path;
        }

        return parent::getInstallPath($package);
    }
}
