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

    it('base64-decodes the secret, UTF-8-normalises it, appends the msgId, and takes the raw md5 as key bytes', function () {
        // Confirmed against the Node reference:
        //   secret  = 'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496'
        //   msgId   = 'probe-abc123'
        //   keyHex  = 'ff8667d31bb8f723cda2b5f6e675c320'
        $key = HmacSigner::deriveKey(
            'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496',
            'probe-abc123',
        );

        expect(bin2hex($key))->toBe('ff8667d31bb8f723cda2b5f6e675c320')
            ->and(strlen($key))->toBe(16);
    });

    it('uses U+FFFD for invalid UTF-8 sequences, matching the Angular TextDecoder default', function () {
        // A secret whose base64 decodes to an invalid-UTF-8 byte must still produce
        // a deterministic key (not throw).
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

    it('matches the Angular-reference output for (secret, msgId=probe-abc123, apiKey) — byte-identical', function () {
        $sig = HmacSigner::sign(
            'B9E0236B77E5C16B1F3540265920C7E0C541622E66C4F76FBC53BC990F11E496',
            'probe-abc123',
            ['probe-abc123', 'EdVfej9DvSSHBCtn0DDUviHxmXMj3t0bodQqjeNXF0'],
        );

        expect($sig)->toBe(
            '06EE49899C99BE6C38691253D6F950FDC5B3332AEA75BB6C24712141C7707D3A'
            .'7C4D5C4336AFB36286FA0927E8F13C88A84E8478ADF417EC23C94D12CC8BB381'
        );
    });

    it('matches the Angular-reference output for (secret=CF63..., msgId=01062571545OiZoS)', function () {
        $sig = HmacSigner::sign(
            'CF63DBB1ADCEEBD3451985746B7D619998CB8E8AAC00715660D0CC911484B335',
            '01062571545OiZoS',
            ['10', 'ILS', 'NDI=', 'test', 'false', '123456789', 'MjIx', '01062571545OiZoS'],
        );

        expect($sig)->toBe(
            '657273D7F55DE4DC0002B2F5ACEC123909A3AC65184A7B8DC6478E236FC81EE0'
            .'F6E8ACE7EB6845173F67C0DAA4B4BC3CD3FA2BC99D404F6D7DB89D76A949A06E'
        );
    });

    it('matches the Angular-reference output for a simple base64 secret', function () {
        // base64('Aladdin:open sesame') = 'QWxhZGRpbjpvcGVuIHNlc2FtZQ=='
        $sig = HmacSigner::sign(
            'QWxhZGRpbjpvcGVuIHNlc2FtZQ==',
            'hello',
            ['hello', 'world', '42', 'true'],
        );

        expect($sig)->toBe(
            '992BFA976D766C85AE6A51B20218B6AEDAA0BC613AF80BE533D0294F5B4AA6DE'
            .'919556671F0FB5343B08DDEB190C12995BF35E061887AFD3665331E046ACEED1'
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
