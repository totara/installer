<?php

use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use TotaraInstaller\events\Handler;

final class HandlerTest extends TestCase {

    private $original_env;

    protected function setUp(): void {
        // Snapshot the current env value and start each test with the var absent,
        // so tests are fully isolated regardless of the developer's shell environment.
        $this->original_env = getenv('TOTARA_DEV_SYMLINK');
        putenv('TOTARA_DEV_SYMLINK');
    }

    protected function tearDown(): void {
        // Restore whatever the developer had set before the test ran.
        if ($this->original_env === false) {
            putenv('TOTARA_DEV_SYMLINK');
        } else {
            putenv('TOTARA_DEV_SYMLINK=' . $this->original_env);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a package mock whose getInstallationSource() returns the given value.
     */
    private function make_package(?string $installation_source): PackageInterface {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getInstallationSource')->willReturn($installation_source);
        return $package;
    }

    /**
     * Invoke the protected static Handler::shouldForceSymlink() via reflection.
     */
    private function should_force_symlink(PackageInterface $package): bool {
        $method = new ReflectionMethod(Handler::class, 'shouldForceSymlink');
        return $method->invoke(null, $package);
    }

    // -------------------------------------------------------------------------
    // Package was not installed from source — symlink must never be forced
    // -------------------------------------------------------------------------

    public function testReturnsFalseWhenPreferredInstallIsNotSource(): void {
        $this->assertFalse($this->should_force_symlink($this->make_package('dist')));
        $this->assertFalse($this->should_force_symlink($this->make_package(null)));
    }

    // -------------------------------------------------------------------------
    // Package was installed from source but TOTARA_DEV_SYMLINK is absent/disabled
    // -------------------------------------------------------------------------

    public function testReturnsFalseWhenEnvVarIsInvalid(): void {
        $package = $this->make_package('source');
        // TOTARA_DEV_SYMLINK was unset in setUp() - it currently has no value
        $this->assertFalse($this->should_force_symlink($package));

        putenv('TOTARA_DEV_SYMLINK=');
        $this->assertFalse($this->should_force_symlink($package));

        putenv('TOTARA_DEV_SYMLINK=0');
        $this->assertFalse($this->should_force_symlink($package));
    }

    // -------------------------------------------------------------------------
    // Both conditions met — symlink must be forced
    // -------------------------------------------------------------------------

    public function testReturnsTrueWhenPreferSourceAndEnvVarIsSet(): void {
        $package = $this->make_package('source');

        putenv('TOTARA_DEV_SYMLINK=1');
        $this->assertTrue($this->should_force_symlink($package));

        putenv('TOTARA_DEV_SYMLINK=true');
        $this->assertTrue($this->should_force_symlink($package));

        putenv('TOTARA_DEV_SYMLINK=yes');
        $this->assertTrue($this->should_force_symlink($package));
    }

}
