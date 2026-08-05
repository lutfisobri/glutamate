<?php

declare(strict_types=1);

namespace Glutamate\Console\Commands;

use Glutamate\Schema\DocblockGenerator;
use Glutamate\Schema\MigrationGenerator;
use Glutamate\Schema\SchemaDiffer;
use Glutamate\Schema\SchemaSnapshot;
use Glutamate\Schema\SnapshotStore;
use Glutamate\SchemaCompiler;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class GenerateCommand extends Command
{
    use DiscoversEntities;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'glutamate:generate {--dry-run}';

    /**
     * The console command description.
     */
    protected $description = 'Detect schema changes and generate migration files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelsPath = config('glutamate.models_path', app_path('Models'));
        $modelsNamespace = config('glutamate.models_namespace', 'App\\Models');
        $snapshotPath = config('glutamate.snapshot_path', storage_path('framework/glutamate/snapshots'));

        $store = new SnapshotStore($snapshotPath);
        $entities = $this->discoverEntities($modelsPath, $modelsNamespace);

        if (empty($entities)) {
            $this->info('No models found.');

            return self::SUCCESS;
        }

        $anyChange = false;

        foreach ($entities as $modelClass) {
            $current = SchemaSnapshot::fromModel($modelClass);
            $previous = $store->load($modelClass);
            $previousColumnNames = $previous !== null ? array_keys($previous->columns) : [];

            $diff = SchemaDiffer::diff($previous, $current);

            if ($diff->isEmpty()) {
                $this->line("  {$modelClass}: no changes");

                continue;
            }

            $anyChange = true;
            $code = MigrationGenerator::generate($modelClass, $previous, $current, $diff);

            if ($this->option('dry-run')) {
                $this->comment("Would generate migration for {$modelClass}:");
                $this->line($code);

                continue;
            }

            $slug = self::migrationSlug($modelClass, $previous);
            $filename = date('Y_m_d_His').'_'.$slug.'.php';
            $path = database_path('migrations/glutamate/'.$filename);

            if (! is_dir(dirname($path))) {
                if (! mkdir(dirname($path), 0755, true) && ! is_dir(dirname($path))) {
                    $this->error('Failed to create directory: '.dirname($path));

                    return self::FAILURE;
                }
            }

            if (file_put_contents($path, $code) === false) {
                $this->error("Failed to write migration file: {$path}");

                return self::FAILURE;
            }

            DocblockGenerator::update($modelClass, SchemaCompiler::compile($modelClass), $previousColumnNames);
            $store->save($current);
            $this->info("Generated: {$path}");
        }

        if (! $anyChange) {
            $this->info('All entities in sync, no migrations generated.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  class-string  $modelClass
     */
    private static function migrationSlug(string $modelClass, ?SchemaSnapshot $previous): string
    {
        /** @var Model $instance */
        $instance = new $modelClass;
        $table = $instance->getTable();

        if ($previous === null) {
            return "create_{$table}_table";
        }

        return "update_{$table}_table";
    }
}
