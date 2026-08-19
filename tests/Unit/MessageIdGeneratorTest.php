<?php

use Qredit\LaravelQredit\Helpers\MessageIdGenerator;

describe('MessageIdGenerator', function () {

    it('generates unique message IDs with correct prefix', function () {
        $id1 = MessageIdGenerator::generate('payment.create');
        $id2 = MessageIdGenerator::generate('payment.create');

        expect($id1)->toStartWith('pr_create_')
            ->and($id2)->toStartWith('pr_create_')
            ->and($id1)->not->toBe($id2);
    });

    it('uses correct prefixes for different request types', function () {
        $authId = MessageIdGenerator::generate('auth.token');
        $paymentId = MessageIdGenerator::generate('payment.create');
        $orderId = MessageIdGenerator::generate('order.get');
        $customerId = MessageIdGenerator::generate('customer.create');

        expect($authId)->toStartWith('auth_token_')
            ->and($paymentId)->toStartWith('pr_create_')
            ->and($orderId)->toStartWith('ord_get_')
            ->and($customerId)->toStartWith('cust_create_');
    });

    it('falls back to generic prefix for unknown types', function () {
        $id = MessageIdGenerator::generate('unknown.type');

        expect($id)->toStartWith('req_');
    });

    it('includes context in message ID when provided', function () {
        $context = ['order_id' => '12345'];
        $id = MessageIdGenerator::generate('payment.create', $context);

        expect($id)->toContain('pr_create_')
            ->and($id)->toMatch('/pr_create_.*_\d{10}_[a-f0-9]{8}/');
    });

    it('generates simple message IDs with custom prefix', function () {
        $id = MessageIdGenerator::generateSimple('custom_prefix');

        expect($id)->toStartWith('custom_prefix_')
            ->and($id)->toMatch('/custom_prefix_.*_\d{10}/');
    });

    it('generates idempotent message IDs for same data', function () {
        $data = ['amount' => 100, 'currency' => 'ILS'];

        $id1 = MessageIdGenerator::generateIdempotent('payment.create', $data);
        $id2 = MessageIdGenerator::generateIdempotent('payment.create', $data);

        // The hash part should be the same for same data
        $parts1 = explode('_', $id1);
        $parts2 = explode('_', $id2);

        expect($parts1[0] . '_' . $parts1[1])->toBe('pr_create')
            ->and($parts1[2])->toBe($parts2[2]); // Hash should be same
    });

    it('generates different idempotent IDs for different data', function () {
        $data1 = ['amount' => 100];
        $data2 = ['amount' => 200];

        $id1 = MessageIdGenerator::generateIdempotent('payment.create', $data1);
        $id2 = MessageIdGenerator::generateIdempotent('payment.create', $data2);

        expect($id1)->not->toBe($id2);
    });

    it('generates batch message IDs correctly', function () {
        $batchId = 'batch123';
        $id1 = MessageIdGenerator::generateBatch('payment.create', $batchId, 0);
        $id2 = MessageIdGenerator::generateBatch('payment.create', $batchId, 1);

        expect($id1)->toStartWith('pr_create_batch_batch123_0_')
            ->and($id2)->toStartWith('pr_create_batch_batch123_1_');
    });

    it('validates message IDs correctly', function () {
        $validId = MessageIdGenerator::generate('payment.create');
        $invalidIds = [
            'invalid',
            '12345',
            'pr_',
            'pr_create',
            'pr_create_',
        ];

        expect(MessageIdGenerator::validate($validId))->toBeTrue();

        foreach ($invalidIds as $invalidId) {
            expect(MessageIdGenerator::validate($invalidId))->toBeFalse();
        }
    });

    it('parses message IDs correctly', function () {
        $id = MessageIdGenerator::generate('payment.create');
        $parsed = MessageIdGenerator::parse($id);

        expect($parsed)->toBeArray()
            ->and($parsed)->toHaveKeys(['prefix', 'unique_id', 'timestamp', 'context', 'datetime'])
            ->and($parsed['prefix'])->toContain('pr_create')
            ->and($parsed['timestamp'])->toBeGreaterThan(time() - 10);
    });

    it('returns null for invalid message ID when parsing', function () {
        $parsed = MessageIdGenerator::parse('invalid_id');

        expect($parsed)->toBeNull();
    });

    it('checks if message ID is expired', function () {
        // Generate an ID with a past timestamp
        $oldId = 'pr_create_test_' . (time() - 7200); // 2 hours ago
        $newId = MessageIdGenerator::generate('payment.create');

        expect(MessageIdGenerator::isExpired($oldId, 3600))->toBeTrue()
            ->and(MessageIdGenerator::isExpired($newId, 3600))->toBeFalse();
    });

    it('generates test message IDs', function () {
        $id = MessageIdGenerator::generateTest('payment.create');

        expect($id)->toStartWith('pr_create_test_')
            ->and($id)->toContain('_test_');
    });

    test('message IDs contain timestamp', function () {
        $beforeTime = time();
        $id = MessageIdGenerator::generate('payment.create');
        $afterTime = time();

        $parts = explode('_', $id);
        $timestamp = (int) $parts[count($parts) - 1];

        expect($timestamp)->toBeGreaterThanOrEqual($beforeTime)
            ->and($timestamp)->toBeLessThanOrEqual($afterTime);
    });

    test('different request types have unique prefixes', function () {
        $types = [
            'auth.token',
            'auth.refresh',
            'payment.create',
            'payment.get',
            'order.create',
            'customer.create',
            'webhook.verify',
            'subscription.create',
        ];

        $prefixes = [];
        foreach ($types as $type) {
            $id = MessageIdGenerator::generate($type);
            $prefix = explode('_', $id)[0] . '_' . explode('_', $id)[1];
            $prefixes[] = $prefix;
        }

        // All prefixes should be unique
        expect(array_unique($prefixes))->toHaveCount(count($prefixes));
    });
});

describe('MessageIdGenerator with trait', function () {

    it('integrates with request classes via trait', function () {
        $request = new \Qredit\LaravelQredit\Requests\Auth\GetTokenRequest('test_key');
        $body = $request->body()->all();

        expect($body)->toHaveKey('msgId')
            ->and($body['msgId'])->toStartWith('auth_token_');
    });

    it('integrates with payment request classes', function () {
        $request = new \Qredit\LaravelQredit\Requests\PaymentRequests\CreatePaymentRequest([
            'amount' => 100,
            'clientReference' => 'ORDER-123',
        ]);
        $body = $request->body()->all();

        expect($body)->toHaveKey('msgId')
            ->and($body['msgId'])->toStartWith('pr_create_');
    });
});