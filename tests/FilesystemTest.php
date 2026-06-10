<?php

use PHPUnit\Framework\TestCase;
use TotaraInstaller\Filesystem;

final class FilesystemTest extends TestCase {

    /** @var string Path to a unique scratch file for the current test. */
    private string $path;

    protected function setUp(): void {
        // Use a unique, non-existent path inside the system temp dir. Each test
        // creates/removes the file itself so we never collide with real data.
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('totara_fs_', true) . '.json';
    }

    protected function tearDown(): void {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    public function testExistsReflectsThePresenceOfTheFile(): void {
        $fs = new Filesystem($this->path);
        $this->assertFalse($fs->exists());

        file_put_contents($this->path, 'anything');
        $this->assertTrue($fs->exists());
    }

    public function testReadJsonReturnsNullWhenFileIsMissing(): void {
        $fs = new Filesystem($this->path);
        $this->assertNull($fs->read_json());
    }

    public function testWriteJsonAndReadJsonRoundTripsData(): void {
        $fs = new Filesystem($this->path);
        $data = ['b' => ['barnacle'], 'a' => ['apple']];

        $fs->write_json($data);

        $this->assertTrue($fs->exists());
        $this->assertSame($data, $fs->read_json());
    }

    public function testWriteJsonUsesPrettyPrintAndUnescapedSlashes(): void {
        $fs = new Filesystem($this->path);
        $fs->write_json(['destination_path' => 'server/blocks/example']);

        $contents = file_get_contents($this->path);
        // Pretty print indents nested entries...
        $this->assertStringContainsString("\n    \"destination_path\"", $contents);
        // ...and slashes are not escaped to \/.
        $this->assertStringContainsString('server/blocks/example', $contents);
        $this->assertStringNotContainsString('server\/blocks', $contents);
    }

    public function testUnlinkRemovesAnExistingFile(): void {
        file_put_contents($this->path, 'anything');
        $fs = new Filesystem($this->path);

        $this->assertTrue($fs->unlink());
        $this->assertFalse($fs->exists());
    }

    public function testUnlinkReturnsFalseWhenFileIsMissing(): void {
        $fs = new Filesystem($this->path);
        // @unlink suppresses the warning and returns false for a missing file.
        $this->assertFalse($fs->unlink());
    }
}
