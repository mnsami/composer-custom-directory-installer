<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller as BaseLibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class LibraryInstaller extends BaseLibraryInstaller
{
  public function getInstallPath(PackageInterface $package)
  {
    $names = $package->getNames();

    if ($this->composer->getPackage()) 
    {
      $extra = $this->composer->getPackage()->getExtra();
      if(!empty($extra['installer-paths']))
      {
        foreach($extra['installer-paths'] as $path => $packageNames)
        {
          foreach($packageNames as $packageName)
          {
            if (in_array(strtolower($packageName), $names)) {
              return $path;
            }
          }
        }
      }
    }

    /*
     * In case, the user didn't provide a custom path
     * use the default one, by calling the parent::getInstallPath function
     */
    return parent::getInstallPath($package);
  }
}
