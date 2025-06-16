<?php

namespace App\Tests\Unit;

use Composer\CustomDirectoryInstaller\LifecyclePlugin;
use Composer\Installer\PackageEvents;
use Composer\Script\ScriptEvents;
use PHPUnit\Framework\TestCase;

class LifecyclePluginTest extends TestCase
{
    public function testSubscribedEvents()
    {
        $events = LifecyclePlugin::getSubscribedEvents();

        $this->assertArrayHasKey(PackageEvents::PRE_PACKAGE_INSTALL, $events);
        $this->assertArrayHasKey(PackageEvents::PRE_PACKAGE_UPDATE, $events);
        $this->assertArrayHasKey(ScriptEvents::POST_AUTOLOAD_DUMP, $events);
    }
}
