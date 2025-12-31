# Changelog

All notable changes to `qredit-laravel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-12-31

### Added
- Initial release of the Qredit Payment Gateway Laravel SDK
- Authentication with API key and automatic token management
- Payment request management (create, update, list, delete)
- Order management (create, update, cancel, list)
- Customer management endpoints
- Payment processing endpoints
- Webhook handling with signature verification
- Comprehensive error handling with custom exceptions
- Retry mechanism with exponential backoff
- Support for test and production environments
- Automatic token caching to reduce API calls
- PSR-compliant logging
- Laravel Horizon compatibility
- Full Saloon PHP integration for HTTP client
- Comprehensive test suite with PHPUnit
- GitHub Actions CI/CD pipeline
- Detailed documentation and examples
- Support for Laravel 10 and 11
- Support for PHP 8.1, 8.2, and 8.3

### Security
- Webhook signature verification
- Secure token storage in cache
- API key protection

## [0.9.0-beta] - 2024-12-30

### Added
- Beta release for testing
- Basic authentication functionality
- Payment request creation
- Initial test suite

### Changed
- Refactored connector to use Saloon v3

### Fixed
- Token caching issues
- Request timeout handling

## [0.5.0-alpha] - 2024-12-29

### Added
- Alpha release with basic functionality
- Authentication endpoint
- Basic payment request creation

### Known Issues
- Webhook handling not fully implemented
- Limited test coverage

---

## Upgrade Guide

### From 0.9.x to 1.0.0

1. Update your composer.json:
```json
"require": {
    "qredit/laravel-qredit": "^1.0"
}
```

2. Run composer update:
```bash
composer update qredit/laravel-qredit
```

3. Publish the new configuration:
```bash
php artisan vendor:publish --provider="Qredit\LaravelQredit\QreditServiceProvider" --tag="config" --force
```

4. Update your .env file with new configuration options:
```env
QREDIT_CLIENT_TYPE=MP
QREDIT_CLIENT_VERSION=1.0.0
QREDIT_WEBHOOK_ENABLED=true
```

5. Clear your application cache:
```bash
php artisan cache:clear
```

### Breaking Changes in 1.0.0

- The `payment-requests` endpoint has been renamed to `paymentRequests`
- All request methods now require a `msgId` field
- The authentication response format has changed from `auth_token` to `token`
- Webhook signature verification is now enabled by default

## Support

For support, please email support@qredit.com or visit our [documentation](https://docs.qredit.com).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.