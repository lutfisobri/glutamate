<?php

declare(strict_types=1);

namespace Glutamate\Schema;

use RuntimeException;

final class SnapshotStore
{
    public function __construct(private readonly string $basePath) {}

    public function path(string $modelClass): string
    {
        $slug = str_replace('\\', '.', $modelClass);

        return rtrim($this->basePath, '/')."/{$slug}.json";
    }

    public function load(string $modelClass): ?SchemaSnapshot
    {
        $file = $this->path($modelClass);

        if (! file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return null;
        }

        return SchemaSnapshot::fromArray(json_decode($content, true, 512, JSON_THROW_ON_ERROR));
    }

    public function save(SchemaSnapshot $snapshot): void
    {
        $file = $this->path($snapshot->modelClass);

        if (! is_dir(dirname($file))) {
            if (! mkdir(dirname($file), 0755, true) && ! is_dir(dirname($file))) {
                throw new RuntimeException('Failed to create directory: '.dirname($file));
            }
        }

        if (file_put_contents($file, json_encode($snapshot->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException("Failed to write snapshot file: {$file}");
        }
    }
}
