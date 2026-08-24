<?php

use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;

/*
 * Regression guard for the inbound-webhook signature.
 *
 * The gateway (Java) signs over the JSON number *literals* it puts on the wire:
 * `"amount":10.0` is signed as "10.0". PHP's json_decode turns that into
 * float(10), whose string cast is "10" — so verifying against the decoded array
 * silently failed on every real callback carrying a decimal amount.
 *
 * The vector below is synthetic — same envelope shape and same float literals as
 * a production callback, with fabricated customer fields. The expected signature
 * was produced with the algorithm confirmed byte-for-byte against a real gateway
 * callback (see docs/SIGNING.md); real callback bodies are never committed
 * because they carry live customer data.
 */

const WEBHOOK_SECRET = '00112233445566778899AABBCCDDEEFF00112233445566778899AABBCCDDEEFF';

const WEBHOOK_MSG_ID = 'sproxy_5555555555555555555555';

const WEBHOOK_SIGNATURE = 'E78D0B980B20AA98492AC6FA2246DF4EEB94485AF4CACFABD0C7337049A16F543C827D6574964D56404EF4493F65236DC1F6FCD3B5FB1B7ABC556577FB31D6C5';

function webhookBody(): string
{
    return '{"amount":10.0,"currency":"ILS","isReversed":false,"notes":"ONLINE_CARD_PURCHASE","operation":"ONLINE_CARD_PURCHASE","paymentRequest":{"amount":10.0,"amountCents":1000,"currency":"ILS","customer":{"email":"customer@example.com","encodedId":"CUSTOMER_ENCODED_ID","idNumber":"000000000","name":"Test Customer","ordersCount":1,"phone":"+970000000000"},"encodedId":null,"orderReference":"1111111111","paymentRequestStatus":"PAYMENT_IN_PROGRESS","reference":"22222222","statusReason":null,"subCorporate":{"encodedId":"SUBCORP_ENCODED_ID","city":"Ramallah","localName":"شركة","latinName":"TESTCO","ownerCorporateName":"TESTCO","type":"HEAD_QUARTER","macCode":"0000"},"url":"https://portaltest.qredit.tech/#/pay/TESTTOKEN"},"productCode":"CSAB","providerReference":"3333333333333333333333","receiver":{"localName":"شركة","latinName":"TESTCO"},"receiverAccount":{"encodedId":"SUBCORP_ENCODED_ID","accountNumber":"MRC/000000/000","currencyCode":"ILS"},"receiverCharges":1.0,"reference":"4444444444444444","sender":{"localName":"Test Bank","latinName":"Test Bank"},"transDateTimeText":"21/08/2026 16:52:58","transactionStatus":"SUCCESS","msgId":"'.WEBHOOK_MSG_ID.'"}';
}

function webhookClient(): Qredit
{
    return Qredit::make([
        'api_key' => 'k',
        'secret_key' => WEBHOOK_SECRET,
        'client_version' => 'ccc1.0',
        'sandbox' => true,
        'skip_auth' => true,
    ]);
}

describe('ValueFlattener — raw JSON numbers', function () {

    it('keeps number literals exactly as they appear on the wire', function () {
        $values = ValueFlattener::flattenRawJson('{"a":10.0,"b":1000,"c":100.00,"d":-2.5}');

        expect($values)->toBe(['10.0', '1000', '100.00', '-2.5']);
    });

    it('does not touch numbers that live inside strings', function () {
        $values = ValueFlattener::flattenRawJson('{"a":"see 1.5 below","b":"x:2.5","c":1.5}');

        expect($values)->toBe(['see 1.5 below', 'x:2.5', '1.5']);
    });

    it('survives escaped quotes and backslashes in strings', function () {
        $values = ValueFlattener::flattenRawJson('{"a":"he said \"3.0\"","b":"back\\\\","c":3.0}');

        expect($values)->toBe(['he said "3.0"', 'back\\', '3.0']);
    });

    it('still drops nulls and empty strings', function () {
        $values = ValueFlattener::flattenRawJson('{"a":null,"b":"","c":1.0,"d":false}');

        expect($values)->toBe(['1.0', false]);
    });

    it('returns null for a body that is not JSON', function () {
        expect(ValueFlattener::flattenRawJson('not json'))->toBeNull();
    });
});

describe('Qredit — inbound webhook verification', function () {

    it('verifies a gateway callback carrying decimal amounts', function () {
        $verified = webhookClient()->verifyWebhookSignature(
            json_decode(webhookBody(), true),
            'HmacSHA512_O '.WEBHOOK_SIGNATURE,
            webhookBody(),
        );

        expect($verified)->toBeTrue();
    });

    it('accepts a lowercase signature too', function () {
        $verified = webhookClient()->verifyWebhookSignature(
            json_decode(webhookBody(), true),
            'HmacSHA512_O '.strtolower(WEBHOOK_SIGNATURE),
            webhookBody(),
        );

        expect($verified)->toBeTrue();
    });

    it('rejects the callback when the body has been tampered with', function () {
        $tampered = str_replace('"amount":10.0', '"amount":9999.0', webhookBody());

        $verified = webhookClient()->verifyWebhookSignature(
            json_decode($tampered, true),
            'HmacSHA512_O '.WEBHOOK_SIGNATURE,
            $tampered,
        );

        expect($verified)->toBeFalse();
    });

    it('is the raw body that makes it verify — the decoded array alone cannot', function () {
        // json_decode has already collapsed 10.0 -> "10" by this point.
        $viaArray = HmacSigner::sign(
            WEBHOOK_SECRET,
            WEBHOOK_MSG_ID,
            ValueFlattener::flatten(json_decode(webhookBody(), true)),
        );

        expect($viaArray)->not->toBe(WEBHOOK_SIGNATURE);
    });

    it('processWebhook passes the raw body through to verification', function () {
        config(['qredit.verify_webhook_signature' => true]);

        $processed = webhookClient()->processWebhook(
            json_decode(webhookBody(), true),
            'HmacSHA512_O '.WEBHOOK_SIGNATURE,
            webhookBody(),
        );

        expect($processed['event'])->toBe('payment.completed');
    });
});
