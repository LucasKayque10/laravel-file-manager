<?php

namespace LucasBarros\LaravelFileManager;

use Illuminate\Support\ServiceProvider;
use LucasBarros\LaravelFileManager\Console\Commands\OptimizeExistingFiles;
use LucasBarros\LaravelFileManager\Services\FileManagerService;

class LaravelFileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/file-manager.php',
            'file-manager'
        );

        $this->app->singleton(
            'file-manager',
            fn ($app) => $app->make(FileManagerService::class)
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );

        $this->publishes([
            __DIR__ . '/../config/file-manager.php' => config_path(
                'file-manager.php'
            ),
        ], 'file-manager-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                OptimizeExistingFiles::class,
            ]);
        }
    }
}