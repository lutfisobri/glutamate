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

final class PushCommand extends Command
{
    use DiscoversEntities;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'glutamate:push';

    /**
     * The console command description.
     */
    protected $description = 'Push schema changes directly to the database without generating migration files';

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

            // Execute migration directly in-memory using a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'glutamate_');

            if ($tempFile === false) {
                $this->error('Failed to create temporary file.');

                return self::FAILURE;
            }

            file_put_contents($tempFile, $code);

            try {
                $migration = require $tempFile;
                $migration->up();
            } finally {
                @unlink($tempFile);
            }

            DocblockGenerator::update($modelClass, SchemaCompiler::compile($modelClass), $previousColumnNames);
            $store->save($current);
            $this->info("Pushed: schema for {$modelClass} applied directly to the database.");
        }

        if (! $anyChange) {
            $this->info('All entities in sync with the database.');
        }

        return self::SUCCESS;
    }
}
