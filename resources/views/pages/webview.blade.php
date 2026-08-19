{{--
    Qredit WebView host page.

    Loads the BlockBuilders payment widget inside a minimal full-bleed layout
    designed for embedding in a native-app WebView (Android `WebView`, iOS
    `WKWebView`). The widget's onSuccess/onCancel/onFail/onPending callbacks
    navigate the WebView to the merchant-supplied deep-link URLs stored in
    the RedirectUrlStore — the host app intercepts those navigations via
    shouldOverrideUrlLoading / decidePolicyForNavigationAction.

    Required variables passed by Qredit\LaravelQredit\Controllers\WebViewController:
        $reference       — Qredit payment_reference
        $accessToken     — merchant gateway access token (PaymentWidget.init requires it)
        $locale          — 'ar' | 'en'
        $direction       — 'rtl' | 'ltr'
        $urls            — Qredit\LaravelQredit\Tenancy\QreditRedirectUrls
        $signUrl         — fully qualified URL of the merchant signing endpoint
        $widgetScriptUrl — URL of payment-widget.min.js
        $widgetGatewayUrl — origin the widget iframes (sent as serverUrl)
        $height / $width — widget iframe dimensions
--}}
@php
    $mobileReturn = static fn (string $event) => url(
        '/qredit/mobile/'.$event.'?reference='.urlencode($reference).'&locale='.urlencode($locale),
    );

    $resolvedUrls = [
        'success' => $urls->successUrl ?? $mobileReturn('success'),
        'cancel'  => $urls->cancelUrl  ?? $mobileReturn('cancel'),
        'failure' => $urls->failureUrl ?? $mobileReturn('failure'),
        'pending' => $urls->pendingUrl ?? $mobileReturn('pending'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('qredit-sdk::checkout.webview.loading') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; background: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        body { overflow: hidden; }
        #qredit-webview-host {
            width: {{ $width }};
            height: {{ $height }};
            min-height: 100vh;
            display: block;
        }
        .qredit-webview-loading {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            background: #ffffff;
            color: #1e293b;
            font-size: 0.95rem;
            z-index: 10;
            transition: opacity .25s ease;
        }
        .qredit-webview-loading.is-hidden { opacity: 0; pointer-events: none; }
        .qredit-webview-loading__spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #3B5FD9;
            border-radius: 50%;
            animation: qredit-spin .8s linear infinite;
        }
        @keyframes qredit-spin { to { transform: rotate(360deg); } }
        .qredit-webview-error {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            color: #b91c1c;
            font-size: 0.95rem;
            background: #ffffff;
            z-index: 20;
        }
        .qredit-webview-error.is-shown { display: flex; }
    </style>
</head>
<body>
    <div id="qredit-webview-loading" class="qredit-webview-loading">
        <div class="qredit-webview-loading__spinner"></div>
        <div>{{ __('qredit-sdk::checkout.webview.loading') }}</div>
    </div>

    <div id="qredit-webview-error" class="qredit-webview-error">
        {{ __('qredit-sdk::checkout.webview.widget-failed') }}
    </div>

    {{-- Registered before the widget partial: its failure events can fire
         synchronously while the page is still parsing. --}}
    <script>
        (function () {
            var loading = document.getElementById('qredit-webview-loading');
            var errorEl = document.getElementById('qredit-webview-error');
            var messages = @json([
                'missing-reference' => __('qredit-sdk::checkout.webview.missing-reference'),
            ]);

            document.addEventListener('qredit:widget-loaded', function () {
                if (loading) { loading.classList.add('is-hidden'); }
            });

            document.addEventListener('qredit:widget-error', function (event) {
                if (loading) { loading.classList.add('is-hidden'); }
                if (!errorEl) { return; }

                var reason = event.detail && event.detail.reason;
                if (messages[reason]) { errorEl.textContent = messages[reason]; }

                errorEl.classList.add('is-shown');
            });
        })();
    </script>

    @include('qredit::partials.widget', [
        'containerId' => 'qredit-webview-host',
        'returnUrls'  => $resolvedUrls,
    ])
</body>
</html>
