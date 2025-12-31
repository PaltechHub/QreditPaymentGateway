<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Tests\Unit;

use Qredit\LaravelQredit\Tests\TestCase;
use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

class QreditTest extends TestCase
{
    protected Qredit $qredit;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_be_instantiated_with_api_key()
    {
        $qredit = new Qredit('test-api-key', true);

        $this->assertInstanceOf(Qredit::class, $qredit);
        $this->assertTrue($qredit->isSandbox());
    }

    /** @test */
    public function it_throws_exception_when_api_key_is_missing()
    {
        config(['qredit.api_key' => null]);

        $this->expectException(QreditException::class);
        $this->expectExceptionMessage('Qredit API key is not configured');

        new Qredit();
    }

    /** @test */
    public function it_can_authenticate_successfully()
    {
        $mockClient = $this->mockSuccessfulAuth();

        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['test-api-key', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $connector = new QreditConnector('test-api-key', true);
        $connector->withMockClient($mockClient);

        $qredit->method('getConnector')->willReturn($connector);

        $token = $qredit->authenticate(true);

        $this->assertEquals('test-token-12345', $token);
    }

    /** @test */
    public function it_verifies_webhook_signature_correctly()
    {
        $qredit = new Qredit('test-api-key', true);

        $payload = json_encode(['test' => 'data']);
        $secret = 'test-secret';
        config(['qredit.webhook_secret' => $secret]);

        $validSignature = hash_hmac('sha512', $payload, $secret);

        $this->assertTrue($qredit->verifyWebhookSignature($payload, $validSignature));
        $this->assertFalse($qredit->verifyWebhookSignature($payload, 'invalid-signature'));
    }

    /** @test */
    public function it_processes_webhook_payload_correctly()
    {
        $qredit = new Qredit('test-api-key', true);

        $payload = [
            'event' => 'payment.completed',
            'data' => [
                'id' => 'pay_123',
                'amount' => 1000,
            ],
        ];

        config(['qredit.verify_webhook_signature' => false]);

        $processed = $qredit->processWebhook($payload);

        $this->assertEquals('payment.completed', $processed['event']);
        $this->assertEquals(['id' => 'pay_123', 'amount' => 1000], $processed['data']);
        $this->assertArrayHasKey('processed_at', $processed);
    }

    /** @test */
    public function it_throws_exception_for_invalid_webhook_signature()
    {
        $qredit = new Qredit('test-api-key', true);

        $payload = ['test' => 'data'];
        config(['qredit.verify_webhook_signature' => true]);
        config(['qredit.webhook_secret' => 'secret']);

        $this->expectException(QreditException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $qredit->processWebhook($payload, 'invalid-signature');
    }

    /** @test */
    public function it_returns_correct_api_url_based_on_environment()
    {
        $sandboxQredit = new Qredit('test-api-key', true);
        $this->assertStringContainsString('185.57.122.58:2030', $sandboxQredit->getApiUrl());

        config(['qredit.production_url' => 'https://api.qredit.com/v1']);
        $productionQredit = new Qredit('test-api-key', false);
        $this->assertEquals('https://api.qredit.com/v1', $productionQredit->getApiUrl());
    }
}