<?php

namespace TotaraInstaller\installers;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Installer that installs one package in one location,
 * based on the plugin-type.
 *
 * type = totara-key (where key = a listed type below)
 */
class SimpleInstaller extends LibraryInstaller {
    use DropInLocations;

    /**
     * Calculate the path to install this plugin in.
     *
     * @param PackageInterface $package
     * @return string
     */
    public function getInstallPath(PackageInterface $package) {
        $name = basename($package->getName());

        // Let the plugin choose a new name instead if it likes
        if (!empty($package->getExtra()['installer-name'])) {
            $name = $package->getExtra()['installer-name'];
        }

        $path = self::getLocationFromPackageType($package->getType());
        $path = str_replace('{$name}', $name, $path);
        return rtrim($path, '/');
    }

    /**
     * Confirm we support that specific Totara plugin.
     *
     * @param string $packageType
     * @return bool
     */
    public function supports(string $packageType) {
        if (self::getLocationFromPackageType($packageType) !== null) {
            $this->io->debug('Totara plugin matched - ' . $packageType);
            return true;
        }

        return false;
    }
}