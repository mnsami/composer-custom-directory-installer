<?php

namespace App\Tests\Unit;

use Composer\Composer;
use Composer\CustomDirectoryInstaller\PackageUtils;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every installer-paths example in README.md must resolve without tripping the path guards.
 */
class ReadmeInstallerPathsTest extends TestCase
{
    /**
     * Yields every (path template, pattern) pair found in the README's ```json blocks.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function readmeExamples(): iterable
    {
        $readme = (string) file_get_contents(__DIR__ . '/../../README.md');
        preg_match_all('/```json\n(.*?)\n```/s', $readme, $m);

        foreach ($m[1] as $i => $block) {
            // Most blocks are fragments ("extra": {...}); wrap them so they parse.
            $json = str_starts_with(trim($block), '{') ? $block : '{' . $block . '}';
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            foreach (self::findInstallerPaths($decoded) as $path => $patterns) {
                foreach ($patterns as $pattern) {
                    yield "block $i: $path <= $pattern" => [$path, $pattern];
                }
            }
        }
    }

    #[DataProvider('readmeExamples')]
    public function testReadmeExampleResolvesWithoutThrowing(string $path, string $pattern): void
    {
        [$name, $type] = match (true) {
            str_starts_with($pattern, 'type:') => ['acme/example', substr($pattern, 5)],
            str_starts_with($pattern, 'vendor:') => [substr($pattern, 7) . '/example', 'library'],
            default => [str_replace('*', 'example', $pattern), 'library'],
        };

        $package = $this->createMock(PackageInterface::class);
        $package->method('getPrettyName')->willReturn($name);
        $package->method('getExtra')->willReturn([]);
        $package->method('getType')->willReturn($type);

        $root = $this->createMock(RootPackageInterface::class);
        $root->method('getExtra')->willReturn(['installer-paths' => [$path => [$pattern]]]);
        $composer = $this->createMock(Composer::class);
        $composer->method('getPackage')->willReturn($root);

        $this->assertNotNull(PackageUtils::getPackageInstallPath($package, $composer));
    }

    /**
     * @param  array<mixed> $node
     * @return array<string, string[]>
     */
    private static function findInstallerPaths(array $node): array
    {
        $found = [];
        foreach ($node as $key => $value) {
            if ($key === 'installer-paths' && is_array($value)) {
                $found += $value;
            } elseif (is_array($value)) {
                $found += self::findInstallerPaths($value);
            }
        }

        return $found;
    }
}
