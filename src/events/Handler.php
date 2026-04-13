<?php

namespace TotaraInstaller\events;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
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
    public static function onInstall(PackageEvent $event, ClientLocker $locker): void {
        /** @var InstallOperation $operation */
        $operation = $event->getOperation();
        $package = $operation->getPackage();
        $io = $event->getIO();
        $manager = $event->getComposer()->getInstallationManager();

        // Sanity check - the package is valid for our installer?
        if (!self::getLocationFromPackageType($package->getType())) {
            // not a Totara package
            return;
        }

        $io->write("[TotaraInstaller][{$package->getName()}] Package installation");

        $force_symlink = static::shouldForceSymlink($event->getComposer());
        static::installClient($package, $locker, $io, $manager, $force_symlink);

        $locker->save();
    }

    /**
     * On update, uninstall and reinstall client.
     *
     * @param PackageEvent $event
     * @param ClientLocker $locker
     * @return void
     */
    public static function onUpdate(PackageEvent $event, ClientLocker $locker): void {
        // For sanity's sake we treat updates as a remove / install again.

        /** @var UpdateOperation $operation */
        $operation = $event->getOperation();
        $io = $event->getIO();
        $initial_package = $operation->getInitialPackage();
        $target_package = $operation->getTargetPackage();
        $manager = $event->getComposer()->getInstallationManager();

        // Sanity check - the package is valid for our installer?
        if (!self::getLocationFromPackageType($initial_package->getType())) {
            // not a Totara package
            return;
        }

        $io->write("[TotaraInstaller][{$target_package->getName()}] Package update");

        // Uninstall First
        static::uninstallClient($initial_package, $locker, $io);

        // Install Second
        $force_symlink = static::shouldForceSymlink($event->getComposer());
        static::installClient($target_package, $locker, $io, $manager, $force_symlink);

        $locker->save();
    }

    /**
     * Clean and remove the client component on uninstall.
     *
     * @param PackageEvent $event
     * @param ClientLocker $locker
     * @return void
     */
    public static function onUninstall(PackageEvent $event, ClientLocker $locker): void {
        /** @var InstallOperation $operation */
        $operation = $event->getOperation();
        $package = $operation->getPackage();
        $io = $event->getIO();

        // Sanity check - the package is valid for our installer?
        if (!self::getLocationFromPackageType($package->getType())) {
            // not a Totara package
            return;
        }

        $io->write("[TotaraInstaller][{$package->getName()}] Package uninstallation");

        static::uninstallClient($package, $locker, $io);

        $locker->save();
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

    /**
     * Uninstall package from client directory
     *
     * @param PackageInterface $package
     * @param ClientLocker $locker
     * @param IOInterface $io
     * @return void
     */
    protected static function uninstallClient(PackageInterface $package, ClientLocker $locker, IOInterface $io): void {
        // Do we have an entry already?
        $locked = $locker->get_package($package->getName());
        if ($locked) {
            // Handle the uninstall
            $client_path = $locked['destination_path'];
            if (!is_dir($client_path)) {
                // Nothing to remove
                $io->debug("[TotaraInstaller][{$package->getName()}] Client directory described in lock file ($client_path) does not exist, skipping uninstall");
                return;
            }

            $fs = new Filesystem();
            $fs->remove($client_path);

            $locker->delete_package($package->getName());
        } else {
            $io->debug("[TotaraInstaller][{$package->getName()}] No client lock entry - skipping uninstall");
        }
    }

    /**
     * Determine whether .client directories should be force-symlinked.
     *
     * Returns true only when BOTH of the following conditions are met:
     *   1. Composer is running with --prefer-source (i.e. preferred-install === 'source')
     *   2. The developer has opted in via the TOTARA_DEV_SYMLINK environment variable.
     *
     * Developers can enable this per-session, per-command, or persistently:
     *   - One-off:    TOTARA_DEV_SYMLINK=1 composer install --prefer-source
     *   - Persistent: add `export TOTARA_DEV_SYMLINK=1` to ~/.bashrc or ~/.zshrc
     *
     * @param Composer $composer
     * @return bool
     */
    protected static function shouldForceSymlink(Composer $composer): bool {
        // --prefer-source causes Composer to set preferred-install to the string 'source'.
        // A per-package array value means no global --prefer-source flag was passed.
        $preferred_install = $composer->getConfig()->get('preferred-install');
        if ($preferred_install !== 'source') {
            return false;
        }

        // Developer opt-in via environment variable.
        $env = getenv('TOTARA_DEV_SYMLINK');
        return $env !== false && $env !== '' && $env !== '0';
    }

    /**
     * Install package to client directory
     *
     * @param PackageInterface $package
     * @param ClientLocker $locker
     * @param IOInterface $io
     * @param InstallationManager $manager
     * @param bool $force_symlink When true, force a symlink even if the package directory is not itself a symlink.
     * @return void
     */
    protected static function installClient(PackageInterface $package, ClientLocker $locker, IOInterface $io, InstallationManager $manager, bool $force_symlink = false): void {
        // Figure out if this package has a client component at all (install, we treat it fresh)
        [$component_name, $source_path] = self::discover_client_path($manager, $package);

        // If we have no name or source, we have nothing to install
        if (empty($component_name) || empty($source_path)) {
            $io->write("[TotaraInstaller][{$package->getName()}] No client component detected, moving onwards.");
            return;
        }

        // Figure out where we want to install the content to
        $destination_path = str_replace('{$name}', $component_name, self::getLocationFromPackageType('totara-client'));
        if (is_dir($destination_path)) {
            $io->error("[TotaraInstaller][{$package->getName()}] There already was a package in {$destination_path} when we tried to install it. Halting.");
            return;
        }

        $file_system = new Filesystem();
        $is_symlink_install = $force_symlink || (Platform::isWindows()
            ? $file_system->isJunction($manager->getInstallPath($package))
            : $file_system->isSymlinkedDirectory($manager->getInstallPath($package)));
        if ($is_symlink_install) {
            $cwd = Platform::getCwd();
            $source_absolute = $cwd . DIRECTORY_SEPARATOR . $source_path;
            $dest_absolute = $cwd . DIRECTORY_SEPARATOR . $destination_path;
            if (Platform::isWindows()) {
                $file_system->junction($source_absolute, $dest_absolute);
            } else {
                $file_system->relativeSymlink($source_absolute, $dest_absolute);
            }
            $verbed = Platform::isWindows() ? 'junctioned' : 'symlinked';
            $io->debug("[TotaraInstaller][{$package->getName()}] client $verbed from '{$source_path}' to '{$destination_path}'");
        } else {
            $file_system->rename($source_path, $destination_path);
            $io->debug("[TotaraInstaller][{$package->getName()}] client moved from '{$source_path}' to '{$destination_path}'");
        }

        $locker->add_package($package->getName(), [
            'component_name' => $component_name,
            'source_path' => $source_path,
            'destination_path' => $destination_path,
        ]);
    }
}
