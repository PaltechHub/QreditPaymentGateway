<?php

use Qredit\LaravelQredit\Qredit;
use Qredit\LaravelQredit\Connectors\QreditConnector;
use Qredit\LaravelQredit\Exceptions\QreditAuthenticationException;
use Qredit\LaravelQredit\Exceptions\QreditException;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
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
});

describe('Authentication', function () {

    it('can authenticate with valid API key', function () {
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

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test_api_key_123', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        // Act
        $token = $qredit->authenticate();

        // Assert
        expect($token)->toBe($expectedToken);
        expect(Cache::get('qredit_auth_token'))->toBe($expectedToken);
    });

    it('throws exception with invalid API key', function () {
        // Arrange
        $this->mockClient->addResponse(
            MockResponse::make([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], 401)
        );

        $connector = new QreditConnector('invalid_key', true);
        $connector->withMockClient($this->mockClient);

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['invalid_key', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        // Act & Assert
        $qredit->authenticate(force: true);
    })->throws(QreditAuthenticationException::class, 'Failed to authenticate with Qredit API');

    it('uses cached token when available', function () {
        // Arrange
        $cachedToken = 'cached_token_789';
        Cache::put('qredit_auth_token', $cachedToken, 3600);

        $connector = new QreditConnector('test_api_key_123', true);

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test_api_key_123', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        // Act
        $token = $qredit->authenticate();

        // Assert
        expect($token)->toBe($cachedToken);
    });

    it('forces authentication and ignores cache', function () {
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

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test_api_key_123', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        // Act
        $token = $qredit->authenticate(force: true);

        // Assert
        expect($token)->toBe($newToken);
        expect(Cache::get('qredit_auth_token'))->toBe($newToken);
    });

    it('throws exception when no token in response', function () {
        // Arrange
        $this->mockClient->addResponse(
            MockResponse::make(['success' => true], 200)
        );

        $connector = new QreditConnector('test_api_key_123', true);
        $connector->withMockClient($this->mockClient);

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test_api_key_123', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        // Act & Assert
        $qredit->authenticate(force: true);
    })->throws(QreditAuthenticationException::class, 'No token received from Qredit API');

    it('throws exception without API key', function () {
        // Arrange
        config(['qredit.api_key' => null]);

        // Act & Assert
        new Qredit();
    })->throws(QreditException::class, 'Qredit API key is not configured');

    it('correctly identifies sandbox mode', function () {
        // Test sandbox mode
        $qredit = new Qredit('test_key', true, true);
        expect($qredit->isSandbox())->toBeTrue();

        // Test production mode
        $qredit = new Qredit('test_key', false, true);
        expect($qredit->isSandbox())->toBeFalse();
    });

    it('handles different token field names in response', function () {
        // Test with 'access_token' field
        $expectedToken = 'access_token_value';

        $this->mockClient->addResponse(
            MockResponse::make([
                'access_token' => $expectedToken,
                'expires_in' => 7200,
            ], 200)
        );

        $connector = new QreditConnector('test_api_key_123', true);
        $connector->withMockClient($this->mockClient);

        $qredit = \Mockery::mock(Qredit::class . '[getConnector]', ['test_api_key_123', true])
            ->shouldAllowMockingProtectedMethods();
        $qredit->shouldReceive('getConnector')->andReturn($connector);

        // Act
        $token = $qredit->authenticate(force: true);

        // Assert
        expect($token)->toBe($expectedToken);
    });
});