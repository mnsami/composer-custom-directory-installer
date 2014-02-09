<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Package\PackageInterface;
use Composer\Installer\PearInstaller as BasePearInstaller;

class PearInstaller extends BasePearInstaller
{
  public function getInstallPath(PackageInterface $package)
  {
    $names = $package->getNames();

    echo "\nNames: [" . implode(" - ", $names) . "]\n\n";

    if ($this->composer->getPackage()) 
    {
      $extra = $this->composer->getPackage()->getExtra();
      if(!empty($extra['installer-paths']))
      {
        foreach($extra['installer-paths'] as $path => $packageNames)
        {
          echo "Path: " . $path . "\n";
          echo "package name: " . var_export($packageNames, true) . "\n";
          foreach($packageNames as $packageName)
          {
            if (in_array($packageName, $names)) {
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
