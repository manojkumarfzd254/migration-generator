<?php

namespace Manojkumar\MigrationGenerator;

use Illuminate\Support\ServiceProvider;
use Manojkumar\MigrationGenerator\Commands\GenerateMigrationsCommand;

class MigrationGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->commands([
            GenerateMigrationsCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/stubs/migration.stub' => resource_path('stubs/migration.stub'),
        ], 'stubs');
    }
}
