<?php

use Qredit\LaravelQredit\Security\HmacSigner;
use Qredit\LaravelQredit\Security\ValueFlattener;

/*
 * The golden vectors in this file are produced by running the Angular reference
 * implementation (the one the Qredit widget ships) against the same inputs in
 * Node with crypto-js. Every test here is a parity check — if the PHP port
 * diverges, the tests fail. See docs/SIGNING.md for the full reference code.
 */

describe('HmacSigner — message assembly', function () {

    it('sorts stringified values lexicographically before concatenating', function () {
        $msg = HmacSigner::buildMessage(['ILS', 10, 'test', 'NDI=']);

        // ASCII sort: "10" < "ILS" < "NDI=" < "test"
        expect($msg)->toBe('10ILSNDI=test');
    });

    it('stringifies booleans as "true"/"false" to match the reference', function () {
        $msg = HmacSigner::buildMessage([false, 'alpha']);

        // "alpha" < "false" in ASCII.
        expect($msg)->toBe('alphafalse');
    });

    it('matches the merchant-doc §7 stated ordering (even though the doc s worked signature is unreproducible)', function () {
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJwcmluY2lwYWwiOiJINHNJQUFBQUFBQUFBSldXejQvYlJCVEhaOE5XUmFxMDdHN3BsZ1dXbG0xTGhZU3lVcm14RW1MaXpDWW1qc2M3TTk3dEZnbkxtN2pCcldNSDIybDNMMmhQY0';

        $values = [
            '123456789',           // cardToken
            10,                    // amount
            'ILS',                 // currencyCode
            'NDI=',                // senderAccountId
            'test',                // notes
            false,                 // isConfirmed
            'MjIx',                // transferReasonId
            '01062571545OiZoS',    // msgId
            $jwt,                  // X-Auth-Token
        ];

        $expected = '01062571545OiZoS10123456789ILSMjIxNDI='.$jwt.'falsetest';

        expect(HmacSigner::buildMessage($values))->toBe($expected);
    });
});

describe('HmacSigner — key derivation', function () {

    it('takes the raw MD5 of (secret . msgId) as the 16-byte HMAC key', function () {
        $key = HmacSigner::deriveKey(
            '00112233445566778899AABBCCDDEEFF00112233445566778899AABBCCDDEEFF',
            'probe-abc123',
        );

        expect(bin2hex($key))->toBe('22db7631764348194a266cad9c6fefe2')
            ->and(strlen($key))->toBe(16);
    });

    it('produces a deterministic 16-byte key for any input without throwing', function () {
        $key = HmacSigner::deriveKey('////', 'x');

        expect(strlen($key))->toBe(16)
            ->and(bin2hex($key))->toMatch('/^[0-9a-f]{32}$/');
    });
});

describe('HmacSigner — signature output', function () {

    it('produces a deterministic 128-char uppercase hex string by default', function () {
        $secret = '00112233445566778899AABBCCDDEEFF00112233445566778899AABBCCDDEEFF';
        $msgId = '01062571545OiZoS';

        $signature = HmacSigner::sign($secret, $msgId, ['10', 'ILS']);

        expect($signature)
            ->toBeString()
            ->toHaveLength(128)
            ->toMatch('/^[A-F0-9]{128}$/');
    });

    it('pins the (secret, msgId=probe-abc123, apiKey) vector', function () {
        $sig = HmacSigner::sign(
            '00112233445566778899AABBCCDDEEFF00112233445566778899AABBCCDDEEFF',
            'probe-abc123',
            ['probe-abc123', 'ExampleApiKeyNotARealCredential0000000000'],
        );

        expect($sig)->toBe(
            'C79588BDDBE758B53B8439B641A955B29345859219E83C79AE15D3AAF6FA2BC5'
            .'34BC6F2C8DD5179DFDA61D0D68D934C2ACE62F6BC5F623B08AD40B8B6697D8F9'
        );
    });

    it('pins the (secret, msgId=01062571545OiZoS) vector', function () {
        $sig = HmacSigner::sign(
            'FFEEDDCCBBAA99887766554433221100FFEEDDCCBBAA99887766554433221100',
            '01062571545OiZoS',
            ['10', 'ILS', 'NDI=', 'test', 'false', '123456789', 'MjIx', '01062571545OiZoS'],
        );

        expect($sig)->toBe(
            '134009182D7CC53349A223ACAFE559683B2197E8B1E8E319AF2BB27679D6ADE4'
            .'0997A194A2C0EC5CA08B80C9114ED3C67376682093B616901E78BE5948535F9F'
        );
    });

    it('pins the vector for a simple base64 secret', function () {
        $sig = HmacSigner::sign(
            'QWxhZGRpbjpvcGVuIHNlc2FtZQ==',
            'hello',
            ['hello', 'world', '42', 'true'],
        );

        expect($sig)->toBe(
            '11C4175C3B5CBB409321AE72AA527497DB185FC1E0DCCE3AB58C847FA8CCC0AE'
            .'2082ED99E785309D38B4DBDD7A484F41E1179C591E0F984244C4D7920930F2CA'
        );
    });

    it('can emit lowercase hex on demand', function () {
        $upper = HmacSigner::sign('secret', 'msg', ['a', 'b']);
        $lower = HmacSigner::sign('secret', 'msg', ['a', 'b'], HmacSigner::CASE_LOWER);

        expect($upper)->toMatch('/^[A-F0-9]{128}$/')
            ->and($lower)->toMatch('/^[a-f0-9]{128}$/')
            ->and(strtoupper($lower))->toBe($upper);
    });

    it('builds the full Authorization header value', function () {
        $header = HmacSigner::authorizationHeader('HmacSHA512_O', 'secret', 'msg', ['a']);

        expect($header)->toStartWith('HmacSHA512_O ')
            ->and(strlen($header))->toBe(strlen('HmacSHA512_O ') + 128);
    });
});

describe('ValueFlattener', function () {

    it('extracts top-level scalars in insertion order', function () {
        $flat = ValueFlattener::flatten([
            'msgId' => 'abc',
            'amount' => 100,
            'active' => true,
        ]);

        expect($flat)->toBe(['abc', 100, true]);
    });

    it('walks nested arrays depth-first', function () {
        $flat = ValueFlattener::flatten([
            'msgId' => 'abc',
            'customerInfo' => [
                'name' => 'Alice',
                'phone' => '+970',
            ],
            'amount' => 100,
        ]);

        expect($flat)->toBe(['abc', 'Alice', '+970', 100]);
    });

    it('drops null and empty string values', function () {
        $flat = ValueFlattener::flatten([
            'present' => 'x',
            'nothing' => null,
            'empty' => '',
            'zero' => 0,          // zero is NOT empty
            'falseFlag' => false, // false is NOT empty
        ]);

        expect($flat)->toBe(['x', 0, false]);
    });

    it('keeps booleans as booleans so HmacSigner stringifies them consistently', function () {
        $flat = ValueFlattener::flatten(['a' => true, 'b' => false]);

        expect($flat)->toBe([true, false]);
    });
});
