<?php

namespace App\Tests\Unit;

use Composer\Composer;
use Composer\CustomDirectoryInstaller\PearPlugin;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;

class PearPluginTest extends TestCase
{
    public function testActivateSkipsRegistrationAndLogsWarningWhenPearInstallerUnavailable(): void
    {
        if (class_exists('Composer\Installer\PearInstaller')) {
            $this->markTestSkipped('BasePearInstaller is present in this environment; cannot test the skip path.');
        }

        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager->expects($this->never())->method('addInstaller');

        $composer = $this->createMock(Composer::class);
        $composer->method('getInstallationManager')->willReturn($installationManager);

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())
            ->method('writeError')
            ->with($this->stringContains('PearInstaller'));

        $plugin = new PearPlugin();
        $plugin->activate($composer, $io);
    }

    public function testDeactivateDoesNothingWhenInstallerWasNeverRegistered(): void
    {
        if (class_exists('Composer\Installer\PearInstaller')) {
            $this->markTestSkipped('BasePearInstaller is present in this environment.');
        }

        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager->expects($this->never())->method('removeInstaller');

        $composer = $this->createMock(Composer::class);
        $composer->method('getInstallationManager')->willReturn($installationManager);

        $io = $this->createMock(IOInterface::class);
        $io->method('writeError');

        $plugin = new PearPlugin();
        $plugin->activate($composer, $io);
        $plugin->deactivate($composer, $io);
    }

    public function testUninstallIsNoop(): void
    {
        $plugin = new PearPlugin();
        $plugin->uninstall(
            $this->createMock(Composer::class),
            $this->createMock(IOInterface::class)
        );
        $this->expectNotToPerformAssertions();
    }
}
