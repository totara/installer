<?php

namespace TotaraInstaller;

/**
 * Simple class to keep track of what client files we've installed/needs cleaning up.
 */
final class ClientLocker {
    private const LOCK_FILE = 'totara-client.lock';

    private array $contents;

    /**
     * @param Filesystem $fs
     */
    public function __construct(private Filesystem $fs) {
        $this->contents = $fs->read_json() ?? [];
    }

    /**
     * @param string $base_path
     * @return string
     */
    public static function path(string $base_path): string {
        return rtrim($base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::LOCK_FILE;
    }

    /**
     * @param string $package
     * @return array|null
     */
    public function get_package(string $package): ?array {
        return $this->contents[$package] ?? null;
    }

    /**
     * @param string $package
     * @param array $attributes
     * @return $this
     */
    public function add_package(string $package, array $attributes): self {
        $this->contents[$package] = $attributes;
        return $this;
    }

    /**
     * @param string $package_name
     * @return $this
     */
    public function delete_package(string $package_name): self {
        if (isset($this->contents[$package_name])) {
            unset($this->contents[$package_name]);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function save(): self {
        if (empty($this->contents)) {
            $this->fs->unlink();
        } else {
            ksort($this->contents);
            $this->fs->write_json($this->contents);
        }

        return $this;
    }
}