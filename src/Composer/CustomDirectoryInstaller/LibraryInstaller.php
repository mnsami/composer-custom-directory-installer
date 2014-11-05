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
              $nameParts = explode('/',$packageName);
              $search = array(
                  '{$packageName}'
              );
              $replace = array(
                  $packageName
              );
              if(count($nameParts) >= 2)
              {
                  $search = array_merge($search, array('{$vendor}','{$name}'));
                  $replace = array_merge($replace,$nameParts);
              }
              $path = str_replace($search, $replace, $path);
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
