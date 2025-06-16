<?php

namespace Composer\CustomDirectoryInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvents;
use Composer\Installer\PackageEvent;
use Composer\Script\ScriptEvents;
use Composer\Script\Event;

class LifecyclePlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            PackageEvents::PRE_PACKAGE_INSTALL => 'reorderInstallers',
            PackageEvents::PRE_PACKAGE_UPDATE => 'reorderInstallers',
            ScriptEvents::POST_AUTOLOAD_DUMP   => 'cleanupVendor',
        ];
    }

    public function reorderInstallers(PackageEvent $event)
    {
        $installationManager = $this->composer->getInstallationManager();

        $ref = new \ReflectionProperty($installationManager, 'installers');
        $ref->setAccessible(true);
        $installers = $ref->getValue($installationManager);

        $reordered = [];
        foreach ($installers as $installer) {
            if ($installer instanceof LibraryInstaller || $installer instanceof PluginInstaller || $installer instanceof PearInstaller) {
                array_unshift($reordered, $installer);
            } else {
                $reordered[] = $installer;
            }
        }
        $ref->setValue($installationManager, $reordered);
    }

    public function cleanupVendor(Event $event)
    {
        $extra = $this->composer->getPackage()->getExtra();
        if (empty($extra['installer-paths'])) {
            return;
        }

        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        foreach ($extra['installer-paths'] as $path => $packages) {
            foreach ($packages as $package) {
                $vendorPath = $vendorDir . '/' . $package;
                if (is_dir($vendorPath)) {
                    $this->removeDirectory($vendorPath);
                }
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }
}
