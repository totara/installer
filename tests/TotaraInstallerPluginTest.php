<?php

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;
use TotaraInstaller\installers\SimpleInstaller;
use TotaraInstaller\TotaraInstallerPlugin;

final class TotaraInstallerPluginTest extends TestCase {

    /** @var string|false Original working directory, restored in tearDown(). */
    private $original_cwd;

    protected function setUp(): void {
        $this->original_cwd = getcwd();
    }

    protected function tearDown(): void {
        if ($this->original_cwd !== false) {
            chdir($this->original_cwd);
        }
    }

    public function testGetSubscribedEventsListensToThePackageLifecycle(): void {
        $events = TotaraInstallerPlugin::getSubscribedEvents();

        $this->assertSame([
            PackageEvents::POST_PACKAGE_INSTALL => ['eventListener', PHP_INT_MAX],
            PackageEvents::POST_PACKAGE_UPDATE => ['eventListener', PHP_INT_MAX],
            PackageEvents::PRE_PACKAGE_UNINSTALL => ['eventListener', PHP_INT_MIN],
        ], $events);
    }

    public function testActivateRegistersTheSimpleInstaller(): void {
        $manager = $this->createMock(InstallationManager::class);
        $manager->expects($this->once())
            ->method('addInstaller')
            ->with($this->isInstanceOf(SimpleInstaller::class));

        // SimpleInstaller extends LibraryInstaller, whose constructor reads the
        // vendor/bin dirs from the Composer config, so a config stub is required.
        $config = $this->createConfiguredStub(Config::class, ['get' => 'vendor']);
        $composer = $this->createMock(Composer::class);
        $composer->method('getInstallationManager')->willReturn($manager);
        $composer->method('getConfig')->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('debug');

        (new TotaraInstallerPlugin())->activate($composer, $io);
    }

    public function testDeactivateAndUninstallAreNoOps(): void {
        $plugin = new TotaraInstallerPlugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        // These are intentionally empty; calling them simply must not error.
        $plugin->deactivate($composer, $io);
        $plugin->uninstall($composer, $io);

        $this->expectNotToPerformAssertions();
    }

    public function testEventListenerDoesNothingOutsideAComposerProject(): void {
        // Move into an empty directory that has no composer.json so the listener
        // bails out before constructing any locker or invoking the handler.
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('totara_plugin_', true);
        mkdir($dir);

        try {
            chdir($dir);

            $event = $this->createMock(PackageEvent::class);
            // No operation/handler interaction is expected on the early return.
            $event->expects($this->never())->method('getOperation');

            TotaraInstallerPlugin::eventListener($event);
        } finally {
            chdir($this->original_cwd);
            rmdir($dir);
        }
    }
}
