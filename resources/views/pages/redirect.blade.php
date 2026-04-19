{{--
    Qredit redirect page — shown while the customer is being sent to the
    hosted checkout. Self-contained HTML; does not depend on any host app
    layout. Override via `php artisan vendor:publish --tag=qredit-views`.

    Required variables:
        $redirectUrl  — the Qredit checkout URL to redirect to
        $locale       — 'ar' | 'en' (defaults to 'en')
        $amount       — formatted total (e.g. '10.00 ₪') — optional
        $reference    — payment reference — optional
--}}

@php
    $locale ??= 'en';
    $dir    = \Qredit\LaravelQredit\Helpers\Locale::direction($locale);
    $amount ??= null;
    $ref    = $reference ?? null;
@endphp

<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('qredit::checkout.redirecting') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=El+Messiri:wght@400;600;700&family=Changa:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'El Messiri', 'Changa', sans-serif;
            background: linear-gradient(160deg, #eef2ff 0%, #e0e7ff 40%, #dbeafe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #1e293b;
        }
        .qredit-redirect {
            width: 100%;
            max-width: 440px;
            text-align: center;
        }

        /* Header badge */
        .qredit-redirect__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #3B5FD9, #5b7bf5);
            color: #fff;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: .9rem;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(59, 95, 217, .25);
        }
        .qredit-redirect__badge svg {
            width: 22px;
            height: 22px;
        }

        /* Card */
        .qredit-redirect__card {
            background: #fff;
            border-radius: 20px;
            padding: 40px 32px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, .06);
        }

        /* Spinner */
        .qredit-redirect__spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e2e8f0;
            border-top-color: #3B5FD9;
            border-radius: 50%;
            animation: qredit-spin 0.8s linear infinite;
            margin: 0 auto 24px;
        }
        @keyframes qredit-spin { to { transform: rotate(360deg); } }

        .qredit-redirect__title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .qredit-redirect__subtitle {
            font-size: .9rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* Amount + reference chips */
        .qredit-redirect__chips {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .qredit-redirect__chip {
            background: linear-gradient(135deg, #3B5FD9, #5b7bf5);
            color: #fff;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: .85rem;
            font-weight: 600;
        }
        .qredit-redirect__chip-label {
            font-size: .7rem;
            font-weight: 400;
            opacity: .8;
            display: block;
            margin-bottom: 2px;
        }

        /* Progress bar */
        .qredit-redirect__progress {
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .qredit-redirect__progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3B5FD9, #5b7bf5);
            border-radius: 2px;
            animation: qredit-progress 2.5s ease-in-out forwards;
        }
        @keyframes qredit-progress { from { width: 0%; } to { width: 100%; } }

        .qredit-redirect__warn {
            font-size: .78rem;
            color: #94a3b8;
            margin-bottom: 16px;
        }

        .qredit-redirect__link {
            font-size: .78rem;
            color: #3B5FD9;
            text-decoration: none;
        }
        .qredit-redirect__link:hover {
            text-decoration: underline;
        }

        /* Footer */
        .qredit-redirect__footer {
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: .72rem;
            color: #94a3b8;
        }
        .qredit-redirect__footer svg {
            width: 14px;
            height: 14px;
        }
    </style>
</head>
<body>
    <div class="qredit-redirect">
        <div class="qredit-redirect__badge">
            <svg viewBox="0 0 24 24" fill="none"><path d="M17.5 21h-3l-2-3h-.5a7 7 0 1 1 4.43-1.5L17.5 21ZM12 16a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" fill="#fff"/></svg>
            Qredit
        </div>

        <div class="qredit-redirect__card">
            <div class="qredit-redirect__spinner"></div>

            <h1 class="qredit-redirect__title">{{ __('qredit::checkout.redirecting') }}</h1>
            <p class="qredit-redirect__subtitle">{{ __('qredit::checkout.please-wait') }}</p>

            @if ($amount || $ref)
                <div class="qredit-redirect__chips">
                    @if ($amount)
                        <div class="qredit-redirect__chip">
                            <span class="qredit-redirect__chip-label">{{ __('qredit::checkout.amount') }}</span>
                            {{ $amount }}
                        </div>
                    @endif
                    @if ($ref)
                        <div class="qredit-redirect__chip">
                            <span class="qredit-redirect__chip-label">{{ __('qredit::checkout.reference') }}</span>
                            {{ $ref }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="qredit-redirect__progress">
                <div class="qredit-redirect__progress-bar"></div>
            </div>

            <p class="qredit-redirect__warn">{{ __('qredit::checkout.do-not-refresh') }}</p>

            <a href="{{ $redirectUrl }}" class="qredit-redirect__link">
                {{ __('qredit::checkout.click-here') }}
            </a>
        </div>

        <div class="qredit-redirect__footer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            {{ __('qredit::checkout.secure-footer') }}
        </div>
    </div>

    <script>
        setTimeout(function() {
            window.location.href = @json($redirectUrl);
        }, 2500);
    </script>
</body>
</html>
