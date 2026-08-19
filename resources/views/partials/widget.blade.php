{{--
    Qredit checkout widget — shared embed.

    Renders the widget container, loads the BlockBuilders loader, and wires the
    outcome callbacks. Deliberately ships NO chrome (no spinner, no error box)
    so it can sit inside a bare WebView shell or a fully themed storefront page
    without fighting either one's styling.

    Instead of styling anything, it dispatches two events on `document` that the
    host page listens for to drive its own UI:

        qredit:widget-loaded   the iframe rendered — hide your loading state
        qredit:widget-error    the widget could not start — show your error state
                               (event.detail.reason: 'missing-reference' |
                               'no-token' | 'script-failed' | 'init-failed')

    Required:
        $reference        raw Qredit payment reference (NOT the #/pay/ token)
        $accessToken      merchant gateway access token, fetched server-side
        $locale           'ar' | 'en'
        $signUrl          absolute URL of the merchant signing endpoint
        $widgetScriptUrl  loader URL (config qredit.widget.script_url)
        $widgetGatewayUrl origin the widget iframes (config qredit.widget.gateway_url)
        $returnUrls       ['success' => …, 'cancel' => …, 'failure' => …, 'pending' => …]

    Optional:
        $containerId      DOM id for the container (default 'qredit-widget-host')
        $height / $width  iframe dimensions (default: config qredit.widget.*)
--}}
@php
    $qreditContainerId = $containerId ?? 'qredit-widget-host';
    $qreditHeight = $height ?? config('qredit.widget.default_height', '1000px');
    $qreditWidth = $width ?? config('qredit.widget.default_width', '100%');
@endphp

<div id="{{ $qreditContainerId }}"></div>

@if ($reference === '')
    <script>
        document.dispatchEvent(new CustomEvent('qredit:widget-error', {
            detail: { reason: 'missing-reference' }
        }));
    </script>
@elseif ($accessToken === '')
    {{-- Gateway auth failed server-side; the widget cannot boot without a token. --}}
    <script>
        document.dispatchEvent(new CustomEvent('qredit:widget-error', {
            detail: { reason: 'no-token' }
        }));
    </script>
@else
    <script src="{{ $widgetScriptUrl }}"
            onerror="document.dispatchEvent(new CustomEvent('qredit:widget-error',{detail:{reason:'script-failed'}}))"></script>
    <script>
        (function () {
            var returnTo  = @json($returnUrls);
            var lang      = @json($locale);
            var reference = @json($reference);
            var token     = @json($accessToken);
            var signUrl   = @json($signUrl);

            function emit(name, detail) {
                document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
            }

            function goTo(url) {
                if (!url) { return; }
                try { window.location.assign(url); }
                catch (_) { window.location.href = url; }
            }

            if (typeof PaymentWidget === 'undefined' || !PaymentWidget.init) {
                emit('qredit:widget-error', { reason: 'script-failed' });
                return;
            }

            try {
                PaymentWidget.init({
                    containerId: @json($qreditContainerId),
                    {{-- Explicit since 1.1.0: its own default is the UAT portal. --}}
                    serverUrl:   @json($widgetGatewayUrl ?? config('qredit.widget.gateway_url')),
                    ereference:  reference,
                    token:       token,
                    url:         signUrl,
                    lang:        lang,
                    width:       @json($qreditWidth),
                    height:      @json($qreditHeight),
                    onLoad: function () {
                        emit('qredit:widget-loaded');
                    },
                    onSuccess: function () {
                        try { PaymentWidget.destroy(); } catch (_) {}
                        goTo(returnTo.success);
                    },
                    onCancel: function () {
                        try { PaymentWidget.destroy(); } catch (_) {}
                        goTo(returnTo.cancel);
                    },
                    onFail: function () {
                        goTo(returnTo.failure);
                    },
                    onPending: function () {
                        goTo(returnTo.pending);
                    },
                    onError: function (data) {
                        // A payload with a `status` is a payment outcome, not a
                        // boot failure — route it like any other failed payment.
                        if (data && data.status) {
                            goTo(returnTo.failure);
                        } else {
                            emit('qredit:widget-error', { reason: 'init-failed' });
                        }
                    }
                });
            } catch (err) {
                emit('qredit:widget-error', { reason: 'init-failed' });
            }
        })();
    </script>
@endif
