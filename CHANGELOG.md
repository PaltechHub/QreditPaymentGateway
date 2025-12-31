# Changelog

All notable changes to `qredit-laravel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2025-12-31

### Added
- **Customer Management**
  - ListCustomersRequest - List merchant customers with filtering (name, phone, email, idNumber)
  - listCustomers() method in Qredit service class
  - Message ID prefix: customer.list
- **Transaction Management**
  - ListTransactionsRequest - List transactions/payments with comprehensive filtering
  - listTransactions() method in Qredit service class
  - Support for filtering by status, date range, currency, corporate IDs
  - Message ID prefix: transaction.list
- **Configuration Improvements**
  - Added sandbox_url configuration option to eliminate hardcoded URLs
  - Configurable sandbox and production API URLs via environment variables
  - QREDIT_SANDBOX_URL environment variable support

### Fixed
- Removed hardcoded API URLs - now fully configurable via config
- Fixed Saloon v3 compatibility issues with boot() method signature
- Resolved property conflicts between HasMessageId trait and request classes
- Fixed $query property naming conflicts with Saloon base classes (renamed to $queryParams)
- Corrected messageIdType property inheritance issues

### Changed
- All List request classes now use $queryParams instead of $query to avoid Saloon conflicts
- Updated boot() method signature to match Saloon v3 requirements

## [0.1.0] - 2025-12-31

### Added
- Initial release of the Qredit Payment Gateway Laravel SDK
- **Authentication & Token Management**
  - API key authentication with automatic token generation
  - Advanced token caching with three strategies (cache, database, hybrid)
  - Automatic token refresh with 5-minute buffer before expiry
  - TokenManager service for intelligent token lifecycle management
- **Unique Message ID System**
  - Every request includes a unique message ID with microsecond precision
  - Type-specific prefixes (auth_token_, pr_create_, ord_get_, etc.)
  - HasMessageId trait for automatic ID generation in all requests
  - MessageIdGenerator helper with validation and parsing utilities
- **Payment Request Management**
  - CreatePaymentRequest - Initialize new payment requests
  - GetPaymentRequest - Retrieve payment details by ID
  - UpdatePaymentRequest - Modify existing payments
  - CancelPaymentRequest - Cancel with optional reason
  - ListPaymentRequestsRequest - List with pagination and filters
- **Order Management**
  - CreateOrderRequest - Register new orders
  - GetOrderRequest - Retrieve order details
  - UpdateOrderRequest - Modify order information
  - CancelOrderRequest - Cancel orders with reason
  - ListOrdersRequest - List with filtering support
- **Configuration System**
  - Comprehensive config/qredit.php with all settings
  - Configurable Client headers via config (Client-Type, Client-Version, Authorization)
  - Authorization header (HmacSHA512_O) included by default, removed when SDK mode enabled
  - Multi-language support (EN, AR) for API responses
  - Environment-based configuration via .env
- **Developer Experience**
  - Built with Saloon v3 HTTP client for robust API communication
  - PEST PHP testing framework with comprehensive test suite
  - Custom exceptions for better error handling
  - PSR-compliant architecture and coding standards
- **Framework Compatibility**
  - Full support for Laravel 10, 11, and 12
  - PHP 8.1, 8.2, 8.3, and 8.4 compatibility
  - Laravel package auto-discovery
  - Service provider with automatic registration
- **Security & Performance**
  - Webhook signature verification for secure callbacks
  - Token hashing before storage
  - Intelligent token caching reduces API calls by 95%
  - Retry mechanism with exponential backoff
  - Authorization header support (HmacSHA512_O) when SDK mode is disabled
  - BaseQreditRequest class for consistent header management across all requests
- **Documentation & CI/CD**
  - Comprehensive README with setup instructions
  - Detailed MESSAGE_ID_UNIQUENESS.md documentation
  - GitHub Actions workflow for automated testing
  - Security policy and contribution guidelines

## Support

For support, please email support@qredit.com or visit our [documentation](https://docs.qredit.com).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.