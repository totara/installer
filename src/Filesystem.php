<?php

namespace TotaraInstaller;

class Filesystem {

    public function __construct(protected string $file_path) {
    }

    /**
     * @param array|null $json_data
     * @return void
     */
    public function write_json(?array $json_data): void {
        file_put_contents($this->file_path, json_encode($json_data, JSON_PRETTY_PRINT));
    }

    /**
     * @return array|null
     */
    public function read_json(): ?array {
        if ($this->exists()) {
            return json_decode(file_get_contents($this->file_path), true);
        }

        return null;
    }

    public function unlink(): void {
        unlink($this->file_path);
    }

    /**
     * @return bool
     */
    public function exists(): bool {
        return file_exists($this->file_path);
    }
}