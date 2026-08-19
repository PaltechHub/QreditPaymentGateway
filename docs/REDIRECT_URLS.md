# Post-payment Redirect URLs

The Qredit gateway does not accept `success_url` / `cancel_url` parameters in its create-payment payload. To send the customer somewhere specific after the widget reports an outcome, this SDK persists the four widget-callback URLs server-side, keyed by the gateway's `payment_reference`, and gives you helpers to look them back up when the customer returns or when the mobile WebView reports a result.

## The four URLs

The BlockBuilders widget fires four callbacks. Map each to a URL up front:

| Widget callback | Stored as       | Fires when                              |
|-----------------|-----------------|-----------------------------------------|
| `onSuccess`     | `successUrl`    | Payment authorized + captured           |
| `onCancel`      | `cancelUrl`     | Customer cancelled inside the iframe    |
| `onFail`        | `failureUrl`    | Payment attempted and declined          |
| `onPending`     | `pendingUrl`    | Awaiting async bank settlement          |

Each is optional individually, but the WebView host page expects a URL for every callback path you intend to support — when a callback fires and the corresponding URL is null, the WebView falls back to the package's default web routes (`/qredit/success?reference=…`, `/qredit/cancel?…`, `/qredit/failure?…`).

## Facade methods

```php
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

// 1. After createPayment(), persist the URLs against the returned reference
Qredit::rememberRedirectUrls(
    $paymentResponse['records'][0]['reference'],
    QreditRedirectUrls::fromArray([
        'success_url' => 'https://shop.test/order/thanks',
        'cancel_url'  => 'eshophub://order/cancel',
        'failure_url' => 'eshophub://order/failure',
        'pending_url' => 'eshophub://order/pending',
    ]),
);

// 2. On the return controller (or WebView host), look them up
$urls = Qredit::resolveRedirectUrls($paymentReference);
return redirect($urls->successUrl ?? route('shop.checkout.success'));

// 3. After you've sent the customer onward, free the slot
Qredit::forgetRedirectUrls($paymentReference);
```

All three accept an optional `?string $tenantId = null` argument so multi-tenant apps can scope explicitly (mandatory in queue jobs).

## Choosing a store

`config/qredit.php → redirect_urls.store`:

| Value      | Backing                          | When to pick                                        |
|------------|----------------------------------|-----------------------------------------------------|
| `hybrid`   | DB write-through + cache read    | Default. Crash safety + fast reads.                 |
| `cache`    | Laravel cache only               | Lightweight apps; no migration; OK if cache flushes cause failed look-ups |
| `database` | `qredit_payment_redirect_urls`   | Cache is unavailable, or you want a single source of truth |
| FQCN       | Your own `RedirectUrlStore`      | Bespoke storage (Redis hash, encrypted blob, ...)   |

URLs in the database are encrypted at rest via Laravel's `encrypted` cast — no plaintext URLs in your warehouse exports.

## Validating URLs

`QreditRedirectUrls::isAcceptableUrl($url)` and `QreditRedirectUrls::assertAllowed()` enforce a denylist of dangerous schemes (`javascript`, `data`, `file`, `vbscript`, `blob`). Any other scheme passes — `https://`, `eshophub://`, `myapp://`, `whatever://` — so you don't have to update server config when a new mobile build needs a new scheme.

Override the denylist via `config('qredit.redirect_urls.blocked_schemes')` or pass an explicit list to `assertAllowed([...])`.

## Linking to the WebView host

```php
use Qredit\LaravelQredit\Controllers\WebViewController;

$webviewUrl = WebViewController::signedUrlFor($paymentReference, locale: 'ar');
// → https://shop.test/qredit/webview/show?reference=…&lang=ar&signature=…
```

The route is protected by Laravel's signed-URL middleware. TTL is configurable via `qredit.widget.webview_signed_ttl_minutes` (default 120).
