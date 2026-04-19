<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Qredit\LaravelQredit\Controllers\SignController;
use Qredit\LaravelQredit\Controllers\WebhookController;

/**
 * One-line route macros for the two endpoints every Qredit integration needs.
 *
 * Usage (in the host app's routes/web.php):
 *
 *     Route::qreditSign();                                  // POST /qredit/sign
 *     Route::qreditWebhook('/qredit/webhook/{tenant}');     // POST that path
 *
 * The sign endpoint takes no arguments because the widget calls it from the
 * browser — its URL is the one the merchant passes to `PaymentWidget.init({url})`.
 *
 * The webhook endpoint accepts a path template so multi-tenant apps can use
 * route-parameter-based tenant resolution.
 */
class RouteMacros
{
    public static function register(): void
    {
        Route::macro('qreditSign', function (string $path = '/qredit/sign', array $middleware = ['web']) {
            return Route::middleware($middleware)
                ->post($path, SignController::class)
                ->name('qredit.sign');
        });

        Route::macro('qreditWebhook', function (string $path = '/qredit/webhook', array $middleware = ['api']) {
            /** @var Router $this */
            return Route::middleware($middleware)
                ->post($path, [WebhookController::class, 'handle'])
                ->name('qredit.webhook')
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
        });
    }
}
