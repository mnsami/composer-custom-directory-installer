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
     *  - Wildcard glob:       "vendor/*" (or its alias "vendor:vendor")
     *
     * Precedence is enforced globally across all installer-paths entries, not per-entry.
     * Matching is case-insensitive; Composer names and types are canonically lowercase.
     *
     * Supported path variables: {$vendor}, {$name}, {$type}
     *
     * @param PackageInterface $package  The package being installed.
     * @param Composer         $composer The Composer instance (used to read root extra).
     * @return string|null The resolved install path without a trailing slash, or null if no match found.
     * @throws \InvalidArgumentException If a template uses an unknown variable or flag, or the resolved path contains '..' or is absolute.
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

                // Absolute: a leading "/" or "\" (the latter also covers UNC "\\server\share"), or a drive letter.
                if (preg_match('@^(?:[/\\\\]|[A-Za-z]:[/\\\\])@', $resolvedPath)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            "Resolved install path '%s' must be a relative path.",
                            $resolvedPath
                        )
                    );
                }

                return rtrim($resolvedPath, '/');
            }
        }

        return null;
    }

    /**
     * Replace {$var} and {$var|flags} placeholders in a path string.
     *
     * A placeholder naming a variable missing from $vars throws, as does an unknown
     * flag character (see applyFlags()): a typo must not resolve to an empty string
     * and send several packages into one directory.
     *
     * @param  string               $path The path template, e.g. "lib/{$vendor}/{$name|FP}".
     * @param  array<string,string> $vars Map of variable name to replacement value.
     * @return string The path with all placeholders substituted.
     * @throws \InvalidArgumentException On an unknown variable or flag.
     */
    protected static function templatePath(string $path, array $vars = []): string
    {
        if (str_contains($path, '{')) {
            $path = (string) preg_replace_callback(
                '@\{\$([A-Za-z0-9_]+)(?:\|([A-Za-z]*))?\}@',
                static function (array $m) use ($vars, $path): string {
                    if (!array_key_exists($m[1], $vars)) {
                        throw new \InvalidArgumentException(
                            sprintf("Unknown variable '%s' in install path template '%s'.", $m[0], $path)
                        );
                    }

                    $value = $vars[$m[1]];
                    if (isset($m[2]) && $m[2] !== '') {
                        $value = self::applyFlags($value, $m[2], $m[0]);
                    }

                    return $value;
                },
                $path
            );
        }

        return $path;
    }

    /**
     * Apply formatting flags to a string value.
     *
     * Flags are applied left-to-right:
     *  F — ucfirst: capitalize first letter
     *  P — pascal/camel: strip hyphens and underscores, capitalize each following word segment
     *  U — strtoupper: uppercase all characters
     *
     * Any other flag character throws.
     *
     * @param  string $value       The value to transform.
     * @param  string $flags       One or more flag characters (e.g. "FP", "U").
     * @param  string $placeholder The placeholder being resolved, named in the error (e.g. "{$name|FP}").
     * @return string The transformed value.
     * @throws \InvalidArgumentException On an unknown flag character.
     */
    protected static function applyFlags(string $value, string $flags, string $placeholder): string
    {
        $len = strlen($flags);
        for ($i = 0; $i < $len; $i++) {
            switch ($flags[$i]) {
                case 'F':
                    $value = ucfirst($value);
                    break;
                case 'P':
                    $parts = preg_split('/[-_]+/', $value) ?: [$value];
                    $value = $parts[0] . implode('', array_map('ucfirst', array_slice($parts, 1)));
                    break;
                case 'U':
                    $value = strtoupper($value);
                    break;
                default:
                    throw new \InvalidArgumentException(
                        sprintf("Unknown flag '%s' in '%s'.", $flags[$i], $placeholder)
                    );
            }
        }

        return $value;
    }

    /**
     * Search through installer-paths config for a path matching the given package name/type.
     *
     * Matching precedence (highest to lowest), enforced globally across all entries:
     *  1. Exact package name match (e.g. "vendor/name")
     *  2. Package type prefix match (e.g. "type:library")
     *  3. Wildcard glob match (e.g. "vendor/*"); "vendor:acme" is an alias of "acme/*" at this rank
     *
     * Matching is case-insensitive. Non-string patterns are ignored.
     *
     * @param  array<string,string[]> $paths The installer-paths map (path => [package names/patterns]).
     * @param  string                 $name  The package pretty name to match.
     * @param  string                 $type  The package type (e.g. "library", "wordpress-plugin").
     * @return string|false The matching path key, or false if none found.
     */
    protected static function mapCustomInstallPaths(array $paths, string $name, string $type = ''): string|false
    {
        // Composer names and types are lowercase; accept patterns in any case and drop non-string entries.
        $name = strtolower($name);
        $type = strtolower($type);
        $paths = array_map(
            static fn(array $names): array => array_map('strtolower', array_filter($names, 'is_string')),
            $paths
        );

        // Pass 1: exact name match (highest precedence across all entries)
        foreach ($paths as $path => $names) {
            if (in_array($name, $names, true)) {
                return $path;
            }
        }

        // Pass 2: type-prefix match
        if (!empty($type)) {
            foreach ($paths as $path => $names) {
                if (in_array('type:' . $type, $names, true)) {
                    return $path;
                }
            }
        }

        // Pass 3: wildcard glob match (lowest precedence); "vendor:acme" is spelled "acme/*"
        foreach ($paths as $path => $names) {
            foreach ($names as $pattern) {
                if (str_starts_with($pattern, 'vendor:')) {
                    $pattern = substr($pattern, 7) . '/*';
                }
                if (str_contains($pattern, '*') && fnmatch($pattern, $name)) {
                    return $path;
                }
            }
        }

        return false;
    }
}
