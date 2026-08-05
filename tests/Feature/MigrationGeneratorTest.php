<?php

declare(strict_types=1);

namespace Glutamate\Tests\Feature;

use Glutamate\Schema\MigrationGenerator;
use Glutamate\Schema\SchemaDiffer;
use Glutamate\Schema\SchemaSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('generates a frozen create migration and executes it on SQLite', function () {
    $current = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts',
        columns: [
            'title' => [
                'type' => 'StringColumn',
                'nullable' => false,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => ['maxLength' => 200],
            ],
            'views' => [
                'type' => 'IntColumn',
                'nullable' => false,
                'hasDefault' => true,
                'default' => 10,
                'unique' => false,
                'index' => false,
                'meta' => ['size' => 'default', 'unsigned' => true, 'autoIncrement' => false],
            ],
        ],
    );

    $diff = SchemaDiffer::diff(null, $current);
    $code = MigrationGenerator::generate('App\\Entities\\Post', null, $current, $diff);

    // Assert that the migration code is frozen and does not reference SchemaCompiler
    expect($code)->toContain("\$table->string('title', 200)");
    expect($code)->toContain("\$table->integer('views', false, true)->default(10)");
    expect($code)->not->toContain('SchemaCompiler');
    expect($code)->not->toContain('App\\Entities\\Post');
    expect($code)->not->toContain('Post::class');

    // Write to a temporary migration directory and execute
    $tempPath = database_path('migrations/temp_gen_'.uniqid());

    if (! is_dir($tempPath)) {
        mkdir($tempPath, 0755, true);
    }

    $migrationFile = $tempPath.'/2026_01_01_000000_create_test_generator_posts_table.php';
    file_put_contents($migrationFile, $code);

    $this->loadMigrationsFrom($tempPath);
    Artisan::call('migrate');

    expect(Schema::hasTable('test_generator_posts'))->toBeTrue();
    expect(Schema::hasColumn('test_generator_posts', 'title'))->toBeTrue();
    expect(Schema::hasColumn('test_generator_posts', 'views'))->toBeTrue();

    // Verify default value works
    DB::table('test_generator_posts')->insert(['title' => 'Test Post']);
    $row = DB::table('test_generator_posts')->first();
    expect((int) $row->views)->toBe(10);

    // Cleanup
    File::deleteDirectory($tempPath);
});

it('generates a frozen alter migration adding and removing columns and executes it', function () {
    $previous = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts_alter',
        columns: [
            'title' => [
                'type' => 'StringColumn',
                'nullable' => false,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => ['maxLength' => 200],
            ],
        ],
    );

    $current = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts_alter',
        columns: [
            'title' => [
                'type' => 'StringColumn',
                'nullable' => false,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => ['maxLength' => 200],
            ],
            'description' => [
                'type' => 'StringColumn',
                'nullable' => true,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => ['maxLength' => null],
            ],
        ],
    );

    // First create table with previous schema
    $createDiff = SchemaDiffer::diff(null, $previous);
    $createCode = MigrationGenerator::generate('App\\Entities\\Post', null, $previous, $createDiff);

    $tempPath = database_path('migrations/temp_alter_'.uniqid());

    if (! is_dir($tempPath)) {
        mkdir($tempPath, 0755, true);
    }

    file_put_contents($tempPath.'/2026_01_01_000000_create_test_generator_posts_alter_table.php', $createCode);

    // Generate alter code
    $alterDiff = SchemaDiffer::diff($previous, $current);
    $alterCode = MigrationGenerator::generate('App\\Entities\\Post', $previous, $current, $alterDiff);

    expect($alterCode)->toContain("\$table->string('description')->nullable();");
    expect($alterCode)->not->toContain('SchemaCompiler');
    expect($alterCode)->not->toContain('App\\Entities\\Post');
    expect($alterCode)->not->toContain('Post::class');

    file_put_contents($tempPath.'/2026_01_01_000001_add_description_to_test_generator_posts_alter_table.php', $alterCode);

    $this->loadMigrationsFrom($tempPath);
    Artisan::call('migrate');

    expect(Schema::hasTable('test_generator_posts_alter'))->toBeTrue();
    expect(Schema::hasColumn('test_generator_posts_alter', 'title'))->toBeTrue();
    expect(Schema::hasColumn('test_generator_posts_alter', 'description'))->toBeTrue();

    // Cleanup
    File::deleteDirectory($tempPath);
});

it('generates text column calls in migration', function () {
    $current = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Article',
        table: 'test_generator_articles',
        columns: [
            'body' => [
                'type' => 'TextColumn',
                'nullable' => true,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => [],
            ],
        ],
    );

    $diff = SchemaDiffer::diff(null, $current);
    $code = MigrationGenerator::generate('App\\Entities\\Article', null, $current, $diff);

    expect($code)->toContain("\$table->text('body')->nullable();");
});

it('generates dropForeign when dropping constrained foreign keys', function () {
    $previous = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts_fk',
        columns: [
            'user_id' => [
                'type' => 'ForeignIdColumn',
                'nullable' => false,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => [
                    'isConstrained' => true,
                    'referencesTable' => 'users',
                    'onDelete' => 'cascade',
                    'onUpdate' => 'cascade',
                ],
            ],
        ],
    );

    $current = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts_fk',
        columns: [],
    );

    $diff = SchemaDiffer::diff($previous, $current);
    $code = MigrationGenerator::generate('App\\Entities\\Post', $previous, $current, $diff);

    expect($code)->toContain("\$table->dropForeign(['user_id']);");
    expect($code)->toContain("\$table->dropColumn('user_id');");
});

it('generates dropForeign when changing constrained foreign keys', function () {
    $previous = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts_fk',
        columns: [
            'user_id' => [
                'type' => 'ForeignIdColumn',
                'nullable' => false,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => [
                    'isConstrained' => true,
                    'referencesTable' => 'users',
                    'onDelete' => 'cascade',
                    'onUpdate' => 'cascade',
                ],
            ],
        ],
    );

    $current = new SchemaSnapshot(
        modelClass: 'App\\Entities\\Post',
        table: 'test_generator_posts_fk',
        columns: [
            'user_id' => [
                'type' => 'IntColumn',
                'nullable' => false,
                'hasDefault' => false,
                'default' => null,
                'unique' => false,
                'index' => false,
                'meta' => [],
            ],
        ],
    );

    $diff = SchemaDiffer::diff($previous, $current);
    $code = MigrationGenerator::generate('App\\Entities\\Post', $previous, $current, $diff);

    expect($code)->toContain("\$table->dropForeign(['user_id']);");
    expect($code)->toContain("\$table->integer('user_id')->change();");
});
