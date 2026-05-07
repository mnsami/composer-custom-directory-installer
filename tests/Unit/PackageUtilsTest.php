<?php

namespace App\Tests\Unit;

use Composer\Composer;
use Composer\CustomDirectoryInstaller\PackageUtils;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PackageUtilsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makePackage(
        string $prettyName,
        array $extra = [],
        string $type = 'library'
    ): PackageInterface {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getPrettyName')->willReturn($prettyName);
        $package->method('getExtra')->willReturn($extra);
        $package->method('getType')->willReturn($type);

        return $package;
    }

    private function makeComposer(array $rootExtra): Composer
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn($rootExtra);

        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($rootPackage);

        return $composer;
    }

    private function callProtected(string $method, array $args): mixed
    {
        $m = new ReflectionMethod(PackageUtils::class, $method);
        $m->setAccessible(true);

        return $m->invoke(null, ...$args);
    }

    // -----------------------------------------------------------------------
    // templatePath()
    // -----------------------------------------------------------------------

    public function testTemplatePathReplacesVariables(): void
    {
        $result = $this->callProtected('templatePath', ['lib/{$vendor}/{$name}', ['vendor' => 'foo', 'name' => 'bar']]);
        $this->assertSame('lib/foo/bar', $result);
    }

    public function testTemplatePathReturnsUnchangedWhenNoBraces(): void
    {
        $result = $this->callProtected('templatePath', ['lib/foo/bar', ['vendor' => 'foo']]);
        $this->assertSame('lib/foo/bar', $result);
    }

    public function testTemplatePathUnknownVariableBecomesEmptyString(): void
    {
        $result = $this->callProtected('templatePath', ['lib/{$unknown}', []]);
        $this->assertSame('lib/', $result);
    }

    public function testTemplatePathMultipleDifferentVariables(): void
    {
        $result = $this->callProtected('templatePath', ['{$type}/{$vendor}/{$name}', ['type' => 'plugin', 'vendor' => 'acme', 'name' => 'foo']]);
        $this->assertSame('plugin/acme/foo', $result);
    }

    public function testTemplatePathRepeatedVariable(): void
    {
        $result = $this->callProtected('templatePath', ['{$name}/{$name}', ['name' => 'bar']]);
        $this->assertSame('bar/bar', $result);
    }

    public function testTemplatePathEmptyVarsArray(): void
    {
        $result = $this->callProtected('templatePath', ['{$vendor}/{$name}', []]);
        $this->assertSame('/', $result);
    }

    // -----------------------------------------------------------------------
    // mapCustomInstallPaths()
    // -----------------------------------------------------------------------

    public function testMapCustomInstallPathsReturnsMatchingPath(): void
    {
        $paths = [
            'custom/one' => ['a/b'],
            'custom/two' => ['c/d'],
        ];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'a/b', '']);
        $this->assertSame('custom/one', $result);

        $noMatch = $this->callProtected('mapCustomInstallPaths', [$paths, 'e/f', '']);
        $this->assertFalse($noMatch);
    }

    public function testMapCustomInstallPathsMatchesByType(): void
    {
        $paths = ['wp-plugins/{$name}' => ['type:wordpress-plugin']];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/myplugin', 'wordpress-plugin']);
        $this->assertSame('wp-plugins/{$name}', $result);
    }

    public function testMapCustomInstallPathsTypeDoesNotMatchDifferentType(): void
    {
        $paths = ['wp-plugins/{$name}' => ['type:wordpress-plugin']];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/myplugin', 'library']);
        $this->assertFalse($result);
    }

    public function testMapCustomInstallPathsMatchesWildcard(): void
    {
        $paths = ['acme-libs/{$name}' => ['acme/*']];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/mylib', '']);
        $this->assertSame('acme-libs/{$name}', $result);
    }

    public function testMapCustomInstallPathsWildcardDoesNotMatchDifferentVendor(): void
    {
        $paths = ['acme-libs/{$name}' => ['acme/*']];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'other/mylib', '']);
        $this->assertFalse($result);
    }

    public function testMapCustomInstallPathsExactNameWinsOverWildcard(): void
    {
        $paths = [
            'exact-path' => ['acme/mylib'],
            'wildcard-path' => ['acme/*'],
        ];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/mylib', '']);
        $this->assertSame('exact-path', $result);
    }

    public function testMapCustomInstallPathsExactNameWinsOverWildcardInEarlierEntry(): void
    {
        // Wildcard entry listed first — exact match in a later entry must still win.
        $paths = [
            'wildcard-path' => ['acme/*'],
            'exact-path' => ['acme/mylib'],
        ];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/mylib', '']);
        $this->assertSame('exact-path', $result);
    }

    public function testMapCustomInstallPathsTypeMatchWinsOverWildcardInEarlierEntry(): void
    {
        // Wildcard entry listed first — type match in a later entry must still win.
        $paths = [
            'wildcard-path' => ['acme/*'],
            'type-path' => ['type:library'],
        ];
        $result = $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/mylib', 'library']);
        $this->assertSame('type-path', $result);
    }

    public function testMapCustomInstallPathsEmptyPathsReturnsFalse(): void
    {
        $result = $this->callProtected('mapCustomInstallPaths', [[], 'acme/mylib', '']);
        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // getPackageInstallPath()
    // -----------------------------------------------------------------------

    public function testGetPackageInstallPathReturnsNullWhenNoInstallerPaths(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer([]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertNull($result);
    }

    public function testGetPackageInstallPathReturnsNullWhenNoPathMatches(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$name}' => ['other/pkg']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertNull($result);
    }

    public function testGetPackageInstallPathResolvesExactNameMatch(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$vendor}/{$name}' => ['acme/mylib']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('custom/acme/mylib', $result);
    }

    public function testGetPackageInstallPathUsesInstallerNameOverride(): void
    {
        $package = $this->makePackage('acme/mylib', ['installer-name' => 'overridden']);
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$name}' => ['acme/mylib']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('custom/overridden', $result);
    }

    public function testGetPackageInstallPathHandlesPackageWithNoSlash(): void
    {
        $package = $this->makePackage('standalone');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$name}' => ['standalone']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('custom/standalone', $result);
    }

    public function testGetPackageInstallPathResolvesTypeMatch(): void
    {
        $package = $this->makePackage('acme/myplugin', [], 'wordpress-plugin');
        $composer = $this->makeComposer(['installer-paths' => ['wp-plugins/{$name}' => ['type:wordpress-plugin']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('wp-plugins/myplugin', $result);
    }

    public function testGetPackageInstallPathResolvesWildcardMatch(): void
    {
        $package = $this->makePackage('acme/anything');
        $composer = $this->makeComposer(['installer-paths' => ['acme-libs/{$name}' => ['acme/*']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('acme-libs/anything', $result);
    }

    public function testGetPackageInstallPathSubstitutesTypeVariable(): void
    {
        $package = $this->makePackage('acme/myplugin', [], 'wordpress-plugin');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$type}/{$vendor}/{$name}' => ['acme/myplugin']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('custom/wordpress-plugin/acme/myplugin', $result);
    }

    public function testGetPackageInstallPathThrowsOnPathTraversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("contains '..'");

        // Package name containing traversal that ends up in the resolved path
        $package = $this->makePackage('acme/../../etc/passwd');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$vendor}/{$name}' => ['acme/../../etc/passwd']]]);

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testGetPackageInstallPathThrowsOnPathTraversalViaInstallerName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("contains '..'");

        // installer-name override injects traversal into the resolved path
        $package = $this->makePackage('acme/mylib', ['installer-name' => '../../etc/passwd']);
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$name}' => ['acme/mylib']]]);

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testGetPackageInstallPathThrowsOnAbsolutePathViaInstallerName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must be a relative path");

        // installer-name override produces an absolute resolved path with no ".."
        $package = $this->makePackage('acme/mylib', ['installer-name' => '/etc/passwd']);
        $composer = $this->makeComposer(['installer-paths' => ['{$name}' => ['acme/mylib']]]);

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testGetPackageInstallPathIgnoresMalformedInstallerPathsScalarValue(): void
    {
        // installer-paths value is a string instead of an array — must not throw TypeError
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$name}' => 'acme/mylib']]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertNull($result);
    }
}
