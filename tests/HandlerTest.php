<?php

use Composer\Composer;
use Composer\Config;
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
     * Build a minimal Composer mock whose Config returns the given preferred-install value.
     */
    private function make_composer($preferred_install): Composer {
        $config = $this->createMock(Config::class);
        $config->method('get')
            ->with('preferred-install')
            ->willReturn($preferred_install);

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);

        return $composer;
    }

    /**
     * Invoke the protected static Handler::shouldForceSymlink() via reflection.
     */
    private function should_force_symlink(Composer $composer): bool {
        $method = new ReflectionMethod(Handler::class, 'shouldForceSymlink');
        return $method->invoke(null, $composer);
    }

    public function testReturnsFalseWhenPreferredInstallIsNotSource(): void {
        $this->assertFalse($this->should_force_symlink($this->make_composer('dist')));
        $this->assertFalse($this->should_force_symlink($this->make_composer('auto')));

        // A per-package preferred-install map means --prefer-source was not passed
        // as a global CLI flag, so we should not force symlinks.
        $per_package = ['vendor/some-package' => 'source', 'vendor/other' => 'dist'];
        $this->assertFalse($this->should_force_symlink($this->make_composer($per_package)));
    }

    public function testReturnsFalseWhenEnvVarIsInvalid(): void {
        $composer = $this->make_composer('source');
        // TOTARA_DEV_SYMLINK was unset in setUp() - it currently has no value
        $this->assertFalse($this->should_force_symlink($composer));

        putenv('TOTARA_DEV_SYMLINK=');
        $this->assertFalse($this->should_force_symlink($composer));

        putenv('TOTARA_DEV_SYMLINK=0');
        $this->assertFalse($this->should_force_symlink($composer));
    }

    public function testReturnsTrueWhenPreferSourceAndEnvVarIsSet(): void {
        $composer = $this->make_composer('source');

        putenv('TOTARA_DEV_SYMLINK=1');
        $this->assertTrue($this->should_force_symlink($composer));

        putenv('TOTARA_DEV_SYMLINK=true');
        $this->assertTrue($this->should_force_symlink($composer));

        putenv('TOTARA_DEV_SYMLINK=yes');
        $this->assertTrue($this->should_force_symlink($composer));
    }

}
