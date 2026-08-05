<?php

declare(strict_types=1);

namespace Glutamate;

use Glutamate\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

final class SchemaCompiler
{
    /**
     * Compile and return the column schema for the given model class.
     *
     * @param  class-string  $modelClass
     * @return array<string, Column<mixed>>
     */
    public static function compile(string $modelClass): array
    {
        $isModel = is_subclass_of($modelClass, Model::class);

        if (! $isModel) {
            throw new InvalidArgumentException("{$modelClass} must extend ".Model::class);
        }

        /** @var class-string<object> $modelClass */
        $ref = new ReflectionClass($modelClass);
        $columns = [];

        foreach ($ref->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $modelClass) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            // Optional optimization: skip if return type is explicitly incompatible
            $returnType = $method->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {
                $returnClassName = $returnType->getName();

                if ($returnType->isBuiltin() || ($returnClassName !== SchemaElement::class && ! is_subclass_of($returnClassName, SchemaElement::class))) {
                    continue;
                }
            }

            try {
                $column = $method->invoke(null);
            } catch (Throwable $e) {
                continue;
            }

            if (! $column instanceof SchemaElement) {
                continue;
            }

            if ($column instanceof Column && $column->getName() === null) {
                throw new LogicException(
                    "Column defined in {$modelClass}::{$method->getName()}() must have an explicit name. "
                    .'Use ->as(__FUNCTION__) or pass the name to make().',
                );
            }

            foreach ($column->getColumns() as $colName => $singleColumn) {
                if (isset($columns[$colName])) {
                    throw new LogicException(
                        "Duplicate column name '{$colName}' resolved from {$modelClass}::{$method->getName()}() — "
                        .'already defined by another method. Use ->as() to disambiguate.',
                    );
                }

                $columns[$colName] = $singleColumn;
            }
        }

        return $columns;
    }
}
