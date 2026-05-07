<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller as BaseLibraryInstaller;

class LibraryInstaller extends BaseLibraryInstaller
{
    public function supports(string $packageType): bool
    {
        if (parent::supports($packageType)) {
            return true;
        }

        $installerTypes = $this->composer->getPackage()->getExtra()['installer-types'] ?? [];

        return in_array($packageType, $installerTypes, true);
    }

    public function getInstallPath(PackageInterface $package): string
    {
        $path = PackageUtils::getPackageInstallPath($package, $this->composer);

        if ($path !== null) {
            return $path;
        }

        return parent::getInstallPath($package);
    }
}
