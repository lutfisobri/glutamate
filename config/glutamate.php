<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models Path
    |--------------------------------------------------------------------------
    |
    | The absolute directory path where your Eloquent Model classes are located.
    |
    */
    'models_path' => app_path('Models'),

    /*
    |--------------------------------------------------------------------------
    | Models Namespace
    |--------------------------------------------------------------------------
    |
    | The base namespace mapping for your Eloquent Model classes.
    |
    */
    'models_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Snapshot Path
    |--------------------------------------------------------------------------
    |
    | The directory path where the JSON snapshot of Model classes is saved.
    |
    */
    'snapshot_path' => storage_path('framework/glutamate/snapshots'),

];
