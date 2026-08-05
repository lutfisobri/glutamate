<?php

declare(strict_types=1);

use Glutamate\Columns\IntColumn;
use Glutamate\Columns\StringColumn;
use Glutamate\SchemaCompiler;
use Illuminate\Database\Eloquent\Model;

class TestValidModel extends Model
{
    public static function id(): IntColumn
    {
        return IntColumn::make()->unsigned()->autoIncrement()->as(__FUNCTION__);
    }

    public static function email(): StringColumn
    {
        return StringColumn::make()->maxLength(191)->unique()->as(__FUNCTION__);
    }

    public static function age(): IntColumn
    {
        return IntColumn::make()->unsigned()->nullable()->as(__FUNCTION__);
    }

    public static function someHelper(): string
    {
        return 'not a column';
    }
}

class TestCustomNameModel extends Model
{
    public static function emailAddress(): StringColumn
    {
        return StringColumn::make()->as('custom_email');
    }
}

class TestDuplicateNameModel extends Model
{
    public static function email(): StringColumn
    {
        return StringColumn::make()->as(__FUNCTION__);
    }

    public static function emailAddress(): StringColumn
    {
        return StringColumn::make()->as('email');
    }
}

class TestNonModel
{
    //
}

it('throws InvalidArgumentException when compiling non-model classes', function () {
    expect(function () {
        SchemaCompiler::compile(TestNonModel::class);
    })->toThrow(InvalidArgumentException::class, TestNonModel::class.' must extend '.Model::class);
});

it('compiles valid models and returns resolved columns', function () {
    $columns = SchemaCompiler::compile(TestValidModel::class);

    expect($columns)->toHaveCount(3);
    expect($columns)->toHaveKeys(['id', 'email', 'age']);
    expect($columns['id'])->toBeInstanceOf(IntColumn::class);
    expect($columns['email'])->toBeInstanceOf(StringColumn::class);
    expect($columns['age'])->toBeInstanceOf(IntColumn::class);
});

it('does not invoke static methods that do not return a Column subtype', function () {
    // TestValidModel has public static function someHelper() returning a string.
    // It should be skipped, not thrown or crashed.
    $columns = SchemaCompiler::compile(TestValidModel::class);
    expect($columns)->not->toHaveKey('some_helper');
});

it('resolves custom names using as() modifier', function () {
    $columns = SchemaCompiler::compile(TestCustomNameModel::class);

    expect($columns)->toHaveCount(1);
    expect($columns)->toHaveKey('custom_email');
    expect($columns['custom_email'])->toBeInstanceOf(StringColumn::class);
});

it('throws LogicException when compiling duplicate column names', function () {
    expect(function () {
        SchemaCompiler::compile(TestDuplicateNameModel::class);
    })->toThrow(LogicException::class, "Duplicate column name 'email' resolved");
});

class TestUnnamedColumnModel extends Model
{
    public static function email(): StringColumn
    {
        return StringColumn::make();
    }
}

it('throws LogicException when compiling a column without explicit name', function () {
    expect(function () {
        SchemaCompiler::compile(TestUnnamedColumnModel::class);
    })->toThrow(LogicException::class, 'must have an explicit name');
});
