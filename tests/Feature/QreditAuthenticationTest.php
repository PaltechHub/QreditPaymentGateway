<?php

namespace Qredit\LaravelQredit\Tests\Feature;

use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

class QreditAuthenticationTest extends TestCase
{
    protected Qredit $qredit;
    protected MockClient $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Set up configuration
        config([
            'qredit.api_key' => 'test_api_key_123',
            'qredit.sandbox' => true,
            'qredit.cache_token' => true,
        ]);

        // Create mock client
        $this->mockClient = new MockClient();
    }

    protected function getPackageProviders($app)
    {
        return ['Qredit\LaravelQredit\QreditServiceProvider'];
    }

    public function test_can_authenticate_with_valid_api_key()
    {
        // Arrange
        $expectedToken = 'valid_token_123456';

        $this->mockClient->addResponse(
            MockResponse::make([
                'token' => $expectedToken,
                'expires_in' => 3600,
            ], 200)
        );

        $connector = new QreditConnector('test_api_key_123', true);
        $connector->withMockClient($this->mockClient);

        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['test_api_key_123', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $qredit->method('getConnector')->willReturn($connector);

        // Act
        $token = $qredit->authenticate();

        // Assert
        $this->assertEquals($expectedToken, $token);
        $this->assertEquals($expectedToken, Cache::get('qredit_auth_token'));
    }

    public function test_throws_exception_with_invalid_api_key()
    {
        // Arrange
        $this->mockClient->addResponse(
            MockResponse::make([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], 401)
        );

        $connector = new QreditConnector('invalid_key', true);
        $connector->withMockClient($this->mockClient);

        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['invalid_key', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $qredit->method('getConnector')->willReturn($connector);

        // Act & Assert
        $this->expectException(QreditAuthenticationException::class);
        $this->expectExceptionMessage('Failed to authenticate with Qredit API');

        $qredit->authenticate(force: true);
    }

    public function test_uses_cached_token_when_available()
    {
        // Arrange
        $cachedToken = 'cached_token_789';
        Cache::put('qredit_auth_token', $cachedToken, 3600);

        $connector = new QreditConnector('test_api_key_123', true);

        // No mock response needed as it should use cache
        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['test_api_key_123', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $qredit->method('getConnector')->willReturn($connector);

        // Act
        $token = $qredit->authenticate();

        // Assert
        $this->assertEquals($cachedToken, $token);
    }

    public function test_force_authentication_ignores_cache()
    {
        // Arrange
        $cachedToken = 'old_cached_token';
        $newToken = 'new_fresh_token';

        Cache::put('qredit_auth_token', $cachedToken, 3600);

        $this->mockClient->addResponse(
            MockResponse::make([
                'token' => $newToken,
                'expires_in' => 3600,
            ], 200)
        );

        $connector = new QreditConnector('test_api_key_123', true);
        $connector->withMockClient($this->mockClient);

        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['test_api_key_123', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $qredit->method('getConnector')->willReturn($connector);

        // Act
        $token = $qredit->authenticate(force: true);

        // Assert
        $this->assertEquals($newToken, $token);
        $this->assertEquals($newToken, Cache::get('qredit_auth_token'));
    }

    public function test_throws_exception_when_no_token_in_response()
    {
        // Arrange
        $this->mockClient->addResponse(
            MockResponse::make([
                'success' => true,
                // No token field
            ], 200)
        );

        $connector = new QreditConnector('test_api_key_123', true);
        $connector->withMockClient($this->mockClient);

        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['test_api_key_123', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $qredit->method('getConnector')->willReturn($connector);

        // Act & Assert
        $this->expectException(QreditAuthenticationException::class);
        $this->expectExceptionMessage('No token received from Qredit API');

        $qredit->authenticate(force: true);
    }

    public function test_constructor_throws_exception_without_api_key()
    {
        // Arrange
        config(['qredit.api_key' => null]);

        // Act & Assert
        $this->expectException(QreditException::class);
        $this->expectExceptionMessage('Qredit API key is not configured');

        new Qredit();
    }

    public function test_can_check_sandbox_mode()
    {
        // Arrange
        $qredit = new Qredit('test_key', true);

        // Act & Assert
        $this->assertTrue($qredit->isSandbox());

        $qredit = new Qredit('test_key', false);
        $this->assertFalse($qredit->isSandbox());
    }

    public function test_authentication_with_different_token_formats()
    {
        // Test with 'access_token' field instead of 'token'
        $expectedToken = 'access_token_value';

        $this->mockClient->addResponse(
            MockResponse::make([
                'access_token' => $expectedToken,
                'expires_in' => 7200,
            ], 200)
        );

        $connector = new QreditConnector('test_api_key_123', true);
        $connector->withMockClient($this->mockClient);

        $qredit = $this->getMockBuilder(Qredit::class)
            ->setConstructorArgs(['test_api_key_123', true])
            ->onlyMethods(['getConnector'])
            ->getMock();

        $qredit->method('getConnector')->willReturn($connector);

        // Act
        $token = $qredit->authenticate(force: true);

        // Assert
        $this->assertEquals($expectedToken, $token);
    }
}