<?php

declare(strict_types=1);

namespace Glutamate\Columns;

use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;

/**
 * @extends Column<string>
 */
final class EnumColumn extends Column
{
    /** @var string[] */
    protected array $values = [];

    /**
     * @param  array<int, string>  $values
     */
    public static function make(array $values, ?string $name = null): static
    {
        if (empty($values)) {
            throw new InvalidArgumentException('EnumColumn requires at least one value.');
        }

        $instance = new self($name);
        $instance->values = $values;

        return $instance;
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function toBlueprint(Blueprint $table, string $name): void
    {
        $col = $table->enum($name, $this->values);
        $this->applyCommonModifiers($col);
    }

    public function phpType(): string
    {
        return $this->nullable ? '?string' : 'string';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshotArray(): array
    {
        return [
            'type' => 'EnumColumn',
            'nullable' => $this->nullable,
            'hasDefault' => $this->hasDefault,
            'default' => $this->default,
            'unique' => $this->unique,
            'index' => $this->index,
            'meta' => [
                'values' => $this->values,
            ],
        ];
    }
}
