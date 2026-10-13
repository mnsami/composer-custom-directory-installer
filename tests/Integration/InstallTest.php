<?php

namespace App\Tests\Integration;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\BufferIO;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Boots a real Composer against a temp project that pulls this plugin and fixture
 * packages from path repositories, then asserts where packages land on disk.
 *
 * The tests run without PHPUnit's error handler: Composer probes not-yet-written
 * autoload files through its Silencer, and PHPUnit would otherwise count every one
 * of those silenced reads as a warning.
 *
 * Assertions on vendor/ target the package directory, not the vendor prefix: Composer
 * 2.3 computes download paths for the whole batch before this plugin is activated and
 * pre-creates an empty vendor/acme/ on a fresh install. Later versions do not.
 */
class InstallTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const FIXTURES = __DIR__ . '/fixtures';

    private string $project;
    private string $cwd;
    private string|false $composerHome;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/ccdi-' . bin2hex(random_bytes(4));
        mkdir($this->project);

        // Composer resolves vendor-dir and install paths against the cwd.
        $this->cwd = (string) getcwd();
        chdir($this->project);

        // Keep global config, cache and global plugins out of the run.
        $this->composerHome = getenv('COMPOSER_HOME');
        Platform::putEnv('COMPOSER_HOME', $this->project . '/.composer');
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        if ($this->composerHome === false) {
            Platform::clearEnv('COMPOSER_HOME');
        } else {
            Platform::putEnv('COMPOSER_HOME', $this->composerHome);
        }
        // Composer's Filesystem unlinks symlinks instead of descending into them,
        // so the symlinked plugin checkout and fixtures stay untouched.
        (new Filesystem())->removeDirectory($this->project);
    }

    #[WithoutErrorHandler]
    public function testFirstInstallPlacesMappedPackageOutsideVendor(): void
    {
        $this->writeProject(['custom/{$name}/' => ['acme/foo']], [self::FIXTURES . '/acme-foo']);
        $this->runComposer();

        $this->assertFileExists($this->project . '/custom/foo/composer.json');
        $this->assertDirectoryDoesNotExist($this->project . '/vendor/acme/foo');
    }

    #[WithoutErrorHandler]
    public function testInstallerTypesRoutesCustomType(): void
    {
        $this->writeProject(
            ['web/modules/{$name}' => ['type:drupal-module']],
            [self::FIXTURES . '/acme-module'],
            ['installer-types' => ['drupal-module']]
        );
        $this->runComposer();

        $this->assertFileExists($this->project . '/web/modules/module/composer.json');
        $this->assertDirectoryDoesNotExist($this->project . '/vendor/acme/module');
    }

    #[WithoutErrorHandler]
    public function testComposerPluginTypeIsRoutedByPluginPlugin(): void
    {
        // Composer installs plugins in operation order and "acme/plugin" sorts before this
        // plugin, so on a fresh install Composer's own PluginInstaller would put it in vendor/
        // before ours is active (plugin-modifies-install-path only orders the loading of
        // plugins that are already installed). Install this plugin first, then add the package.
        $this->writeProject([], []);
        $this->runComposer();

        $this->writeProject(['plugins/{$name}' => ['acme/plugin']], [self::FIXTURES . '/acme-plugin']);
        $this->runComposer();

        $this->assertFileExists($this->project . '/plugins/plugin/composer.json');
        $this->assertDirectoryDoesNotExist($this->project . '/vendor/acme/plugin');
    }

    #[WithoutErrorHandler]
    public function testLibraryIsStillRoutedWhenComposerInstallersIsActive(): void
    {
        $this->writeProject(
            ['libs/{$name}' => ['acme/foo'], 'wp-content/plugins/{$name}' => ['type:wordpress-plugin']],
            [self::ROOT . '/vendor/composer/installers', self::FIXTURES . '/acme-foo', self::FIXTURES . '/acme-wp-plugin'],
            ['installer-types' => ['wordpress-plugin']]
        );
        $this->runComposer();

        $this->assertFileExists($this->project . '/vendor/composer/installers/composer.json');
        $this->assertFileExists($this->project . '/libs/foo/composer.json');
        $this->assertFileExists($this->project . '/wp-content/plugins/wp-plugin/composer.json');
        $this->assertDirectoryDoesNotExist($this->project . '/vendor/acme/foo');
        $this->assertDirectoryDoesNotExist($this->project . '/vendor/acme/wp-plugin');
    }

    #[WithoutErrorHandler]
    public function testPathChangeOnUpdateInstallsAtNewPathAndLeavesOldDirectory(): void
    {
        $fixtures = [self::FIXTURES . '/acme-foo'];
        $this->writeProject(['old/{$name}' => ['acme/foo']], $fixtures);
        $this->runComposer();
        $this->assertFileExists($this->project . '/old/foo/composer.json');

        $this->writeProject(['new/{$name}' => ['acme/foo']], $fixtures);
        $this->runComposer();

        $this->assertFileExists($this->project . '/new/foo/composer.json');
        // Documented limitation (#60): the old directory is neither moved nor removed.
        $this->assertFileExists($this->project . '/old/foo/composer.json');
    }

    /**
     * Write the temp project's composer.json: this plugin plus the given package
     * directories through path repositories, Packagist disabled, plugins allowed.
     *
     * @param array<string,string[]> $installerPaths extra.installer-paths
     * @param string[]               $packageDirs    directories holding a composer.json
     * @param array<string,mixed>    $extra          merged into extra (e.g. installer-types)
     */
    private function writeProject(array $installerPaths, array $packageDirs, array $extra = []): void
    {
        $repositories = [];
        $require = [];
        $allowPlugins = [];
        foreach ([self::ROOT, ...$packageDirs] as $dir) {
            $dir = (string) realpath($dir);
            $manifest = json_decode((string) file_get_contents($dir . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            $repository = ['type' => 'path', 'url' => $dir];
            if (!isset($manifest['version'])) {
                // No VCS metadata to guess a version from: pin one.
                $repository['options'] = ['versions' => [$manifest['name'] => '9.9.9']];
            }
            $repositories[] = $repository;
            $require[$manifest['name']] = $manifest['version'] ?? '9.9.9';
            if (($manifest['type'] ?? 'library') === 'composer-plugin') {
                $allowPlugins[$manifest['name']] = true;
            }
        }
        $repositories[] = ['packagist.org' => false];

        file_put_contents($this->project . '/composer.json', json_encode([
            'name' => 'test/project',
            'repositories' => $repositories,
            'require' => $require,
            'config' => ['allow-plugins' => $allowPlugins],
            'extra' => ['installer-paths' => $installerPaths] + $extra,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Run the equivalent of `composer update` in-process and return its output.
     */
    private function runComposer(): string
    {
        $io = new BufferIO();
        // Pass no config path: Factory::create() disables local plugins for any path
        // other than the default ./composer.json, which resolves in the cwd set in setUp().
        $composer = Factory::create($io, null, false, true);
        $installer = Installer::create($io, $composer)->setUpdate(true);
        if (method_exists($installer, 'setAudit')) {
            $installer->setAudit(false); // Composer >= 2.4; keeps the run offline
        }
        $exit = $installer->run();
        $output = $io->getOutput();
        $this->assertSame(0, $exit, "composer update failed:\n" . $output);

        return $output;
    }
}
