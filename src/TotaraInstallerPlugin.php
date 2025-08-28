<?php

namespace TotaraInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use TotaraInstaller\events\Handler;
use TotaraInstaller\installers\SimpleInstaller;

/**
 * Entry point class, configures and enables the installers.
 */
class TotaraInstallerPlugin implements PluginInterface, EventSubscriberInterface {

    protected array $installers = [];

    public function activate(Composer $composer, IOInterface $io): void {
        $simple = new SimpleInstaller($io, $composer);

        $manager = $composer->getInstallationManager();
        $manager->addInstaller($simple);

        $io->debug('Totara Installers Activated');
    }

    public function deactivate(Composer $composer, IOInterface $io): void {
    }

    public function uninstall(Composer $composer, IOInterface $io): void {
    }

    /**
     * Events we listen to for handling custom code
     *
     * @return array[]
     */
    public static function getSubscribedEvents() {
        // return [
        //     // Run the install as late as possible
        //     PackageEvents::POST_PACKAGE_INSTALL => [
        //         'eventListener',
        //         PHP_INT_MAX
        //     ],
        //     PackageEvents::POST_PACKAGE_UPDATE => [
        //         'eventListener',
        //         PHP_INT_MAX
        //     ],
        //     // Run the uninstall as early as possible
        //     PackageEvents::POST_PACKAGE_UNINSTALL => [
        //         'eventListener',
        //         PHP_INT_MIN
        //     ],
        // ];
    }

    /**
     * Proxy to the handler to manage events. Called by getSubscribedEvents().
     *
     * @param PackageEvent $event
     * @return void
     */
    public static function eventListener(PackageEvent $event) {
        $path = getcwd();
        if (!file_exists($path . '/composer.json')) {
            return;
        }

        $fs = new Filesystem(ClientLocker::path($path));
        $locker = new ClientLocker($fs);

        match ($event->getName()) {
            PackageEvents::POST_PACKAGE_INSTALL => Handler::onInstall($event, $locker),
            PackageEvents::POST_PACKAGE_UPDATE => Handler::onUpdate($event, $locker),
            PackageEvents::POST_PACKAGE_UNINSTALL => Handler::onUninstall($event, $locker),
        };
    }
}