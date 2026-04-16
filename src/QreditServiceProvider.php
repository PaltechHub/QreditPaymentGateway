<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit;

use Illuminate\Support\ServiceProvider;
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Routing\RouteMacros;
use Qredit\LaravelQredit\Tenancy\ConfigCredentialProvider;
use Qredit\LaravelQredit\Tenancy\NullTenantResolver;

class QreditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RouteMacros::register();

        $this->registerPublishing();
        $this->registerCommands();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qredit.php', 'qredit');

        // Multi-tenancy defaults — host apps override these bindings.
        $this->app->singleton(CredentialProvider::class, ConfigCredentialProvider::class);
        $this->app->singleton(TenantResolver::class, NullTenantResolver::class);

        // Central manager — owns per-tenant client cache.
        $this->app->singleton(QreditManager::class, function ($app) {
            return new QreditManager(
                $app->make(CredentialProvider::class),
                $app->make(TenantResolver::class),
            );
        });

        // `Qredit` facade → QreditManager (NOT the raw Qredit client), so single-tenant
        // consumers still get transparent `Qredit::createOrder(...)` calls, and
        // multi-tenant consumers also get `Qredit::forTenant('x')->createOrder(...)`.
        $this->app->alias(QreditManager::class, 'qredit');

        // Keep a direct binding for code that wants the raw Qredit client.
        $this->app->bind(Qredit::class, fn ($app) => $app->make(QreditManager::class)->current());
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/qredit.php' => config_path('qredit.php'),
        ], 'qredit-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'qredit-migrations');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Commands\QreditTestCommand::class,
            Commands\CallApiCommand::class,
            Commands\InstallCommand::class,
        ]);
    }

    public function provides(): array
    {
        return [
            Qredit::class,
            QreditManager::class,
            CredentialProvider::class,
            TenantResolver::class,
            'qredit',
        ];
    }
}
