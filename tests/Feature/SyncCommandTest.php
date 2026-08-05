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

it('runs sync, generating migration file and executing migrate', function () use (&$tempDirs) {
    $tempEntitiesDir = sys_get_temp_dir().'/glutamate_entities_'.uniqid();
    $tempSnapshotsDir = sys_get_temp_dir().'/glutamate_snapshots_'.uniqid();
    $tempDirs[] = $tempEntitiesDir;
    $tempDirs[] = $tempSnapshotsDir;

    if (! is_dir($tempEntitiesDir)) {
        mkdir($tempEntitiesDir, 0755, true);
    }

    $classCode = <<<'PHP'
<?php

namespace Glutamate\Tests\Feature\TempEntitiesSyncCommand;

use Glutamate\Columns\StringColumn;
use Illuminate\Database\Eloquent\Model;

class TempProductSyncCommandEntity extends Model
{
    protected $table = 'temp_products_sync_command';

    public static function name(): StringColumn
    {
        return StringColumn::make()->as(__FUNCTION__);
    }
}
PHP;

    $filePath = $tempEntitiesDir.'/TempProductSyncCommandEntity.php';
    file_put_contents($filePath, $classCode);

    require_once $filePath;

    config([
        'glutamate.models_path' => $tempEntitiesDir,
        'glutamate.models_namespace' => 'Glutamate\\Tests\\Feature\\TempEntitiesSyncCommand',
        'glutamate.snapshot_path' => $tempSnapshotsDir,
    ]);

    $migrationsDir = database_path('migrations/glutamate');
    $tempDirs[] = $migrationsDir;
    File::deleteDirectory($migrationsDir);

    // Assert table does not exist
    expect(Schema::hasTable('temp_products_sync_command'))->toBeFalse();

    // Run sync
    $this->artisan('glutamate:sync')
        ->assertSuccessful();

    // Assert migration file was generated
    $migrationFiles = File::files($migrationsDir);
    expect($migrationFiles)->toHaveCount(1);
    expect($migrationFiles[0]->getFilename())->toContain('create_temp_products_sync_command_table');

    // Assert table was created (migrate was called)
    expect(Schema::hasTable('temp_products_sync_command'))->toBeTrue();
    expect(Schema::hasColumn('temp_products_sync_command', 'name'))->toBeTrue();
});
