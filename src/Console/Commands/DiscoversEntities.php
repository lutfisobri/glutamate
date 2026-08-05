<?php

declare(strict_types=1);

namespace Glutamate\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use ReflectionClass;
use RegexIterator;

trait DiscoversEntities
{
    /**
     * Discover all entity classes in the configured directory.
     *
     * @return class-string[]
     */
    protected function discoverEntities(string $path, string $namespace): array
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (! is_dir($path)) {
            return [];
        }

        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        $classes = [];
        foreach ($regex as $fileInfo) {
            $filePath = is_array($fileInfo) ? ($fileInfo[0] ?? '') : $fileInfo;

            if (! is_string($filePath) || $filePath === '') {
                continue;
            }

            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
            $relativePath = str_replace(
                [rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, '.php'],
                ['', ''],
                $normalizedPath,
            );

            $className = $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            if (class_exists($className)) {
                $isModel = is_subclass_of($className, Model::class);

                if ($isModel) {
                    $ref = new ReflectionClass($className);

                    if (! $ref->isAbstract()) {
                        $classes[] = $className;
                    }
                }
            }
        }

        return $classes;
    }
}
