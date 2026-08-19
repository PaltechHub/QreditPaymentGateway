# Mobile WebView Integration

The package ships a `/qredit/webview/show` route that renders the BlockBuilders payment widget inside a stripped, full-bleed HTML shell — no host-app chrome — designed for embedding in a native-app `WebView` / `WKWebView`.

After the widget reports an outcome (`onSuccess` / `onCancel` / `onFail` / `onPending`), the WebView navigates to a server-rendered status page at one of:

- `https://<host>/qredit/mobile/success?reference=…&locale=…`
- `https://<host>/qredit/mobile/cancel?reference=…&locale=…`
- `https://<host>/qredit/mobile/failure?reference=…&locale=…`
- `https://<host>/qredit/mobile/pending?reference=…&locale=…`

**No deep links. No custom URL schemes. No URL config from the mobile side.** The mobile app's only job is to watch its WebView URL for the `/qredit/mobile/` prefix and close the WebView natively when it appears.

> The package's `RedirectUrlStore` + `QreditRedirectUrls` primitives are still available for the *web* flow (storefronts that want to land customers on bespoke pages). The mobile flow ignores them.

## Server side

Register the routes in your host app:

```php
// routes/web.php
Route::qreditSign();      // POST /qredit/sign
Route::qreditWebview();   // GET  /qredit/webview/show  (signed)
```

…and provide your own status-page route (the host app owns the layout — the package ships a default `/qredit/mobile/{status}` controller, see your host app's docs for the path).

In your checkout flow:

```php
use Qredit\LaravelQredit\Controllers\WebViewController;
use Qredit\LaravelQredit\Facades\Qredit;

// 1. Create the gateway order + payment request
$payment = Qredit::createPayment([...]);
$record  = $payment['records'][0];
$ref     = $record['reference'];

// 2. Hand the mobile app a signed WebView URL. Pass the RAW `reference` — the
//    gateway's GET /paymentRequests?reference= only matches that one. The
//    encoded token in the hosted pay URL's #/pay/ fragment returns zero
//    records, and the widget then renders with every field blank.
$webviewUrl = WebViewController::signedUrlFor($ref, locale: 'en');
```

The host page fetches the merchant gateway access token server-side (via the SDK's cached `authenticate()`) and injects it into `PaymentWidget.init` — the widget requires it. The signing secret itself never leaves the server.

Return `$webviewUrl` to the mobile app in the JSON response. The signed URL self-expires after `qredit.widget.webview_signed_ttl_minutes` (default 120 minutes).

## Android (`WebView`)

```kotlin
webView.webViewClient = object : WebViewClient() {
    override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
        val url = request.url
        if (url.path?.startsWith("/qredit/mobile/") == true) {
            val status    = url.lastPathSegment       // "success" | "cancel" | "failure" | "pending"
            val reference = url.getQueryParameter("reference")
            handleQreditDone(status, reference)
            (view.context as? Activity)?.finish()
            return true
        }
        return false
    }
}
webView.loadUrl(webviewUrl)
```

Optionally bind a JS bridge so the host's "Back to App" button on the status page works even without URL interception:

```kotlin
webView.addJavascriptInterface(object {
    @JavascriptInterface
    fun close(status: String, reference: String) {
        runOnUiThread { handleQreditDone(status, reference); finish() }
    }
}, "AndroidQredit")
```

## iOS (`WKWebView`)

```swift
func webView(_ webView: WKWebView,
             decidePolicyFor navigationAction: WKNavigationAction,
             decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
    guard let url = navigationAction.request.url else { decisionHandler(.allow); return }

    if url.path.hasPrefix("/qredit/mobile/") {
        let status    = url.lastPathComponent
        let reference = URLComponents(url: url, resolvingAgainstBaseURL: false)?
            .queryItems?.first(where: { $0.name == "reference" })?.value
        handleQreditDone(status: status, reference: reference)
        decisionHandler(.cancel)
        return
    }
    decisionHandler(.allow)
}
```

Optional script-message handler for the "Back to App" button:

```swift
let config = WKWebViewConfiguration()
config.userContentController.add(self, name: "qreditClose")
```

## Securing the WebView URL

- The route is signed-URL-protected by Laravel. A stolen `payment_reference` alone is useless without a fresh signature.
- HTTPS in production. iOS App Transport Security and Android's network-security-config both reject plain HTTP by default.
- The merchant signing secret never leaves the server (the existing `/qredit/sign` proxy controller handles HMAC).

## When the widget fails to load

If `payment-widget.min.js` can't load (network issue, CSP, ad-blocker on Android WebView), the host page shows a translated error message and stops there. The mobile app can detect this by listening for a fixed-position `<div class="qredit-webview-error is-shown">` becoming visible, or simply timing out and presenting a retry. Best practice: include the WebView's loaded URL + an `X-Mobile-App-Version` header so server logs can correlate retries.
