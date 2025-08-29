<?php

namespace TotaraInstaller\events;

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;
use JsonException;
use TotaraInstaller\ClientLocker;
use TotaraInstaller\installers\DropInLocations;

/**
 * Event handler used to manage custom client code
 */
final class Handler {
    use DropInLocations;

    private const CLIENT_DIR = '.client';

    /**
     * On install, move/link any extra packages to the correct locations.
     *
     * @param PackageEvent $event
     * @param ClientLocker $locker
     * @return void
     */
    public static function onInstall(PackageEvent $event, ClientLocker $locker) {
        /** @var InstallOperation $operation */
        $operation = $event->getOperation();
        $package = $operation->getPackage();
        $io = $event->getIO();
        $manager = $event->getComposer()->getInstallationManager();

        // Sanity check - the package is valid for our installer?
        if (!self::getLocationFromPackageType($package->getType())) {
            $io->debug("[TotaraInstaller] Package installation");
            $io->debug("[TotaraInstaller] Package wasn't valid for Totara - skipping post installation");
            return;
        }

        $io->write("[TotaraInstaller] Package installation");

        // Figure out if this package has a client component at all (install, we treat it fresh)
        [$component_name, $source_path] = self::discover_client_path($manager, $package);

        // If we have no name or source, we have nothing to install
        if (empty($component_name) || empty($source_path)) {
            $io->write("[TotaraInstaller] No client component detected, moving onwards.");
            return null;
        }

        // Figure out where we want to install the content to
        $destination_path = rtrim(str_replace('{$name}', $component_name, self::getLocationFromPackageType('totara-client')), '/');
        if (is_dir($destination_path)) {
            $io->error("[TotaraInstaller][{$package->getName()}] There already was a package in {$destination_path} when we tried to install it. Halting.");
            return null;
        }

        $file_system = new Filesystem();
        $is_symlink_install = $file_system->isSymlinkedDirectory($manager->getInstallPath($package));
        if ($is_symlink_install) {
            $cwd = Platform::getCwd();
            $file_system->relativeSymlink(
                $cwd . DIRECTORY_SEPARATOR . $source_path,
                $cwd . DIRECTORY_SEPARATOR . $destination_path
            );
            $io->debug("[TotaraInstaller] Package {$package->getName()} client symlinked from '{$source_path}' to '{$destination_path}'");
        } else {
            $file_system->rename($source_path, $destination_path);
            $io->debug("[TotaraInstaller] Package {$package->getName()} client moved from '{$source_path}' to '{$destination_path}'");
        }

        $locker->add_package($package->getName(), [
            'component_name' => $component_name,
            'source_path' => $source_path,
            'destination_path' => $destination_path,
        ])->save();
    }

    public static function onUpdate(PackageEvent $event, ClientLocker $locker) {
        // For sanity's sake we treat updates as a remove / install again.
        $event->getIO()->write("[TotaraInstaller] Package update");

        /** @var UpdateOperation $operation */
        $operation = $event->getOperation();
        $io = $event->getIO();
        $initial_package = $operation->getInitialPackage();
        $target_package = $operation->getTargetPackage();
        $manager = $event->getComposer()->getInstallationManager();

        // Sanity check - the package is valid for our installer?
        if (!self::getLocationFromPackageType($initial_package->getType())) {
            $io->debug("[TotaraInstaller] Package wasn't valid for Totara - skipping post update");
            return;
        }

        // Uninstall First
        // Do we have an entry already?
        $locked = $locker->get_package($initial_package->getName());
        if ($locked) {
            // Handle the uninstall
            $client_path = $locked['destination_path'];
            if (!is_dir($client_path)) {
                // Nothing to remove
                return null;
            }

            $fs = new Filesystem();
            $fs->remove($client_path);

            $locker->delete_package($initial_package->getName());
        }

        // Install Second
        [$component_name, $source_path] = self::discover_client_path($event->getComposer()->getInstallationManager(), $target_package);

        // If we have no name or source, we have nothing to install
        if (empty($component_name) || empty($source_path)) {
            return null;
        }

        // Figure out where we want to install the content to
        $destination_path = rtrim(str_replace('{$name}', $component_name, self::getLocationFromPackageType('totara-client')), '/');
        if (is_dir($destination_path)) {
            $io->error("[TotaraInstaller][{$target_package->getName()}] There already was a package in {$destination_path} when we tried to upgrade it. Halting.");
            return null;
        }

        $file_system = new Filesystem();
        $is_symlink_install = $file_system->isSymlinkedDirectory($manager->getInstallPath($target_package));
        if ($is_symlink_install) {
            $cwd = Platform::getCwd();
            $file_system->relativeSymlink(
                $cwd . DIRECTORY_SEPARATOR . $source_path,
                $cwd . DIRECTORY_SEPARATOR . $destination_path
            );
            $io->debug("[TotaraInstaller] Package {$target_package->getName()} client symlinked from '{$source_path}' to '{$destination_path}'");
        } else {
            $file_system->rename($source_path, $destination_path);
            $io->debug("[TotaraInstaller] Package {$target_package->getName()} client moved from '{$source_path}' to '{$destination_path}'");
        }

        $locker->add_package($target_package->getName(), [
            'component_name' => $component_name,
            'source_path' => $source_path,
            'destination_path' => $destination_path,
        ])->save();
    }

    /**
     * Clean and remove the client component on uninstall.
     *
     * @param PackageEvent $event
     * @param ClientLocker $locker
     * @return void|null
     */
    public static function onUninstall(PackageEvent $event, ClientLocker $locker) {
        $event->getIO()->write("[TotaraInstaller] Package uninstallation");

        /** @var InstallOperation $operation */
        $operation = $event->getOperation();
        $package = $operation->getPackage();
        $io = $event->getIO();

        // Sanity check - the package is valid for our installer?
        if (!self::getLocationFromPackageType($package->getType())) {
            $io->debug("[TotaraInstaller] Package wasn't valid for Totara - skipping post uninstallation");
            return;
        }

        // Do we have an entry already?
        $locked = $locker->get_package($package->getName());
        if (!$locked) {
            $io->debug("[TotaraInstaller] No client package lock entry - skipping post uninstallation");
            // Nothing to do
            return null;
        }

        // Does our path exist?
        $client_path = $locked['destination_path'];
        if (!is_dir($client_path) && !is_link($client_path)) {
            $io->debug("[TotaraInstaller] Client package lock entry dir ($client_path) does not exist, skipping");
            // Nothing to remove
            return null;
        }

        $fs = new Filesystem();
        $fs->remove($client_path);
        $io->debug("[TotaraInstaller] Removed client component $client_path provided by {$package->getName()}");

        $locker->delete_package($package->getName())->save();
    }

    /**
     * @param InstallationManager $manager
     * @param PackageInterface $package
     * @return array
     */
    protected static function discover_client_path(InstallationManager $manager, PackageInterface $package): array {
        $install_source = $manager->getInstallPath($package) . DIRECTORY_SEPARATOR . self::CLIENT_DIR;
        $component_file = $install_source . DIRECTORY_SEPARATOR . 'tui.json';
        $component_name = null;

        if (file_exists($component_file)) {
            try {
                $config = json_decode(file_get_contents($component_file), true, 5, JSON_THROW_ON_ERROR);
                $component_name = $config['component'] ?? null;
            } catch (JsonException $ex) {
                $component_name = null;
            }
        } else {
            $install_source = null;
        }

        return [$component_name, $install_source];
    }

}
