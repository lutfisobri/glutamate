<?php

declare(strict_types=1);

namespace Glutamate\Schema;

use Glutamate\Columns\Column;
use ReflectionClass;
use RuntimeException;

final class DocblockGenerator
{
    /**
     * @param  class-string  $modelClass
     * @param  array<string, Column<mixed>>  $columns
     * @param  string[]  $previousColumnNames
     */
    public static function update(string $modelClass, array $columns, array $previousColumnNames = []): void
    {
        $ref = new ReflectionClass($modelClass);
        $filePath = $ref->getFileName();

        if (! $filePath || ! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return;
        }

        $docComment = $ref->getDocComment();

        $propertyLines = [];
        foreach ($columns as $name => $column) {
            $phpType = $column->phpType();
            $propertyLines[] = " * @property {$phpType} \${$name}";
        }

        $lineEnding = str_contains($content, "\r\n") ? "\r\n" : "\n";

        if ($docComment !== false) {
            $lines = preg_split('/\r\n|\r|\n/', $docComment);

            if ($lines === false) {
                return;
            }

            $columnsToReplace = array_unique(array_merge(array_keys($columns), $previousColumnNames));

            $newLines = [];
            foreach ($lines as $line) {
                if (preg_match('/@property\s+(?:.+?)\$(\w+)/', $line, $matches)) {
                    $propertyName = $matches[1];

                    if (in_array($propertyName, $columnsToReplace, true)) {
                        continue;
                    }
                }

                if (trim($line) === '*/') {
                    foreach ($propertyLines as $propLine) {
                        $newLines[] = $propLine;
                    }
                }
                $newLines[] = $line;
            }
            $newDocComment = implode($lineEnding, $newLines);
            $content = str_replace($docComment, $newDocComment, $content);
        } else {
            $newDocLines = ['/**'];
            foreach ($propertyLines as $propLine) {
                $newDocLines[] = $propLine;
            }
            $newDocLines[] = ' */';

            $lines = preg_split('/\r\n|\r|\n/', $content);

            if ($lines === false) {
                return;
            }

            $startLineIndex = $ref->getStartLine() - 1;
            $insertIndex = $startLineIndex;

            while ($insertIndex > 0) {
                $prevLine = trim($lines[$insertIndex - 1]);

                if (str_starts_with($prevLine, '#[')) {
                    $insertIndex--;
                } else {
                    break;
                }
            }

            array_splice($lines, $insertIndex, 0, $newDocLines);
            $content = implode($lineEnding, $lines);
        }

        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException("Failed to update docblock in file: {$filePath}");
        }
    }
}
