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
              if($matched = preg_match_all("/\{(.*?)\}/is",$path,$matches,PREG_PATTERN_ORDER)) {
                  $packageParts = explode('/',$packageName);
                  foreach($matches[1] as $pattern)
                  {
                      $patternParts = explode('|', $pattern);
                      $psr = false;
                      if(count($patternParts) > 1)
                      {
                          if(strstr($patternParts[1],'P'))
                          {
                              $psr = true;
                          }
                      }
                      switch($patternParts[0])
                      {
                          case '$package':
                              $value = $packageName;
                              break;
                          case '$name':
                              if(count($packageParts) > 1) {
                                  $value = $packageParts[1];
                              } else {
                                  $value = 'undefined';
                              }
                              break;
                          case '$vendor':
                              if(count($packageParts) > 1) {
                                  $value = $packageParts[0];
                              } else {
                                  $value = 'undefined';
                              }
                              break;
                      }
                      if($psr === true)
                      {
                          $value = ucfirst($value);
                      }

                      $path = str_replace('{' . $pattern . '}', $value, $path);
                  }
              }

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
