# Contributing to Qredit Laravel SDK

First off, thank you for considering contributing to the Qredit Laravel SDK! It's people like you that make this package better for everyone.

## Code of Conduct

By participating in this project, you are expected to uphold our Code of Conduct:
- Be respectful and inclusive
- Welcome newcomers and help them get started
- Focus on what is best for the community
- Show empathy towards other community members

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples to demonstrate the steps**
- **Describe the behavior you observed and what you expected**
- **Include screenshots if relevant**
- **Include your environment details**:
  - PHP version
  - Laravel version
  - Package version
  - Operating system

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- **Use a clear and descriptive title**
- **Provide a detailed description of the proposed enhancement**
- **Explain why this enhancement would be useful**
- **List any alternative solutions you've considered**

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies**: `composer install`
3. **Make your changes** following our coding standards
4. **Add tests** for any new functionality
5. **Update documentation** as needed
6. **Ensure tests pass**: `composer test`
7. **Format your code**: `composer format`
8. **Run static analysis**: `composer analyse`
9. **Commit your changes** using descriptive commit messages
10. **Push to your fork** and submit a pull request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/qredit-payment-gateway.git
cd qredit-payment-gateway

# Install dependencies
composer install

# Run tests
composer test

# Format code
composer format

# Run static analysis
composer analyse
```

## Coding Standards

We follow PSR-12 coding standards and use Laravel Pint for formatting:

```bash
# Check code style
vendor/bin/pint --test

# Fix code style
vendor/bin/pint
```

### Key Guidelines

- **Classes**: Use PascalCase (e.g., `PaymentRequest`)
- **Methods/Functions**: Use camelCase (e.g., `createPayment()`)
- **Variables**: Use camelCase (e.g., `$paymentData`)
- **Constants**: Use UPPER_SNAKE_CASE (e.g., `DEFAULT_TIMEOUT`)
- **Files**: Match class names (e.g., `PaymentRequest.php`)

### Documentation

- Add PHPDoc blocks for all classes, methods, and properties
- Include parameter types and return types
- Add descriptions that explain "why" not just "what"
- Update README.md for new features

Example:
```php
/**
 * Create a new payment request.
 *
 * This method handles the creation of payment requests including
 * validation, formatting, and API communication.
 *
 * @param array $data The payment data including amount, currency, etc.
 * @return array The response from the payment gateway
 * @throws QreditApiException If the API request fails
 */
public function createPayment(array $data): array
{
    // Implementation
}
```

## Testing

We maintain high test coverage. Please write tests for your code:

```php
// tests/Feature/YourFeatureTest.php
public function test_your_feature_works_correctly()
{
    // Arrange
    $data = ['amount' => 100];

    // Act
    $result = $this->qredit->yourMethod($data);

    // Assert
    $this->assertEquals(100, $result['amount']);
}
```

Run tests with:
```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test
vendor/bin/phpunit tests/Feature/YourFeatureTest.php
```

## Commit Messages

Use clear and meaningful commit messages:

- **feat**: New feature (e.g., `feat: add refund functionality`)
- **fix**: Bug fix (e.g., `fix: resolve token caching issue`)
- **docs**: Documentation (e.g., `docs: update installation guide`)
- **style**: Formatting (e.g., `style: fix indentation`)
- **refactor**: Code refactoring (e.g., `refactor: simplify auth logic`)
- **test**: Testing (e.g., `test: add payment request tests`)
- **chore**: Maintenance (e.g., `chore: update dependencies`)

## Release Process

1. Update version in `composer.json`
2. Update CHANGELOG.md
3. Create a git tag: `git tag v1.0.0`
4. Push tags: `git push --tags`
5. Create GitHub release
6. Package will auto-publish to Packagist

## Getting Help

- **Discord**: Join our Discord server (link in README)
- **Issues**: Open a GitHub issue
- **Email**: dev@qredit.com

## Recognition

Contributors will be recognized in:
- README.md contributors section
- GitHub contributors page
- Release notes

## Financial Contributions

If you'd like to financially support the project, consider:
- Sponsoring via GitHub Sponsors
- Corporate sponsorship

Thank you for contributing! 🎉