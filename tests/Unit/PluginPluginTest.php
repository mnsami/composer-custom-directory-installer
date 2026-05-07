<?php

namespace App\Tests\Unit;

use Composer\Composer;
use Composer\Config;
use Composer\CustomDirectoryInstaller\PluginInstaller;
use Composer\CustomDirectoryInstaller\PluginPlugin;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\TestCase;

class PluginPluginTest extends TestCase
{
    private function makeComposer(InstallationManager $installationManager): Composer
    {
        $config = new Config();

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([]);

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getPackage')->willReturn($rootPackage);
        $composer->method('getInstallationManager')->willReturn($installationManager);

        return $composer;
    }

    public function testActivateRegistersPluginInstaller(): void
    {
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager->expects($this->once())
            ->method('addInstaller')
            ->with($this->isInstanceOf(PluginInstaller::class));

        $composer = $this->makeComposer($installationManager);
        $io = $this->createMock(IOInterface::class);

        $plugin = new PluginPlugin();
        $plugin->activate($composer, $io);
    }

    public function testDeactivateRemovesPluginInstaller(): void
    {
        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager->expects($this->once())->method('addInstaller');
        $installationManager->expects($this->once())
            ->method('removeInstaller')
            ->with($this->isInstanceOf(PluginInstaller::class));

        $composer = $this->makeComposer($installationManager);
        $io = $this->createMock(IOInterface::class);

        $plugin = new PluginPlugin();
        $plugin->activate($composer, $io);
        $plugin->deactivate($composer, $io);
    }

    public function testUninstallIsNoop(): void
    {
        $plugin = new PluginPlugin();
        $plugin->uninstall(
            $this->createMock(Composer::class),
            $this->createMock(IOInterface::class)
        );
        $this->expectNotToPerformAssertions();
    }
}
