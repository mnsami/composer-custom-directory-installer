<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller as BaseLibraryInstaller;

/**
 * Custom installer for library-type packages.
 *
 * Delegates to PackageUtils::getPackageInstallPath() to resolve custom paths
 * before falling back to the default Composer library install path.
 */
class LibraryInstaller extends BaseLibraryInstaller
{
    /**
     * Returns the installation path for a package.
     *
     * Checks for a custom path configured via extra.installer-paths in the root
     * composer.json. Falls back to the default vendor-based path if none is found.
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
