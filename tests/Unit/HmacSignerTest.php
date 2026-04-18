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
        // Confirmed against live UAT (auth/token returned a JWT):
        //   secret = 'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496'
        //   msgId  = 'probe-abc123'
        //   keyHex = '06bcaf6c7d423bec7df7b0e32abac714'
        $key = HmacSigner::deriveKey(
            'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496',
            'probe-abc123',
        );

        expect(bin2hex($key))->toBe('06bcaf6c7d423bec7df7b0e32abac714')
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
        $secret = 'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496';
        $msgId = '01062571545OiZoS';

        $signature = HmacSigner::sign($secret, $msgId, ['10', 'ILS']);

        expect($signature)
            ->toBeString()
            ->toHaveLength(128)
            ->toMatch('/^[A-F0-9]{128}$/');
    });

    it('matches the live-UAT output for (secret, msgId=probe-abc123, apiKey)', function () {
        $sig = HmacSigner::sign(
            'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496',
            'probe-abc123',
            ['probe-abc123', 'EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0'],
        );

        expect($sig)->toBe(
            'BDDCA9E14E3BF18F413853BA1A03C2B077977D937AD700A639C3D60E85B50856'
            .'3F045529287F8927069477DB8D94F1A1C9142C9A480AC953ABE0BF3E52965A7D'
        );
    });

    it('matches the live-UAT output for (secret=CF63..., msgId=01062571545OiZoS)', function () {
        $sig = HmacSigner::sign(
            'CF63DBB1ADCEEBD3451985746B7D619998CB8E8AAC00715660D0CC911484B335',
            '01062571545OiZoS',
            ['10', 'ILS', 'NDI=', 'test', 'false', '123456789', 'MjIx', '01062571545OiZoS'],
        );

        expect($sig)->toBe(
            '1C6122C3F47B02C363193091A9C6EE2A2EF54272233E026211016F954F046D36'
            .'1C4A7080679BE5E6BDCC7C3A5D245FEEC48CCCEB14472F4FFB0C4E373CDF05F5'
        );
    });

    it('matches the live-UAT output for a simple base64 secret', function () {
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
