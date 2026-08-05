<?php

declare(strict_types=1);

namespace Glutamate\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

$tempDirs = [];

afterEach(function () use (&$tempDirs) {
    foreach ($tempDirs as $dir) {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
    $tempDirs = [];
});

it('runs push applying schema directly to database without writing migrations', function () use (&$tempDirs) {
    $tempEntitiesDir = sys_get_temp_dir().'/glutamate_entities_'.uniqid();
    $tempSnapshotsDir = sys_get_temp_dir().'/glutamate_snapshots_'.uniqid();
    $tempDirs[] = $tempEntitiesDir;
    $tempDirs[] = $tempSnapshotsDir;

    if (! is_dir($tempEntitiesDir)) {
        mkdir($tempEntitiesDir, 0755, true);
    }

    $classCode = <<<'PHP'
<?php

namespace Glutamate\Tests\Feature\TempEntitiesPush;

use Glutamate\Columns\StringColumn;
use Illuminate\Database\Eloquent\Model;

class TempProductPushEntity extends Model
{
    protected $table = 'temp_products_push';

    public static function name(): StringColumn
    {
        return StringColumn::make()->as(__FUNCTION__);
    }
}
PHP;

    $filePath = $tempEntitiesDir.'/TempProductPushEntity.php';
    file_put_contents($filePath, $classCode);

    require_once $filePath;

    config([
        'glutamate.models_path' => $tempEntitiesDir,
        'glutamate.models_namespace' => 'Glutamate\\Tests\\Feature\\TempEntitiesPush',
        'glutamate.snapshot_path' => $tempSnapshotsDir,
    ]);

    $migrationsDir = database_path('migrations/glutamate');
    $tempDirs[] = $migrationsDir;
    File::deleteDirectory($migrationsDir);

    // Assert table does not exist
    expect(Schema::hasTable('temp_products_push'))->toBeFalse();

    // Run push
    $this->artisan('glutamate:push')
        ->expectsOutputToContain('Pushed: schema for')
        ->assertSuccessful();

    // Assert table exists
    expect(Schema::hasTable('temp_products_push'))->toBeTrue();
    expect(Schema::hasColumn('temp_products_push', 'name'))->toBeTrue();

    // Assert no migration files were written
    expect(is_dir($migrationsDir))->toBeFalse();

    // Assert snapshot file exists
    $snapshotFile = $tempSnapshotsDir.'/Glutamate.Tests.Feature.TempEntitiesPush.TempProductPushEntity.json';
    expect(file_exists($snapshotFile))->toBeTrue();
});
