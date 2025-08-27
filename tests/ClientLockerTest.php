<?php

use PHPUnit\Framework\TestCase;
use TotaraInstaller\Filesystem;

final class ClientLockerTest extends TestCase {
    public function testGetPackage(): void {
        $fs = $this->createConfiguredStub(
            Filesystem::class,
            [
                'read_json' => ['abc' => [123], 'def' => [456]]
            ]
        );

        $locker = new \TotaraInstaller\ClientLocker($fs);

        $package = $locker->get_package('abc');
        $this->assertSame($package, [123]);

        $package = $locker->get_package('def');
        $this->assertSame($package, [456]);

        $package = $locker->get_package('ghi');
        $this->assertNull($package);
    }

    public function testAddPackages(): void {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->exactly(2))
            ->method('write_json')
            ->withAnyParameters();
        $fs->expects($this->once())
            ->method('read_json')
            ->willReturn(['a' => 'apple']);

        $locker = new \TotaraInstaller\ClientLocker($fs);

        $package = $locker->get_package('abc');
        $this->assertNull($package);

        $locker->add_package('abc', [123]);
        $locker->save();

        $package = $locker->get_package('abc');
        $this->assertSame($package, [123]);

        $locker->add_package('def', [456]);
        $package = $locker->get_package('def');
        $this->assertSame($package, [456]);

        $locker->save();
    }

    public function testRemovePackages(): void {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->once())
            ->method('read_json')
            ->willReturn(['a' => ['apple'], 'b' => ['barnacle']]);
        $fs->expects($this->once())
            ->method('unlink');

        $locker = new \TotaraInstaller\ClientLocker($fs);

        // Let's watch the contents array
        $prop = new ReflectionProperty($locker, 'contents');

        // Initial load, everything is there
        $contents = $prop->getValue($locker);
        $this->assertSame(['a' => ['apple'], 'b' => ['barnacle']], $contents);

        // Drop a package off
        $locker->delete_package('a')->save();
        $contents = $prop->getValue($locker);
        $this->assertSame(['b' => ['barnacle']], $contents);
        $this->assertSame(['barnacle'], $locker->get_package('b'));
        $this->assertNull($locker->get_package('a'));

        // Delete the second
        $locker->delete_package('b')->save();
    }

}