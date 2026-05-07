<?php

namespace App\Tests\Unit;

use Composer\Composer;
use Composer\Config;
use Composer\CustomDirectoryInstaller\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\TestCase;

class LibraryInstallerTest extends TestCase
{
    private function makePackage(string $prettyName, array $extra = [], string $type = 'library'): PackageInterface
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
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$vendor}/{$name}' => ['acme/mylib']]]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);
        $path = $installer->getInstallPath($package);

        $this->assertSame('custom/acme/mylib', $path);
    }

    public function testGetInstallPathFallsBackToParentWhenNoCustomPath(): void
    {
        $package = $this->makePackage('acme/mylib');
        $package->method('getTargetDir')->willReturn(null);
        $package->method('isDev')->willReturn(false);
        $composer = $this->makeComposer([]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);
        $path = $installer->getInstallPath($package);

        // Parent returns the vendor-dir based path; just assert it's a non-empty string
        // that does not equal the custom path
        $this->assertIsString($path);
        $this->assertNotEmpty($path);
        $this->assertStringContainsString('acme/mylib', $path);
    }

    public function testSupportsReturnsTrueForLibraryTypeByDefault(): void
    {
        $composer = $this->makeComposer([]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);

        $this->assertTrue($installer->supports('library'));
    }

    public function testSupportsReturnsFalseForCustomTypeWithoutInstallerTypes(): void
    {
        $composer = $this->makeComposer([]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);

        $this->assertFalse($installer->supports('drupal-module'));
    }

    public function testSupportsReturnsTrueForCustomTypeWhenListedInInstallerTypes(): void
    {
        $composer = $this->makeComposer(['installer-types' => ['drupal-module']]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);

        $this->assertTrue($installer->supports('drupal-module'));
    }

    public function testSupportsReturnsFalseForCustomTypeNotInInstallerTypes(): void
    {
        $composer = $this->makeComposer(['installer-types' => ['drupal-module']]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);

        $this->assertFalse($installer->supports('wordpress-plugin'));
    }

    public function testGetInstallPathWorksForCustomTypePackage(): void
    {
        $package = $this->makePackage('acme/mymodule', [], 'drupal-module');
        $composer = $this->makeComposer([
            'installer-types' => ['drupal-module'],
            'installer-paths' => ['web/modules/{$name}' => ['type:drupal-module']],
        ]);
        $io = $this->createMock(IOInterface::class);

        $installer = new LibraryInstaller($io, $composer);

        $this->assertTrue($installer->supports('drupal-module'));
        $this->assertSame('web/modules/mymodule', $installer->getInstallPath($package));
    }
}
