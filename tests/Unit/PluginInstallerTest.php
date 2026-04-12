<?php

namespace App\Tests\Unit;

use Composer\Composer;
use Composer\Config;
use Composer\CustomDirectoryInstaller\PluginInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\TestCase;

class PluginInstallerTest extends TestCase
{
    private function makePackage(string $prettyName, array $extra = [], string $type = 'composer-plugin'): PackageInterface
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getPrettyName')->willReturn($prettyName);
        $package->method('getExtra')->willReturn($extra);
        $package->method('getType')->willReturn($type);

        return $package;
    }

    private function makeComposer(array $rootExtra): Composer
    {
        $config = new Config();

        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn($rootExtra);

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getPackage')->willReturn($rootPackage);

        return $composer;
    }

    public function testGetInstallPathReturnsCustomPath(): void
    {
        $package = $this->makePackage('acme/myplugin');
        $composer = $this->makeComposer(['installer-paths' => ['custom-plugins/{$vendor}/{$name}' => ['acme/myplugin']]]);
        $io = $this->createMock(IOInterface::class);

        $installer = new PluginInstaller($io, $composer);
        $path = $installer->getInstallPath($package);

        $this->assertSame('custom-plugins/acme/myplugin', $path);
    }

    public function testGetInstallPathFallsBackToParentWhenNoCustomPath(): void
    {
        $package = $this->makePackage('acme/myplugin');
        $package->method('getTargetDir')->willReturn(null);
        $package->method('isDev')->willReturn(false);
        $composer = $this->makeComposer([]);
        $io = $this->createMock(IOInterface::class);

        $installer = new PluginInstaller($io, $composer);
        $path = $installer->getInstallPath($package);

        $this->assertIsString($path);
        $this->assertNotEmpty($path);
        $this->assertStringContainsString('acme/myplugin', $path);
    }
}
