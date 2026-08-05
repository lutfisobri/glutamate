<?php

declare(strict_types=1);

namespace Glutamate;

use Glutamate\Console\Commands\GenerateCommand;
use Glutamate\Console\Commands\PushCommand;
use Glutamate\Console\Commands\SyncCommand;
use Illuminate\Support\ServiceProvider;

class GlutamateServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/glutamate.php', 'glutamate');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/glutamate'));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/glutamate.php' => config_path('glutamate.php'),
        ], ['glutamate', 'glutamate-config']);

        $this->commands([
            GenerateCommand::class,
            PushCommand::class,
            SyncCommand::class,
        ]);
    }
}
