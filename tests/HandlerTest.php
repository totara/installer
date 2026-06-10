<?php

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use TotaraInstaller\ClientLocker;
use TotaraInstaller\events\Handler;
use TotaraInstaller\Filesystem;

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

    // -------------------------------------------------------------------------
    // Further helpers for the event entry points and client (un)installation
    // -------------------------------------------------------------------------

    /**
     * Invoke any protected static method on Handler via reflection.
     */
    private function invoke_static(string $method, array $args) {
        return (new ReflectionMethod(Handler::class, $method))->invokeArgs(null, $args);
    }

    /**
     * Build a package stub with the given type (and optional name).
     */
    private function make_typed_package(string $type, string $name = 'totara/example'): PackageInterface {
        return $this->createConfiguredStub(PackageInterface::class, [
            'getType' => $type,
            'getName' => $name,
        ]);
    }

    /**
     * Build a PackageEvent wired with an InstallOperation around $package.
     */
    private function make_install_event(PackageInterface $package, IOInterface $io): PackageEvent {
        $operation = $this->createConfiguredStub(InstallOperation::class, ['getPackage' => $package]);
        return $this->make_event($operation, $io);
    }

    /**
     * Build a PackageEvent wired with an UpdateOperation around the packages.
     */
    private function make_update_event(PackageInterface $initial, PackageInterface $target, IOInterface $io): PackageEvent {
        $operation = $this->createConfiguredStub(UpdateOperation::class, [
            'getInitialPackage' => $initial,
            'getTargetPackage' => $target,
        ]);
        return $this->make_event($operation, $io);
    }

    private function make_event(object $operation, IOInterface $io): PackageEvent {
        $manager = $this->createStub(InstallationManager::class);
        $composer = $this->createConfiguredStub(Composer::class, ['getInstallationManager' => $manager]);

        return $this->createConfiguredStub(PackageEvent::class, [
            'getOperation' => $operation,
            'getIO' => $io,
            'getComposer' => $composer,
        ]);
    }

    /**
     * Build a real ClientLocker (the class is final) seeded with $contents,
     * backed by a Filesystem stub so nothing touches the real disk.
     */
    private function make_locker(array $contents = []): ClientLocker {
        $fs = $this->createConfiguredStub(Filesystem::class, ['read_json' => $contents]);
        return new ClientLocker($fs);
    }

    /**
     * Create a unique temporary directory, returning its absolute path.
     */
    private function make_temp_dir(): string {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('totara_handler_', true);
        mkdir($dir, 0777, true);
        return $dir;
    }

    /**
     * Recursively remove a directory tree.
     */
    private function remove_tree(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // Event entry points ignore packages that are not Totara plugins
    // -------------------------------------------------------------------------

    /**
     * A locker whose Filesystem is never written to — used to prove the event
     * handler returned early without calling ClientLocker::save().
     */
    private function locker_expecting_no_save(): ClientLocker {
        $fs = $this->createMock(Filesystem::class);
        $fs->method('read_json')->willReturn([]);
        $fs->expects($this->never())->method('write_json');
        $fs->expects($this->never())->method('unlink');
        return new ClientLocker($fs);
    }

    public function testOnInstallIgnoresNonTotaraPackages(): void {
        $package = $this->make_typed_package('library');
        $event = $this->make_install_event($package, $this->createMock(IOInterface::class));

        Handler::onInstall($event, $this->locker_expecting_no_save());
    }

    public function testOnUpdateIgnoresNonTotaraPackages(): void {
        $initial = $this->make_typed_package('library');
        $target = $this->make_typed_package('library');
        $event = $this->make_update_event($initial, $target, $this->createMock(IOInterface::class));

        Handler::onUpdate($event, $this->locker_expecting_no_save());
    }

    public function testOnUninstallIgnoresNonTotaraPackages(): void {
        $package = $this->make_typed_package('library');
        $event = $this->make_install_event($package, $this->createMock(IOInterface::class));

        Handler::onUninstall($event, $this->locker_expecting_no_save());
    }

    // -------------------------------------------------------------------------
    // discover_client_path
    // -------------------------------------------------------------------------

    public function testDiscoverClientPathReadsTheComponentFromTuiJson(): void {
        $dir = $this->make_temp_dir();
        try {
            mkdir($dir . '/.client');
            file_put_contents($dir . '/.client/tui.json', json_encode(['component' => 'totara_example']));

            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $dir]);
            $package = $this->make_typed_package('totara-client');

            [$component, $source] = $this->invoke_static('discover_client_path', [$manager, $package]);

            $this->assertSame('totara_example', $component);
            $this->assertSame($dir . '/.client', $source);
        } finally {
            $this->remove_tree($dir);
        }
    }

    public function testDiscoverClientPathReturnsNullsWhenThereIsNoClientComponent(): void {
        $dir = $this->make_temp_dir();
        try {
            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $dir]);
            $package = $this->make_typed_package('totara-client');

            [$component, $source] = $this->invoke_static('discover_client_path', [$manager, $package]);

            $this->assertNull($component);
            $this->assertNull($source);
        } finally {
            $this->remove_tree($dir);
        }
    }

    public function testDiscoverClientPathHandlesInvalidJson(): void {
        $dir = $this->make_temp_dir();
        try {
            mkdir($dir . '/.client');
            file_put_contents($dir . '/.client/tui.json', '{not valid json');

            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $dir]);
            $package = $this->make_typed_package('totara-client');

            [$component, $source] = $this->invoke_static('discover_client_path', [$manager, $package]);

            // The file exists, so the source is set, but the component is unreadable.
            $this->assertNull($component);
            $this->assertSame($dir . '/.client', $source);
        } finally {
            $this->remove_tree($dir);
        }
    }

    // -------------------------------------------------------------------------
    // uninstallClient
    // -------------------------------------------------------------------------

    public function testUninstallClientSkipsWhenThereIsNoLockEntry(): void {
        $locker = $this->make_locker();

        $package = $this->make_typed_package('totara-client');
        $this->invoke_static('uninstallClient', [$package, $locker, $this->createMock(IOInterface::class)]);

        $this->assertNull($locker->get_package('totara/example'));
    }

    public function testUninstallClientSkipsWhenTheLockedDirectoryIsMissing(): void {
        $entry = ['destination_path' => '/totara/does/not/exist'];
        $locker = $this->make_locker(['totara/example' => $entry]);

        $package = $this->make_typed_package('totara-client');
        $this->invoke_static('uninstallClient', [$package, $locker, $this->createMock(IOInterface::class)]);

        // The lock entry is left untouched because there was nothing to remove.
        $this->assertSame($entry, $locker->get_package('totara/example'));
    }

    public function testUninstallClientRemovesTheDirectoryAndLockEntry(): void {
        $dir = $this->make_temp_dir();
        $client = $dir . '/installed-client';
        mkdir($client);

        $locker = $this->make_locker(['totara/example' => ['destination_path' => $client]]);

        $package = $this->make_typed_package('totara-client');
        try {
            $this->invoke_static('uninstallClient', [$package, $locker, $this->createMock(IOInterface::class)]);

            $this->assertDirectoryDoesNotExist($client);
            $this->assertNull($locker->get_package('totara/example'));
        } finally {
            $this->remove_tree($dir);
        }
    }

    // -------------------------------------------------------------------------
    // installClient
    // -------------------------------------------------------------------------

    public function testInstallClientDoesNothingWhenNoClientComponentIsPresent(): void {
        $dir = $this->make_temp_dir();
        try {
            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $dir]);
            $locker = $this->make_locker();

            $package = $this->make_typed_package('totara-client');
            $this->invoke_static('installClient', [$package, $locker, $this->createMock(IOInterface::class), $manager, false]);

            $this->assertNull($locker->get_package('totara/example'));
        } finally {
            $this->remove_tree($dir);
        }
    }

    public function testInstallClientMovesTheComponentAndRecordsItInTheLock(): void {
        $root = $this->make_temp_dir();
        $original_cwd = getcwd();
        try {
            // Lay out a package install dir with a .client component...
            $install = $root . '/install';
            mkdir($install . '/.client', 0777, true);
            file_put_contents($install . '/.client/tui.json', json_encode(['component' => 'totara_example']));
            // ...and the parent of the destination ("client/component"), relative to cwd.
            mkdir($root . '/client/component', 0777, true);

            chdir($root);
            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $install]);
            $locker = $this->make_locker();

            $package = $this->make_typed_package('totara-client');
            $this->invoke_static('installClient', [$package, $locker, $this->createMock(IOInterface::class), $manager, false]);

            $this->assertDirectoryExists($root . '/client/component/totara_example');
            $this->assertDirectoryDoesNotExist($install . '/.client');
            $this->assertSame([
                'component_name' => 'totara_example',
                'source_path' => $install . '/.client',
                'destination_path' => 'client/component/totara_example',
            ], $locker->get_package('totara/example'));
        } finally {
            chdir($original_cwd);
            $this->remove_tree($root);
        }
    }

    public function testInstallClientSymlinksTheComponentWhenForced(): void {
        $root = $this->make_temp_dir();
        $original_cwd = getcwd();
        try {
            $install = $root . '/install';
            mkdir($install . '/.client', 0777, true);
            file_put_contents($install . '/.client/tui.json', json_encode(['component' => 'totara_example']));
            mkdir($root . '/client/component', 0777, true);

            chdir($root);
            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $install]);
            $locker = $this->make_locker();

            $package = $this->make_typed_package('totara-client');
            // force_symlink = true exercises the symlink branch directly.
            $this->invoke_static('installClient', [$package, $locker, $this->createMock(IOInterface::class), $manager, true]);

            $this->assertTrue(is_link($root . '/client/component/totara_example'));
            // The original component is left in place when symlinking.
            $this->assertDirectoryExists($install . '/.client');
            $this->assertNotNull($locker->get_package('totara/example'));
        } finally {
            chdir($original_cwd);
            $this->remove_tree($root);
        }
    }

    public function testInstallClientHaltsWhenTheDestinationAlreadyExists(): void {
        $root = $this->make_temp_dir();
        $original_cwd = getcwd();
        try {
            $install = $root . '/install';
            mkdir($install . '/.client', 0777, true);
            file_put_contents($install . '/.client/tui.json', json_encode(['component' => 'totara_example']));
            // A package is already sitting in the destination.
            mkdir($root . '/client/component/totara_example', 0777, true);

            chdir($root);
            $manager = $this->createConfiguredStub(InstallationManager::class, ['getInstallPath' => $install]);
            $locker = $this->make_locker();

            $io = $this->createMock(IOInterface::class);
            $io->expects($this->once())->method('error');

            $package = $this->make_typed_package('totara-client');
            $this->invoke_static('installClient', [$package, $locker, $io, $manager, false]);

            // Nothing was moved and no lock entry was recorded.
            $this->assertDirectoryExists($install . '/.client');
            $this->assertNull($locker->get_package('totara/example'));
        } finally {
            chdir($original_cwd);
            $this->remove_tree($root);
        }
    }

}
