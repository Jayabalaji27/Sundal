<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Plan;
use App\Models\Workspace;
use App\Observers\UserObserver;
use App\Observers\PlanObserver;
use App\Observers\WorkspaceObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\WebhookService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the UserObserver
        User::observe(UserObserver::class);
        
        // Register the PlanObserver
        Plan::observe(PlanObserver::class);
        
        // Register the WorkspaceObserver
        Workspace::observe(WorkspaceObserver::class);
        


        // Configure dynamic storage disks
        try {
            \App\Services\DynamicStorageService::configureDynamicDisks();
        } catch (\Exception $e) {
            // Silently fail during migrations or when database is not ready
        }

        // Override Spatie media-library max_file_size dynamically from DB settings
        // This runs after auth is resolved (on actual HTTP requests)
        $this->app['events']->listen('Illuminate\Auth\Events\Authenticated', function () {
            try {
                $config = \App\Services\StorageConfigService::getStorageConfig();
                $maxBytes = ($config['max_file_size_mb'] ?? 2) * 1024 * 1024;
                config(['media-library.max_file_size' => $maxBytes]);
            } catch (\Exception $e) {
                // Silently fail
            }
        });
    }
}