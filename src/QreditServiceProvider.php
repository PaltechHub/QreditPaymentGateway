<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class QreditServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerCommands();
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/qredit.php',
            'qredit'
        );

        $this->app->singleton(Qredit::class, function ($app) {
            return new Qredit(
                config('qredit.api_key'),
                config('qredit.sandbox', true)
            );
        });

        $this->app->alias(Qredit::class, 'qredit');
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/qredit.php' => config_path('qredit.php'),
            ], 'qredit-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'qredit-migrations');

            // Publish views (if we add any later)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/qredit'),
            ], 'qredit-views');

            // Publish all assets
            $this->publishes([
                __DIR__ . '/../config/qredit.php' => config_path('qredit.php'),
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'qredit');
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (config('qredit.webhook.enabled', false)) {
            Route::group($this->webhookRouteConfiguration(), function () {
                Route::post(
                    config('qredit.webhook.path', '/qredit/webhook'),
                    [Controllers\WebhookController::class, 'handle']
                )->name('qredit.webhook');
            });
        }
    }

    /**
     * Get the webhook route group configuration.
     */
    protected function webhookRouteConfiguration(): array
    {
        return [
            'prefix' => config('qredit.webhook.prefix', ''),
            'middleware' => config('qredit.webhook.middleware', []),
        ];
    }

    /**
     * Register the package's artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\QreditTestCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            Qredit::class,
            'qredit',
        ];
    }
}