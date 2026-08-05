<?php

declare(strict_types=1);

use Glutamate\Columns\DateTimeColumn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates datetime column', function () {
    Schema::create('test_datetimes', function ($table) {
        DateTimeColumn::make('created_at')->toBlueprint($table, 'created_at');
    });

    expect(Schema::hasColumn('test_datetimes', 'created_at'))->toBeTrue();
    expect(Schema::getColumnType('test_datetimes', 'created_at'))->toBe('datetime');
});

it('applies useCurrent default value', function () {
    Schema::create('test_datetimes_current', function ($table) {
        $table->id();
        DateTimeColumn::make('created_at')->useCurrent()->toBlueprint($table, 'created_at');
    });

    DB::table('test_datetimes_current')->insert(['id' => 1]);
    $row = DB::table('test_datetimes_current')->first();

    expect($row->created_at)->not->toBeNull();
    $date = Carbon::parse($row->created_at);
    expect($date->isToday())->toBeTrue();
});

it('returns correct phpType', function () {
    $dateTime = DateTimeColumn::make('test');
    expect($dateTime->phpType())->toBe('\Illuminate\Support\Carbon');

    $nullableDateTime = DateTimeColumn::make('test')->nullable();
    expect($nullableDateTime->phpType())->toBe('?\Illuminate\Support\Carbon');
});
