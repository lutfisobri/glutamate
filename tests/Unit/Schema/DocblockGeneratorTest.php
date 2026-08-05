<?php

declare(strict_types=1);

namespace Glutamate\Tests\Unit\Schema;

use Glutamate\Schema\DocblockGenerator;
use Glutamate\SchemaCompiler;

$tempFiles = [];

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    $tempFiles = [];
});

it('updates docblocks on models correctly', function () use (&$tempFiles) {
    $className = 'TempModelForDocblockTest_'.uniqid();
    $tempFile = sys_get_temp_dir().'/'.$className.'.php';
    $tempFiles[] = $tempFile;

    $code = "<?php

namespace Glutamate\\Tests\\Unit\\Schema;

use Illuminate\\Database\\Eloquent\\Model;
use Glutamate\\Columns\\IdColumn;
use Glutamate\\Columns\\StringColumn;

class {$className} extends Model
{
    public static function id(): IdColumn
    {
        return IdColumn::make()->as('id');
    }

    public static function name(): StringColumn
    {
        return StringColumn::make()->as('name');
    }
}
";
    file_put_contents($tempFile, $code);
    require_once $tempFile;

    $fqcn = "Glutamate\\Tests\\Unit\\Schema\\{$className}";

    $columns = SchemaCompiler::compile($fqcn);
    DocblockGenerator::update($fqcn, $columns);

    $newContent = file_get_contents($tempFile);

    expect($newContent)->toContain('* @property int $id');
    expect($newContent)->toContain('* @property string $name');

    // Test with existing docblock
    $codeWithDoc = "<?php

namespace Glutamate\\Tests\\Unit\\Schema;

use Illuminate\\Database\\Eloquent\\Model;
use Glutamate\\Columns\\IdColumn;
use Glutamate\\Columns\\StringColumn;

/**
 * Some existing description.
 *
 * @property string \$old_prop
 * @property \App\Models\Comment[] \$comments
 */
class {$className}Doc extends Model
{
    public static function id(): IdColumn
    {
        return IdColumn::make()->as('id');
    }

    public static function name(): StringColumn
    {
        return StringColumn::make()->as('name');
    }
}
";
    $classNameDoc = $className.'Doc';
    $tempFileDoc = sys_get_temp_dir().'/'.$classNameDoc.'.php';
    $tempFiles[] = $tempFileDoc;
    file_put_contents($tempFileDoc, $codeWithDoc);
    require_once $tempFileDoc;

    $fqcnDoc = "Glutamate\\Tests\\Unit\\Schema\\{$classNameDoc}";
    $columnsDoc = SchemaCompiler::compile($fqcnDoc);
    DocblockGenerator::update($fqcnDoc, $columnsDoc, ['old_prop']);

    $newContentDoc = file_get_contents($tempFileDoc);

    expect($newContentDoc)->toContain('Some existing description.');
    expect($newContentDoc)->toContain('* @property int $id');
    expect($newContentDoc)->toContain('* @property string $name');
    expect($newContentDoc)->toContain('* @property \App\Models\Comment[] $comments');
    expect($newContentDoc)->not->toContain('@property string $old_prop');

    // Test with PHP 8 attributes but no docblock
    $codeWithAttr = "<?php

namespace Glutamate\\Tests\\Unit\\Schema;

use Illuminate\\Database\\Eloquent\\Model;
use Glutamate\\Columns\\IdColumn;
use Glutamate\\Columns\\StringColumn;

#[\Illuminate\Database\Eloquent\Attributes\Fillable('name')]
class {$className}Attr extends Model
{
    public static function id(): IdColumn
    {
        return IdColumn::make()->as('id');
    }

    public static function name(): StringColumn
    {
        return StringColumn::make()->as('name');
    }
}
";
    $classNameAttr = $className.'Attr';
    $tempFileAttr = sys_get_temp_dir().'/'.$classNameAttr.'.php';
    $tempFiles[] = $tempFileAttr;
    file_put_contents($tempFileAttr, $codeWithAttr);
    require_once $tempFileAttr;

    $fqcnAttr = "Glutamate\\Tests\\Unit\\Schema\\{$classNameAttr}";
    $columnsAttr = SchemaCompiler::compile($fqcnAttr);
    DocblockGenerator::update($fqcnAttr, $columnsAttr);

    $newContentAttr = file_get_contents($tempFileAttr);

    // Verify it is placed above the attribute, i.e., docblock comes first, then #[Fillable
    $docblockPos = strpos($newContentAttr, '/**');
    $attrPos = strpos($newContentAttr, '#[\Illuminate\Database\Eloquent\Attributes\Fillable');
    $classPos = strpos($newContentAttr, 'class '.$classNameAttr);

    expect($docblockPos)->not->toBeFalse();
    expect($attrPos)->not->toBeFalse();
    expect($classPos)->not->toBeFalse();

    expect($docblockPos)->toBeLessThan($attrPos);
    expect($attrPos)->toBeLessThan($classPos);
});
