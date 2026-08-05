<?php

declare(strict_types=1);

arch()->preset()->php()->ignoring(['debug_backtrace', 'tempnam', 'var_export']);

arch()->preset()->security()->ignoring('tempnam');

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Glutamate')
    ->toUseStrictTypes();
