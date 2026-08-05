<?php

declare(strict_types=1);

namespace Glutamate\Tests\Unit\Schema;

use Glutamate\Columns\IntColumn;
use Glutamate\Columns\StringColumn;
use Glutamate\Schema\SchemaSnapshot;
use Illuminate\Database\Eloquent\Model;

class SnapshotTestModel extends Model
{
    protected $table = 'snapshot_test_table';

    public static function title(): StringColumn
    {
        return StringColumn::make()->maxLength(200)->as(__FUNCTION__);
    }

    public static function views(): IntColumn
    {
        return IntColumn::make()->unsigned()->default(0)->as(__FUNCTION__);
    }
}

it('creates snapshot from model class correctly', function () {
    $snapshot = SchemaSnapshot::fromModel(SnapshotTestModel::class);

    expect($snapshot->modelClass)->toBe(SnapshotTestModel::class);
    expect($snapshot->table)->toBe('snapshot_test_table');
    expect($snapshot->columns)->toHaveKeys(['title', 'views']);

    $titleCol = $snapshot->columns['title'];
    expect($titleCol['type'])->toBe('StringColumn');
    expect($titleCol['meta']['maxLength'])->toBe(200);

    $viewsCol = $snapshot->columns['views'];
    expect($viewsCol['type'])->toBe('IntColumn');
    expect($viewsCol['meta']['unsigned'])->toBeTrue();
    expect($viewsCol['default'])->toBe(0);
});

it('supports round-trip via arrays', function () {
    $snapshot = SchemaSnapshot::fromModel(SnapshotTestModel::class);
    $array = $snapshot->toArray();

    $restored = SchemaSnapshot::fromArray($array);

    expect($restored->modelClass)->toBe($snapshot->modelClass);
    expect($restored->table)->toBe($snapshot->table);
    expect($restored->columns)->toBe($snapshot->columns);
});
