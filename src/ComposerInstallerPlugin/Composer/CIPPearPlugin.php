<?php

namespace ComposerInstallerPlugin\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use ComposerInstallerPlugin\Composer\CIPPearInstaller;

class CIPPearPlugin implements PluginInterface
{
  public function activate (Composer $composer, IOInterface $io)
  {
    $installer = new CIPPearInstaller($io, $composer);
    $composer->getInstallationManager()->addInstaller($installer);
  }
}
