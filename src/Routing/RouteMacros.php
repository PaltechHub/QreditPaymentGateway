<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Routing;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Qredit\LaravelQredit\Controllers\SignController;
use Qredit\LaravelQredit\Controllers\WebhookController;
use Qredit\LaravelQredit\Controllers\WebViewController;

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
        // The widget POSTs here from inside the gateway's own iframe origin
        // (pay.blockbuilders.ps), so this is always a cross-origin request with
        // no session and no CSRF token — the same shape as the webhook.
        Route::macro('qreditSign', function (string $path = '/qredit/sign', array $middleware = ['web']) {
            return Route::middleware($middleware)
                ->post($path, SignController::class)
                ->name('qredit.sign')
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
        });

        // The webhook endpoint accepts a path template so multi-tenant apps can use
        // route-parameter-based tenant resolution.
        Route::macro('qreditWebhook', function (string $path = '/qredit/webhook', array $middleware = ['api']) {
            /** @var Router $this */
            return Route::middleware($middleware)
                ->post($path, [WebhookController::class, 'handle'])
                ->name('qredit.webhook')
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
        });

        // WebView host page — renders the BlockBuilders widget inside a minimal
        // layout suitable for embedding in a native-app WebView. Always gated by
        // Laravel's signed-URL middleware so the payment_reference can't be
        // replayed without a fresh signature.
        Route::macro('qreditWebview', function (string $path = '/qredit/webview/show', array $middleware = ['web', 'signed']) {
            return Route::middleware($middleware)
                ->get($path, [WebViewController::class, 'show'])
                ->name('qredit.webview.show');
        });
    }
}
