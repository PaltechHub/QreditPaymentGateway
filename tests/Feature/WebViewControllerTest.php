<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Qredit\LaravelQredit\Controllers\WebViewController;
use Qredit\LaravelQredit\Facades\Qredit;
use Qredit\LaravelQredit\Tenancy\QreditRedirectUrls;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::qreditSign();
    Route::qreditWebview();
});

it('rejects an unsigned request to the webview route', function () {
    $this->get('/qredit/webview/show?reference=PAY-123')
        ->assertForbidden();
});

it('renders the widget host page on a signed request', function () {
    Qredit::rememberRedirectUrls('PAY-WV-1', QreditRedirectUrls::fromArray([
        'success_url' => 'eshophub://done',
        'cancel_url' => 'eshophub://cancel',
    ]));

    $url = URL::temporarySignedRoute('qredit.webview.show', now()->addMinutes(5), [
        'reference' => 'PAY-WV-1',
        'lang' => 'en',
    ]);

    $response = $this->get($url);

    $response->assertOk()
        ->assertSee('qredit-webview-host', false)
        ->assertSee('PAY-WV-1', false)
        ->assertSee('eshophub:\/\/done', false)
        ->assertSee('eshophub:\/\/cancel', false);
});

it('falls back to default return routes when no URLs are stored', function () {
    $url = URL::temporarySignedRoute('qredit.webview.show', now()->addMinutes(5), [
        'reference' => 'PAY-WV-2',
        'lang' => 'en',
    ]);

    $response = $this->get($url);

    $response->assertOk()
        ->assertSee('\/qredit\/mobile\/success', false)
        ->assertSee('\/qredit\/mobile\/cancel', false);
});

it('exposes a helper that returns a signed URL', function () {
    Route::qreditWebview();

    $signed = WebViewController::signedUrlFor('PAY-HELPER', 'ar');

    expect($signed)
        ->toContain('reference=PAY-HELPER')
        ->toContain('lang=ar')
        ->toContain('signature=');
});
