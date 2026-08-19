<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Qredit\LaravelQredit\Contracts\CredentialProvider;
use Qredit\LaravelQredit\Contracts\RedirectUrlStore;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\Routing\RouteMacros;
use Qredit\LaravelQredit\Stores\CacheRedirectUrlStore;
use Qredit\LaravelQredit\Stores\DatabaseRedirectUrlStore;
use Qredit\LaravelQredit\Stores\HybridRedirectUrlStore;
use Qredit\LaravelQredit\Tenancy\ConfigCredentialProvider;
use Qredit\LaravelQredit\Tenancy\NullTenantResolver;

class QreditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RouteMacros::register();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'qredit');

        // Translations use their own namespace on purpose: host apps (Bagisto)
        // register a `qredit` translation namespace of their own, and Laravel's
        // translation loader keeps only ONE hint path per namespace (unlike
        // views, which merge). A shared namespace would silently shadow these
        // files and render raw keys like `qredit::checkout.webview.loading`.
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'qredit-sdk');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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
                $app->make(RedirectUrlStore::class),
            );
        });

        // `Qredit` facade → QreditManager (NOT the raw Qredit client), so single-tenant
        // consumers still get transparent `Qredit::createOrder(...)` calls, and
        // multi-tenant consumers also get `Qredit::forTenant('x')->createOrder(...)`.
        $this->app->alias(QreditManager::class, 'qredit');

        // Keep a direct binding for code that wants the raw Qredit client.
        $this->app->bind(Qredit::class, fn ($app) => $app->make(QreditManager::class)->current());

        $this->registerRedirectUrlStore();
        $this->registerCorsPath();
    }

    /**
     * The checkout widget POSTs to the sign endpoint from inside the gateway's
     * own iframe origin, so that call is cross-origin. Laravel's global
     * HandleCors middleware only acts on paths listed in `cors.paths` — without
     * this the browser preflight comes back with no Access-Control-Allow-*
     * headers and the widget never gets its signature.
     *
     * Done here rather than in the route macro so it survives `route:cache`.
     * Apps that register the macro on a non-default path add that path to
     * `cors.paths` themselves.
     */
    protected function registerCorsPath(): void
    {
        $path = ltrim((string) config('qredit.sign_path', 'qredit/sign'), '/');

        $paths = (array) config('cors.paths', []);

        if (! in_array($path, $paths, true)) {
            $paths[] = $path;
            config(['cors.paths' => $paths]);
        }
    }

    /**
     * Resolve the redirect-URL store from config. Host apps can either set
     * `qredit.redirect_urls.store` to a shorthand ('hybrid' | 'cache' | 'database')
     * or to a fully-qualified class name, or bind RedirectUrlStore::class
     * directly to their own implementation before this provider boots.
     */
    protected function registerRedirectUrlStore(): void
    {
        $this->app->singleton(RedirectUrlStore::class, function ($app) {
            $ttl = (int) config('qredit.redirect_urls.ttl_minutes', 60);

            $cacheStore = new CacheRedirectUrlStore($app->make(CacheRepository::class), $ttl);
            $databaseStore = new DatabaseRedirectUrlStore($ttl);

            $configured = config('qredit.redirect_urls.store', 'hybrid');

            return match (true) {
                $configured === 'cache' => $cacheStore,
                $configured === 'database' => $databaseStore,
                $configured === 'hybrid' => new HybridRedirectUrlStore($cacheStore, $databaseStore),
                is_string($configured) && class_exists($configured) => $app->make($configured),
                default => new HybridRedirectUrlStore($cacheStore, $databaseStore),
            };
        });
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
            __DIR__.'/../resources/views' => resource_path('views/vendor/qredit'),
        ], 'qredit-views');

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
            RedirectUrlStore::class,
            'qredit',
        ];
    }
}
