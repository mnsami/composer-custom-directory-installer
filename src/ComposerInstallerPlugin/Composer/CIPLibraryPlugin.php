<?php

namespace ComposerInstallerPlugin\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use ComposerInstallerPlugin\Composer\CIPLibraryInstaller;

class CIPLibraryPlugin implements PluginInterface
{
  public function activate (Composer $composer, IOInterface $io)
  {
    $installer = new CIPLibraryInstaller($io, $composer);
    $composer->getInstallationManager()->addInstaller($installer);
  }
}
