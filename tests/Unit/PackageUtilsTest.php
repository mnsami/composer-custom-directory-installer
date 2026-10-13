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

    public function testTemplatePathThrowsOnUnknownVariable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown variable '{\$nmae}' in install path template 'lib/{\$nmae}'.");

        $this->callProtected('templatePath', ['lib/{$nmae}', ['name' => 'foo']]);
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

    public function testTemplatePathEmptyVarsArrayThrowsOnFirstPlaceholder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('{$vendor}');

        $this->callProtected('templatePath', ['{$vendor}/{$name}', []]);
    }

    // templatePath() — flags

    public function testTemplatePathFlagFCapitalizesFirstLetter(): void
    {
        $result = $this->callProtected('templatePath', ['{$name|F}', ['name' => 'my-package']]);
        $this->assertSame('My-package', $result);
    }

    public function testTemplatePathFlagPRemovesSeparatorsAndCamelCases(): void
    {
        $result = $this->callProtected('templatePath', ['{$name|P}', ['name' => 'my-package']]);
        $this->assertSame('myPackage', $result);
    }

    public function testTemplatePathFlagPRemovesUnderscores(): void
    {
        $result = $this->callProtected('templatePath', ['{$name|P}', ['name' => 'my_package']]);
        $this->assertSame('myPackage', $result);
    }

    public function testTemplatePathFlagUUppercasesAll(): void
    {
        $result = $this->callProtected('templatePath', ['{$name|U}', ['name' => 'my-package']]);
        $this->assertSame('MY-PACKAGE', $result);
    }

    public function testTemplatePathFlagFPProducesPascalCase(): void
    {
        $result = $this->callProtected('templatePath', ['{$name|FP}', ['name' => 'my-package']]);
        $this->assertSame('MyPackage', $result);
    }

    public function testTemplatePathFlagFPOnSingleWord(): void
    {
        $result = $this->callProtected('templatePath', ['{$name|FP}', ['name' => 'mypackage']]);
        $this->assertSame('Mypackage', $result);
    }

    public function testTemplatePathThrowsOnUnknownFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown flag 'X' in '{\$name|XZ}'.");

        $this->callProtected('templatePath', ['{$name|XZ}', ['name' => 'foo']]);
    }

    public function testGetPackageInstallPathThrowsOnUnknownVariable(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$nmae}' => ['acme/mylib']]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('{$nmae}');

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testTemplatePathNoFlagBehaviourUnchanged(): void
    {
        $result = $this->callProtected('templatePath', ['{$name}', ['name' => 'my-package']]);
        $this->assertSame('my-package', $result);
    }

    public function testTemplatePathMixedFlagsAndPlainPlaceholders(): void
    {
        $result = $this->callProtected('templatePath', ['lib/{$vendor}/{$name|FP}', ['vendor' => 'acme', 'name' => 'my-lib']]);
        $this->assertSame('lib/acme/MyLib', $result);
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

    public function testMapCustomInstallPathsMatchesVendorPrefix(): void
    {
        $paths = ['acme-libs/{$name}' => ['vendor:acme']];
        $this->assertSame('acme-libs/{$name}', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/mylib', '']));
        $this->assertFalse($this->callProtected('mapCustomInstallPaths', [$paths, 'other/mylib', '']));
    }

    public function testMapCustomInstallPathsExactNameAndTypeWinOverVendorPrefix(): void
    {
        $paths = [
            'vendor-path' => ['vendor:acme'],
            'type-path' => ['type:library'],
            'exact-path' => ['acme/mylib'],
        ];
        $this->assertSame('exact-path', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/mylib', 'library']));
        $this->assertSame('type-path', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/other', 'library']));
        $this->assertSame('vendor-path', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/other', 'wordpress-plugin']));
    }

    public function testMapCustomInstallPathsVendorPrefixAndGlobShareLowestPrecedence(): void
    {
        // Same rank: the first-listed entry wins, whichever spelling it uses.
        $this->assertSame('glob-path', $this->callProtected('mapCustomInstallPaths', [
            ['glob-path' => ['acme/*'], 'vendor-path' => ['vendor:acme']], 'acme/mylib', '',
        ]));
        $this->assertSame('vendor-path', $this->callProtected('mapCustomInstallPaths', [
            ['vendor-path' => ['vendor:acme'], 'glob-path' => ['acme/*']], 'acme/mylib', '',
        ]));
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

    public function testMapCustomInstallPathsMatchesCaseInsensitively(): void
    {
        // Composer names and types are lowercase; patterns may be written in any case.
        $this->assertSame('exact', $this->callProtected('mapCustomInstallPaths', [['exact' => ['Acme/Foo']], 'acme/foo', '']));
        $this->assertSame('type', $this->callProtected('mapCustomInstallPaths', [['type' => ['type:WordPress-Plugin']], 'acme/foo', 'wordpress-plugin']));
        $this->assertSame('glob', $this->callProtected('mapCustomInstallPaths', [['glob' => ['ACME/*']], 'acme/foo', '']));
        $this->assertSame('vendor', $this->callProtected('mapCustomInstallPaths', [['vendor' => ['Vendor:Acme']], 'acme/foo', '']));
    }

    public function testMapCustomInstallPathsMatchesGlobsWithoutStar(): void
    {
        // Any fnmatch metacharacter makes a pattern a glob, not only "*".
        $paths = ['q' => ['acme/foo?'], 'range' => ['acme/bar[0-9]']];
        $this->assertSame('q', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/foo1', '']));
        $this->assertSame('range', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/bar7', '']));
        $this->assertFalse($this->callProtected('mapCustomInstallPaths', [$paths, 'acme/barx', '']));
    }

    public function testMapCustomInstallPathsIgnoresNonStringPatterns(): void
    {
        $paths = ['custom' => [null, 42, 'acme/foo']];
        $this->assertSame('custom', $this->callProtected('mapCustomInstallPaths', [$paths, 'acme/foo', '']));
        $this->assertFalse($this->callProtected('mapCustomInstallPaths', [$paths, 'acme/bar', '']));
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

    public function testGetPackageInstallPathResolvesVendorPrefixMatch(): void
    {
        $package = $this->makePackage('acme/foo');
        $composer = $this->makeComposer(['installer-paths' => ['libs/{$name}' => ['vendor:acme']]]);

        $this->assertSame('libs/foo', PackageUtils::getPackageInstallPath($package, $composer));
    }

    public function testGetPackageInstallPathSubstitutesTypeVariable(): void
    {
        $package = $this->makePackage('acme/myplugin', [], 'wordpress-plugin');
        $composer = $this->makeComposer(['installer-paths' => ['custom/{$type}/{$vendor}/{$name}' => ['acme/myplugin']]]);

        $result = PackageUtils::getPackageInstallPath($package, $composer);
        $this->assertSame('custom/wordpress-plugin/acme/myplugin', $result);
    }

    public function testGetPackageInstallPathMatchesPatternCaseInsensitively(): void
    {
        $package = $this->makePackage('acme/foo');
        $composer = $this->makeComposer(['installer-paths' => ['libs/{$name}' => ['Acme/Foo']]]);

        $this->assertSame('libs/foo', PackageUtils::getPackageInstallPath($package, $composer));
    }

    public function testGetPackageInstallPathStripsTrailingSlash(): void
    {
        $package = $this->makePackage('monolog/monolog');
        $composer = $this->makeComposer(['installer-paths' => ['./monolog/' => ['monolog/monolog']]]);

        $this->assertSame('./monolog', PackageUtils::getPackageInstallPath($package, $composer));
    }

    public function testGetPackageInstallPathThrowsOnUncPath(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['\\\\server\\share\\{$name}' => ['acme/mylib']]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a relative path');

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testGetPackageInstallPathThrowsOnBackslashLeadingPath(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['\\foo\\{$name}' => ['acme/mylib']]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a relative path');

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testGetPackageInstallPathThrowsOnDriveLetterPath(): void
    {
        $package = $this->makePackage('acme/mylib');
        $composer = $this->makeComposer(['installer-paths' => ['C:\\libs\\{$name}' => ['acme/mylib']]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a relative path');

        PackageUtils::getPackageInstallPath($package, $composer);
    }

    public function testGetPackageInstallPathInstallerNameDoesNotAffectMatching(): void
    {
        // Patterns match the real package name; installer-name only changes what {$name} expands to.
        $package = $this->makePackage('acme/bar', ['installer-name' => 'foo']);
        $composer = $this->makeComposer(['installer-paths' => ['libs/{$name}' => ['acme/foo']]]);

        $this->assertNull(PackageUtils::getPackageInstallPath($package, $composer));
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
