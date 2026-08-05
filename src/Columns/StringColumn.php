<?php

declare(strict_types=1);

namespace Glutamate\Columns;

use Illuminate\Database\Schema\Blueprint;

/**
 * @extends Column<string>
 */
final class StringColumn extends Column
{
    public static function make(?string $name = null): static
    {
        return new self($name);
    }

    protected ?int $maxLength = null;

    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function toBlueprint(Blueprint $table, string $name): void
    {
        $column = $table->string($name, $this->maxLength);

        $this->applyCommonModifiers($column);
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
            'type' => 'StringColumn',
            'nullable' => $this->nullable,
            'hasDefault' => $this->hasDefault,
            'default' => $this->default,
            'unique' => $this->unique,
            'index' => $this->index,
            'meta' => [
                'maxLength' => $this->maxLength,
            ],
        ];
    }
}
