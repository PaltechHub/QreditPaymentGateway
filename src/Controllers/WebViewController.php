<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Qredit\LaravelQredit\Contracts\TenantResolver;
use Qredit\LaravelQredit\QreditManager;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

/**
 * Renders the BlockBuilders payment widget inside a minimal layout designed
 * for embedding in a native-app WebView (no shop chrome).
 *
 * The route is signed (Laravel's signed-URL middleware) so a stolen payment
 * reference alone can't be replayed.
 *
 * The widget's onSuccess / onCancel / onFail / onPending callbacks navigate
 * the WebView to the URLs stored against this payment reference via the
 * RedirectUrlStore. Mobile apps intercept those navigations and route natively.
 */
class WebViewController extends Controller
{
    public function __construct(
        protected QreditManager $qredit,
        protected TenantResolver $tenants,
    ) {}

    public function show(Request $request): View
    {
        $reference = (string) $request->query('reference', '');
        $tenantId = $this->tenants->currentTenantId($request);
        $locale = $this->resolveLocale($request);

        $urls = $this->qredit->resolveRedirectUrls($reference, $tenantId)
            ?? new QreditRedirectUrls;

        return view('qredit::pages.webview', $this->qredit->widgetProps($reference, $locale, $tenantId) + [
            'direction' => strtolower($locale) === 'ar' ? 'rtl' : 'ltr',
            'urls' => $urls,
            'height' => (string) config('qredit.widget.webview_height', '100vh'),
            'width' => (string) config('qredit.widget.default_width', '100%'),
        ]);
    }

    /**
     * Build a signed URL the mobile app can open inside its WebView. TTL
     * is sourced from `qredit.widget.webview_signed_ttl_minutes`.
     *
     * $paymentReference is the RAW `reference` createPayment returned — not the
     * encoded token in the hosted pay URL's `#/pay/` fragment. The gateway's
     * `GET /paymentRequests?reference=` only matches the raw one; passing the
     * encoded token returns zero records and the widget renders blank.
     */
    public static function signedUrlFor(string $paymentReference, string $locale = 'en'): string
    {
        $ttl = (int) config('qredit.widget.webview_signed_ttl_minutes', 120);

        return URL::temporarySignedRoute(
            'qredit.webview.show',
            now()->addMinutes($ttl),
            [
                'reference' => $paymentReference,
                'lang' => $locale,
            ],
        );
    }

    protected function resolveLocale(Request $request): string
    {
        $lang = $request->query('lang') ?? $request->header('Accept-Language');

        return is_string($lang) && $lang !== '' ? $lang : 'en';
    }
}
