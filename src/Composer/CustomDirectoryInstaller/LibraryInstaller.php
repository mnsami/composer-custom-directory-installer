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

        // A bare string is accepted as a single type; other scalars cast to a list that matches nothing.
        $installerTypes = (array) ($this->composer->getPackage()->getExtra()['installer-types'] ?? []);

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
