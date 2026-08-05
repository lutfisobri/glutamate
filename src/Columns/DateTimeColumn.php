<?php

declare(strict_types=1);

namespace Glutamate\Columns;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;

/**
 * @extends Column<Carbon>
 */
final class DateTimeColumn extends Column
{
    public static function make(?string $name = null): static
    {
        return new self($name);
    }

    protected bool $useCurrent = false;

    protected bool $useCurrentOnUpdate = false;

    public function useCurrent(bool $value = true): static
    {
        $this->useCurrent = $value;

        return $this;
    }

    public function useCurrentOnUpdate(bool $value = true): static
    {
        $this->useCurrentOnUpdate = $value;

        return $this;
    }

    public function getUseCurrent(): bool
    {
        return $this->useCurrent;
    }

    public function getUseCurrentOnUpdate(): bool
    {
        return $this->useCurrentOnUpdate;
    }

    public function toBlueprint(Blueprint $table, string $name): void
    {
        $col = $table->dateTime($name);

        if ($this->useCurrent) {
            $col->useCurrent();
        }

        if ($this->useCurrentOnUpdate) {
            $col->useCurrentOnUpdate();
        }
        $this->applyCommonModifiers($col);
    }

    public function phpType(): string
    {
        return $this->nullable ? '?\Illuminate\Support\Carbon' : '\Illuminate\Support\Carbon';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshotArray(): array
    {
        return [
            'type' => 'DateTimeColumn',
            'nullable' => $this->nullable,
            'hasDefault' => $this->hasDefault,
            'default' => $this->default,
            'unique' => $this->unique,
            'index' => $this->index,
            'meta' => [
                'useCurrent' => $this->useCurrent,
                'useCurrentOnUpdate' => $this->useCurrentOnUpdate,
            ],
        ];
    }
}
