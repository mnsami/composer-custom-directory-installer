<?php

namespace Composer\CustomDirectoryInstaller\Tests;

use Composer\CustomDirectoryInstaller\PackageUtils;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PackageUtilsTest extends TestCase
{
    public function testTemplatePathReplacesVariables()
    {
        $method = new ReflectionMethod(PackageUtils::class, 'templatePath');
        $method->setAccessible(true);
        $result = $method->invoke(null, 'lib/{$vendor}/{$name}', ['vendor' => 'foo', 'name' => 'bar']);
        $this->assertSame('lib/foo/bar', $result);
    }

    public function testMapCustomInstallPathsReturnsMatchingPath()
    {
        $method = new ReflectionMethod(PackageUtils::class, 'mapCustomInstallPaths');
        $method->setAccessible(true);
        $paths = [
            'custom/one' => ['a/b'],
            'custom/two' => ['c/d']
        ];
        $result = $method->invoke(null, $paths, 'a/b');
        $this->assertSame('custom/one', $result);

        $noMatch = $method->invoke(null, $paths, 'e/f');
        $this->assertFalse($noMatch);
    }
}
