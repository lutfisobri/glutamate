<?php

declare(strict_types=1);

namespace Glutamate\Schema;

use LogicException;

final class MigrationGenerator
{
    public static function generate(string $modelClass, ?SchemaSnapshot $previous, SchemaSnapshot $current, SchemaDiff $diff): string
    {
        $table = $current->table;

        if ($previous === null) {
            return self::generateCreate($table, $current);
        }

        return self::generateAlter($table, $previous, $current, $diff);
    }

    private static function generateCreate(string $table, SchemaSnapshot $current): string
    {
        $columnsLines = [];
        foreach ($current->columns as $name => $snapshot) {
            $columnsLines[] = '            '.self::renderColumnCall($name, $snapshot);
        }

        $columnsCode = implode("\n", $columnsLines);

        $template = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{{table}}', function (Blueprint $table) {
{{columnsCode}}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{{table}}');
    }
};
PHP;

        return str_replace(['{{table}}', '{{columnsCode}}'], [$table, $columnsCode], $template);
    }

    private static function generateAlter(string $table, SchemaSnapshot $previous, SchemaSnapshot $current, SchemaDiff $diff): string
    {
        $upLines = [];
        $downLines = [];

        // UP: Added columns
        foreach ($diff->added as $name => $snapshot) {
            $upLines[] = '            '.self::renderColumnCall($name, $snapshot);
        }

        // UP: Changed columns
        foreach ($diff->changed as $name => $change) {
            $fromSnapshot = $change['from'];

            if ($fromSnapshot['type'] === 'ForeignIdColumn' && ($fromSnapshot['meta']['isConstrained'] ?? false)) {
                $upLines[] = "            \$table->dropForeign(['{$name}']);";
            }
            $upLines[] = '            '.self::renderColumnCall($name, $change['to'], true);
        }

        // UP: Removed columns
        foreach ($diff->removed as $name) {
            $snapshot = $previous->columns[$name] ?? null;

            if ($snapshot !== null && $snapshot['type'] === 'ForeignIdColumn' && ($snapshot['meta']['isConstrained'] ?? false)) {
                $upLines[] = "            \$table->dropForeign(['{$name}']);";
            }
            $upLines[] = "            \$table->dropColumn('{$name}');";
        }

        // DOWN: Drop added columns
        foreach ($diff->added as $name => $snapshot) {
            if ($snapshot['type'] === 'ForeignIdColumn' && ($snapshot['meta']['isConstrained'] ?? false)) {
                $downLines[] = "            \$table->dropForeign(['{$name}']);";
            }
            $downLines[] = "            \$table->dropColumn('{$name}');";
        }

        // DOWN: Revert changed columns
        foreach ($diff->changed as $name => $change) {
            $toSnapshot = $change['to'];

            if ($toSnapshot['type'] === 'ForeignIdColumn' && ($toSnapshot['meta']['isConstrained'] ?? false)) {
                $downLines[] = "            \$table->dropForeign(['{$name}']);";
            }
            $downLines[] = '            '.self::renderColumnCall($name, $change['from'], true);
        }

        // DOWN: Re-add removed columns
        foreach ($diff->removed as $name) {
            $snapshot = $previous->columns[$name] ?? null;

            if ($snapshot !== null) {
                $downLines[] = '            '.self::renderColumnCall($name, $snapshot);
            }
        }

        $upCode = implode("\n", $upLines);
        $downCode = implode("\n", $downLines);

        $template = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('{{table}}', function (Blueprint $table) {
{{upCode}}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{{table}}', function (Blueprint $table) {
{{downCode}}
        });
    }
};
PHP;

        return str_replace(['{{table}}', '{{upCode}}', '{{downCode}}'], [$table, $upCode, $downCode], $template);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderColumnCall(string $name, array $snapshot, bool $isChange = false): string
    {
        $call = match ($snapshot['type']) {
            'StringColumn' => self::renderStringCall($name, $snapshot),
            'TextColumn' => "\$table->text('{$name}')",
            'IntColumn' => self::renderIntCall($name, $snapshot),
            'BoolColumn' => "\$table->boolean('{$name}')",
            'EnumColumn' => self::renderEnumCall($name, $snapshot),
            'DecimalColumn' => self::renderDecimalCall($name, $snapshot),
            'DateTimeColumn' => self::renderDateTimeCall($name, $snapshot),
            'ForeignIdColumn' => self::renderForeignIdCall($name, $snapshot),
            'IdColumn' => $name === 'id' ? '$table->id()' : "\$table->id('{$name}')",
            default => throw new LogicException("Unknown column type: {$snapshot['type']}"),
        };

        if ($snapshot['nullable']) {
            $call .= '->nullable()';
        }

        if ($snapshot['hasDefault']) {
            $call .= '->default('.self::renderValue($snapshot['default']).')';
        }

        if ($snapshot['unique']) {
            $call .= '->unique()';
        }

        if ($snapshot['index']) {
            $call .= '->index()';
        }

        if ($isChange) {
            $call .= '->change()';
        }

        return $call.';';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderStringCall(string $name, array $snapshot): string
    {
        $maxLength = $snapshot['meta']['maxLength'] ?? null;

        if ($maxLength !== null) {
            return "\$table->string('{$name}', {$maxLength})";
        }

        return "\$table->string('{$name}')";
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderIntCall(string $name, array $snapshot): string
    {
        $size = $snapshot['meta']['size'] ?? 'default';
        $autoIncrement = $snapshot['meta']['autoIncrement'] ?? false;
        $unsigned = $snapshot['meta']['unsigned'] ?? false;

        $method = match ($size) {
            'tiny' => 'tinyInteger',
            'small' => 'smallInteger',
            'medium' => 'mediumInteger',
            'big' => 'bigInteger',
            default => 'integer',
        };

        $args = "'{$name}'";

        if ($autoIncrement || $unsigned) {
            $args .= ', '.var_export($autoIncrement, true);
            $args .= ', '.var_export($unsigned, true);
        }

        return "\$table->{$method}({$args})";
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderEnumCall(string $name, array $snapshot): string
    {
        $values = $snapshot['meta']['values'] ?? [];
        $valuesString = implode(', ', array_map(fn (mixed $v) => var_export($v, true), $values));

        return "\$table->enum('{$name}', [{$valuesString}])";
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderDecimalCall(string $name, array $snapshot): string
    {
        $precision = $snapshot['meta']['precision'] ?? 8;
        $scale = $snapshot['meta']['scale'] ?? 2;
        $unsigned = $snapshot['meta']['unsigned'] ?? false;

        $call = "\$table->decimal('{$name}', {$precision}, {$scale})";

        if ($unsigned) {
            $call .= '->unsigned()';
        }

        return $call;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderDateTimeCall(string $name, array $snapshot): string
    {
        $useCurrent = $snapshot['meta']['useCurrent'] ?? false;
        $useCurrentOnUpdate = $snapshot['meta']['useCurrentOnUpdate'] ?? false;

        $call = "\$table->dateTime('{$name}')";

        if ($useCurrent) {
            $call .= '->useCurrent()';
        }

        if ($useCurrentOnUpdate) {
            $call .= '->useCurrentOnUpdate()';
        }

        return $call;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function renderForeignIdCall(string $name, array $snapshot): string
    {
        $call = "\$table->foreignId('{$name}')";

        if ($snapshot['meta']['isConstrained'] ?? false) {
            $refTable = $snapshot['meta']['referencesTable'] ?? null;

            if ($refTable !== null) {
                $call .= "->constrained('{$refTable}')";
            } else {
                $call .= '->constrained()';
            }

            $onDelete = $snapshot['meta']['onDelete'] ?? 'restrict';
            $onUpdate = $snapshot['meta']['onUpdate'] ?? 'restrict';

            $call .= "->onDelete('{$onDelete}')";
            $call .= "->onUpdate('{$onUpdate}')";
        }

        return $call;
    }

    private static function renderValue(mixed $value): string
    {
        return var_export($value, true);
    }
}
