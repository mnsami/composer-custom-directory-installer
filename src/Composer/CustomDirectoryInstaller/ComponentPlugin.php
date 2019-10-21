<?php
namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\CustomDirectoryInstaller\PluginInstaller;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\CustomDirectoryInstaller\ComponentInstaller;

class ComponentPlugin implements PluginInterface
{
    public function activate (Composer $composer, IOInterface $io)
    {
        $installer = new ComponentInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
