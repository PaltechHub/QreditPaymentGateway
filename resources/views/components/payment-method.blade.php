{{--
    Qredit payment method selector — drop into any checkout page.

    Usage:
        @include('qredit::components.payment-method', [
            'title'       => 'Qredit',
            'description' => 'Secure payment',
            'sandbox'     => true,
            'locale'      => 'ar',    // 'ar' for RTL, anything else for LTR
            'image'       => null,    // optional logo URL
            'selected'    => false,   // whether this method is currently active
            'inputName'   => 'payment[method]',
            'inputValue'  => 'qredit',
        ])

    The component is self-contained — no Tailwind build required. Inline styles
    use the same palette as the Qredit hosted checkout (blue-600 / #3B5FD9).
--}}

@php
    $dir   = \Qredit\LaravelQredit\Helpers\Locale::direction($locale ?? 'en');
    $title = $title ?? __('qredit::checkout.title');
    $desc  = $description ?? __('qredit::checkout.description');
    $isSandbox = $sandbox ?? false;
    $isSelected = $selected ?? false;
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=El+Messiri:wght@400;600;700&family=Changa:wght@400;500;600&display=swap');
    .qredit-card{font-family:'El Messiri','Changa',sans-serif;border:2px solid #e5e7eb;border-radius:12px;padding:16px 20px;cursor:pointer;transition:border-color .15s,box-shadow .15s;background:#fff}
    .qredit-card:hover{border-color:#93a8f0}
    .qredit-card.qredit-selected{border-color:#3B5FD9;box-shadow:0 0 0 3px rgba(59,95,217,.12)}
    .qredit-card .qredit-radio{width:20px;height:20px;border:2px solid #cbd5e1;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:border-color .15s}
    .qredit-card.qredit-selected .qredit-radio{border-color:#3B5FD9}
    .qredit-card.qredit-selected .qredit-radio::after{content:'';width:10px;height:10px;background:#3B5FD9;border-radius:50%}
    .qredit-title{font-weight:600;font-size:1rem;color:#1e293b}
    .qredit-desc{font-size:.8rem;color:#64748b;margin-top:2px}
    .qredit-logo{height:28px;object-fit:contain}
    .qredit-info{margin-top:12px;padding:12px 16px;background:linear-gradient(135deg,#f0f4ff 0%,#e8eeff 100%);border-radius:8px;display:none}
    .qredit-selected .qredit-info{display:block}
    .qredit-info-row{display:flex;align-items:center;gap:8px;font-size:.8rem;color:#475569;margin-bottom:4px}
    .qredit-info-row:last-child{margin-bottom:0}
    .qredit-info-row svg{width:16px;height:16px;color:#3B5FD9;flex-shrink:0}
    .qredit-sandbox-badge{display:inline-block;padding:2px 8px;background:#fef3c7;color:#92400e;font-size:.7rem;font-weight:600;border-radius:4px;margin-top:6px}
    .qredit-secure{display:flex;align-items:center;gap:6px;margin-top:10px;font-size:.72rem;color:#94a3b8}
    .qredit-secure svg{width:14px;height:14px}
</style>

<label class="qredit-card {{ $isSelected ? 'qredit-selected' : '' }}" dir="{{ $dir }}" id="qredit-payment-card" onclick="document.getElementById('qredit-radio-input').checked=true;this.classList.add('qredit-selected')">
    <input
        type="radio"
        name="{{ $inputName ?? 'payment[method]' }}"
        value="{{ $inputValue ?? 'qredit' }}"
        id="qredit-radio-input"
        style="display:none"
        {{ $isSelected ? 'checked' : '' }}
    />

    <div style="display:flex;align-items:center;gap:12px;">
        <div class="qredit-radio"></div>

        <div style="flex:1;min-width:0">
            <div class="qredit-title">{{ $title }}</div>
            @if ($desc)
                <div class="qredit-desc">{{ $desc }}</div>
            @endif
        </div>

        @if (!empty($image))
            <img src="{{ $image }}" alt="Qredit" class="qredit-logo" />
        @else
            {{-- Inline Qredit "Q" icon — no external dependency --}}
            <div style="width:40px;height:40px;background:linear-gradient(135deg,#3B5FD9,#5b7bf5);border-radius:10px;display:flex;align-items:center;justify-content:center">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M17.5 21h-3l-2-3h-.5a7 7 0 1 1 4.43-1.5L17.5 21ZM12 16a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" fill="#fff"/></svg>
            </div>
        @endif
    </div>

    <div class="qredit-info">
        <div class="qredit-info-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
            <span>{{ __('qredit::checkout.secure-encrypted') }}</span>
        </div>
        <div class="qredit-info-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
            <span>{{ __('qredit::checkout.card-qr') }}</span>
        </div>
        <div class="qredit-info-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <span>{{ __('qredit::checkout.no-hidden-fees') }}</span>
        </div>

        @if ($isSandbox)
            <div class="qredit-sandbox-badge">{{ __('qredit::checkout.test-mode') }}</div>
        @endif

        <div class="qredit-secure">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            {{ __('qredit::checkout.secure-footer') }}
        </div>
    </div>
</label>
