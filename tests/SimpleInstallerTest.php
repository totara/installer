<?php

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use TotaraInstaller\installers\SimpleInstaller;

final class SimpleInstallerTest extends TestCase {

    /**
     * Build a SimpleInstaller without invoking the heavy LibraryInstaller
     * constructor, optionally injecting an IO mock for methods that need it.
     */
    private function make_installer(?IOInterface $io = null): SimpleInstaller {
        $installer = (new ReflectionClass(SimpleInstaller::class))->newInstanceWithoutConstructor();

        if ($io !== null) {
            $property = new ReflectionProperty(LibraryInstaller::class, 'io');
            $property->setValue($installer, $io);
        }

        return $installer;
    }

    /**
     * Build a package stub describing its name, type and extra metadata.
     */
    private function make_package(string $name, string $type, array $extra = []): PackageInterface {
        return $this->createConfiguredStub(PackageInterface::class, [
            'getName' => $name,
            'getType' => $type,
            'getExtra' => $extra,
        ]);
    }

    public function testGetInstallPathUsesThePackageBasename(): void {
        $installer = $this->make_installer();
        $package = $this->make_package('totara/myblock', 'totara-block');

        $this->assertSame('server/blocks/myblock', $installer->getInstallPath($package));
    }

    public function testGetInstallPathPrefersTheInstallerNameOverride(): void {
        $installer = $this->make_installer();
        $package = $this->make_package('totara/myblock', 'totara-block', ['installer-name' => 'renamed']);

        $this->assertSame('server/blocks/renamed', $installer->getInstallPath($package));
    }

    public function testSupportsReturnsTrueForAKnownTotaraType(): void {
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('debug');
        $installer = $this->make_installer($io);

        $this->assertTrue($installer->supports('totara-block'));
    }

    public function testSupportsReturnsFalseForAnUnsupportedType(): void {
        $installer = $this->make_installer();

        $this->assertFalse($installer->supports('library'));
    }
}
