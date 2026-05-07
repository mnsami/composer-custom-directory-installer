<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\Package\PackageInterface;

/**
 * Utility class for resolving custom installation paths for Composer packages.
 *
 * Reads the root package's extra.installer-paths configuration and provides
 * path resolution with variable substitution, type-based matching, and
 * wildcard vendor matching.
 */
class PackageUtils
{
    /**
     * Resolves the custom install path for a package.
     *
     * Reads the root package's extra.installer-paths configuration and returns
     * the resolved path for the given package, or null if no custom path is configured.
     *
     * Supported matching strategies (highest to lowest precedence):
     *  - Exact package name:  "vendor/name"
     *  - Package type prefix: "type:library"
     *  - Wildcard glob:       "vendor/*"
     *
     * Precedence is enforced globally across all installer-paths entries, not per-entry.
     *
     * Supported path variables: {$vendor}, {$name}, {$type}
     *
     * @param PackageInterface $package  The package being installed.
     * @param Composer         $composer The Composer instance (used to read root extra).
     * @return string|null The resolved install path, or null if no match found.
     * @throws \InvalidArgumentException If the resolved path contains '..' or is absolute.
     */
    public static function getPackageInstallPath(PackageInterface $package, Composer $composer): ?string
    {
        $prettyName = $package->getPrettyName();
        if (str_contains($prettyName, '/')) {
            [$vendor, $name] = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

        $availableVars = compact('name', 'vendor');
        $availableVars['type'] = $package->getType();

        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $availableVars['name'] = $extra['installer-name'];
        }

        $extra = $composer->getPackage()->getExtra();
        if (!empty($extra['installer-paths']) && is_array($extra['installer-paths'])) {
            $paths = array_filter($extra['installer-paths'], 'is_array');
            $customPath = self::mapCustomInstallPaths(
                $paths,
                $prettyName,
                $package->getType()
            );
            if (false !== $customPath) {
                $resolvedPath = self::templatePath($customPath, $availableVars);

                if (str_contains($resolvedPath, '..')) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Resolved install path '%s' contains '..', which is not allowed.",
                            $resolvedPath
                        )
                    );
                }

                if (str_starts_with($resolvedPath, '/') || preg_match('/^[A-Za-z]:[\\/\\\\]/', $resolvedPath)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Resolved install path '%s' must be a relative path.",
                            $resolvedPath
                        )
                    );
                }

                return $resolvedPath;
            }
        }

        return null;
    }

    /**
     * Replace {$var} placeholders in a path string.
     *
     * Unknown placeholders are substituted with an empty string.
     *
     * @param  string               $path The path template, e.g. "lib/{$vendor}/{$name}".
     * @param  array<string,string> $vars Map of variable name to replacement value.
     * @return string The path with all known placeholders substituted.
     */
    protected static function templatePath(string $path, array $vars = []): string
    {
        if (str_contains($path, '{')) {
            $path = (string) preg_replace_callback(
                '@\{\$([A-Za-z0-9_]+)\}@',
                static fn(array $m): string => $vars[$m[1]] ?? '',
                $path
            );
        }

        return $path;
    }

    /**
     * Search through installer-paths config for a path matching the given package name/type.
     *
     * Matching precedence (highest to lowest), enforced globally across all entries:
     *  1. Exact package name match (e.g. "vendor/name")
     *  2. Package type prefix match (e.g. "type:library")
     *  3. Wildcard glob match (e.g. "vendor/*")
     *
     * @param  array<string,string[]> $paths The installer-paths map (path => [package names/patterns]).
     * @param  string                 $name  The package pretty name to match.
     * @param  string                 $type  The package type (e.g. "library", "wordpress-plugin").
     * @return string|false The matching path key, or false if none found.
     */
    protected static function mapCustomInstallPaths(array $paths, string $name, string $type = ''): string|false
    {
        // Pass 1: exact name match (highest precedence across all entries)
        foreach ($paths as $path => $names) {
            if (in_array($name, $names)) {
                return $path;
            }
        }

        // Pass 2: type-prefix match
        if (!empty($type)) {
            foreach ($paths as $path => $names) {
                if (in_array('type:' . $type, $names)) {
                    return $path;
                }
            }
        }

        // Pass 3: wildcard glob match (lowest precedence)
        foreach ($paths as $path => $names) {
            foreach ($names as $pattern) {
                if (str_contains($pattern, '*') && fnmatch($pattern, $name)) {
                    return $path;
                }
            }
        }

        return false;
    }
}
