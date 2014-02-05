<?php

namespace ComposerInstallerPlugin\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use ComposerInstallerPlugin\Composer\LibraryInstaller;

class LibraryPlugin implements PluginInterface
{
  public function activate (Composer $composer, IOInterface $io)
  {
    $installer = new LibraryInstaller($io, $composer);
    $composer->getInstallationManager()->addInstaller($installer);
  }
}
