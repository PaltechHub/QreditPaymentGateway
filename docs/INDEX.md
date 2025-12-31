# Qredit Laravel SDK Documentation

Welcome to the comprehensive documentation for the Qredit Laravel SDK v0.1.1.

## Documentation Structure

### For Developers

#### 1. [API Reference](API_REFERENCE.md)
Complete API documentation with examples for every method, error handling, webhooks, and advanced usage patterns.

**What's inside:**
- Full method signatures and parameters
- Real-world code examples
- Response formats
- Error handling patterns
- Testing strategies
- Advanced usage scenarios

#### 2. [README](../README.md)
Quick start guide and basic usage examples.

**What's inside:**
- Installation instructions
- Basic configuration
- Simple usage examples
- Requirements and compatibility

#### 3. [CHANGELOG](../CHANGELOG.md)
Version history and release notes.

**What's inside:**
- New features per version
- Bug fixes
- Breaking changes
- Migration guides

### For AI/LLM Assistants

#### [LLM Implementation Guide](LLM_IMPLEMENTATION_GUIDE.md)
Structured guide for AI assistants to understand and work with the SDK.

**What's inside:**
- Architecture overview
- Implementation patterns
- Code templates
- Best practices
- Common tasks and solutions

### Technical Deep Dives

#### 1. [Message ID Uniqueness](MESSAGE_ID_UNIQUENESS.md)
Technical analysis of the message ID generation system.

**What's inside:**
- Uniqueness algorithm
- Collision probability analysis
- Performance metrics
- Implementation details

#### 2. [Release Notes v0.1.1](../RELEASE_NOTES_v0.1.1.md)
Detailed notes for the latest release.

**What's inside:**
- New features in v0.1.1
- Bug fixes
- Migration guide
- Testing results

#### 3. [Test Results v0.1.1](../TEST_RESULTS_v0.1.1.md)
Complete test coverage report for v0.1.1.

**What's inside:**
- Test coverage metrics
- Feature test results
- Known issues
- Running tests

## Quick Navigation

### By Use Case

#### "I want to integrate Qredit payments into my Laravel app"
→ Start with [API Reference](API_REFERENCE.md)

#### "I need to understand the codebase structure"
→ Read [LLM Implementation Guide](LLM_IMPLEMENTATION_GUIDE.md)

#### "I want to see what's new"
→ Check [CHANGELOG](../CHANGELOG.md)

#### "I'm an AI assistant helping with this SDK"
→ Use [LLM Implementation Guide](LLM_IMPLEMENTATION_GUIDE.md)

#### "I need to debug an issue"
→ See Error Handling in [API Reference](API_REFERENCE.md#error-handling)

### By Feature

| Feature | Documentation |
|---------|--------------|
| Payment Processing | [Payment Requests](API_REFERENCE.md#payment-requests) |
| Order Management | [Orders](API_REFERENCE.md#orders) |
| Customer Management | [Customers](API_REFERENCE.md#customers) |
| Transaction History | [Transactions](API_REFERENCE.md#transactions) |
| Webhook Handling | [Webhooks](API_REFERENCE.md#webhooks) |
| Error Handling | [Error Handling](API_REFERENCE.md#error-handling) |
| Testing | [Testing](API_REFERENCE.md#testing) |
| Configuration | [Configuration](API_REFERENCE.md#configuration) |

## Getting Started

### For New Users

1. **Install the package**
   ```bash
   composer require qredit/laravel-qredit
   ```

2. **Configure your environment**
   - Copy environment variables from [Configuration](API_REFERENCE.md#configuration)
   - Add your API key to `.env`

3. **Basic Usage**
   ```php
   use Qredit\LaravelQredit\Facades\Qredit;

   $payment = Qredit::createPayment([
       'amount' => 100.00,
       'currencyCode' => 'ILS',
       'description' => 'Test payment',
       // ... see API Reference for full options
   ]);
   ```

4. **Learn More**
   - [Full API Reference](API_REFERENCE.md)
   - [Code Examples](API_REFERENCE.md#payment-requests)
   - [Error Handling](API_REFERENCE.md#error-handling)

### For Existing Users Upgrading to v0.1.1

1. **Review Breaking Changes**
   - Check [CHANGELOG](../CHANGELOG.md) for any breaking changes
   - No breaking changes in v0.1.1

2. **New Features**
   - [List Customers](API_REFERENCE.md#list-customers)
   - [List Transactions](API_REFERENCE.md#list-transactions)
   - Configurable sandbox URL

3. **Bug Fixes**
   - Saloon v3 compatibility
   - Property conflicts resolved
   - See [Release Notes](../RELEASE_NOTES_v0.1.1.md)

## Support

### Resources

- **GitHub Repository**: [github.com/PaltechHub/qredit-laravel](https://github.com/PaltechHub/qredit-laravel)
- **Issues**: [github.com/PaltechHub/qredit-laravel/issues](https://github.com/PaltechHub/qredit-laravel/issues)
- **Email Support**: support@qredit.com
- **API Status**: [status.qredit.com](https://status.qredit.com)

### Common Issues

| Issue | Solution | Documentation |
|-------|----------|---------------|
| Authentication fails | Check API key in `.env` | [Configuration](API_REFERENCE.md#configuration) |
| Webhook signature invalid | Verify webhook secret | [Webhooks](API_REFERENCE.md#webhooks) |
| Rate limiting | Implement retry logic | [Rate Limiting](API_REFERENCE.md#rate-limiting) |
| Mockery conflicts in tests | Use `skipAuth` parameter | [Testing](API_REFERENCE.md#testing) |

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](../CONTRIBUTING.md) for details.

## License

The Qredit Laravel SDK is open-source software licensed under the [MIT license](../LICENSE.md).

---

**Documentation Version**: 1.0.0
**SDK Version**: 0.1.1
**Last Updated**: December 31, 2024