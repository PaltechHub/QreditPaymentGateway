# Security Policy

## Supported Versions

We release security patches for the latest minor release line. Earlier lines receive fixes only for critical vulnerabilities.

| Version | Supported          |
| ------- | ------------------ |
| 0.3.x   | :white_check_mark: |
| 0.2.x   | :warning: Critical fixes only |
| < 0.2   | :x:                |

## Reporting a Vulnerability

We take the security of the Qredit Laravel SDK seriously. If you have discovered a security vulnerability, we appreciate your help in disclosing it to us in a responsible manner.

### 🔒 Private Disclosure Process

**DO NOT** create a public GitHub issue for security vulnerabilities.

Instead, please report security vulnerabilities by emailing:

📧 **shakerawad@paltechhub.com**

Or use GitHub's private vulnerability reporting:

1. Go to the Security tab of our repository
2. Click on "Report a vulnerability"
3. Fill out the form with details

### What to Include

Please provide the following information:

- **Description**: Clear description of the vulnerability
- **Impact**: The potential impact of the vulnerability
- **Steps to Reproduce**: Detailed steps to reproduce the issue
- **Affected Versions**: List of affected versions
- **Suggested Fix**: If you have a suggestion for fixing the issue

### Example Report Format

```markdown
**Summary**: [Brief description]

**Severity**: [Critical/High/Medium/Low]

**Description**:
[Detailed description of the vulnerability]

**Steps to Reproduce**:
1. Configure the SDK with...
2. Call the following method...
3. Observe that...

**Impact**:
[What can an attacker do with this vulnerability?]

**Affected Versions**:
- v1.0.0 - v1.0.5

**Suggested Mitigation**:
[Your suggested fix, if any]

**Additional Information**:
[Any other relevant information]
```

## Response Timeline

- **Initial Response**: Within 48 hours
- **Assessment**: Within 5 business days
- **Fix Timeline**: Based on severity
  - Critical: 24-48 hours
  - High: 3-5 days
  - Medium: 1-2 weeks
  - Low: Next regular release

## Security Update Process

1. **Confirmation**: We'll confirm receipt and begin investigation
2. **Assessment**: Determine the scope and impact
3. **Fix Development**: Develop and test a fix
4. **Private Disclosure**: Notify affected users if necessary
5. **Public Release**: Release the fix
6. **Public Disclosure**: Publish security advisory

## Security Best Practices

When using this SDK, please follow these security best practices:

### API Key Protection

```php
// ❌ DON'T hardcode API keys
$qredit = new Qredit('YOUR_API_KEY');

// ✅ DO use environment variables
$qredit = new Qredit(env('QREDIT_API_KEY'));
```

### Webhook Verification

```php
// ✅ ALWAYS verify webhook signatures
public function handleWebhook(Request $request)
{
    $signature = $request->header('X-Qredit-Signature');

    if (!Qredit::verifyWebhookSignature($request->getContent(), $signature)) {
        abort(401, 'Invalid signature');
    }

    // Process webhook...
}
```

### Input Validation

```php
// ✅ ALWAYS validate user input
$validated = $request->validate([
    'amount' => 'required|numeric|min:0.01',
    'currency' => 'required|in:ILS,USD,EUR',
    'email' => 'required|email',
]);

$payment = Qredit::createPaymentRequest($validated);
```

### Error Handling

```php
// ✅ DON'T expose sensitive information in errors
try {
    $payment = Qredit::createPaymentRequest($data);
} catch (QreditException $e) {
    // Log full error internally
    Log::error('Payment failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

    // Return generic error to user
    return response()->json(['error' => 'Payment processing failed'], 500);
}
```

### Logging

```php
// ❌ DON'T log sensitive data
Log::info('Payment created', $paymentData); // May contain sensitive info

// ✅ DO sanitize before logging
Log::info('Payment created', [
    'reference' => $paymentData['reference'],
    'amount' => $paymentData['amount'],
    'customer_id' => $paymentData['customer_id'], // Not the full details
]);
```

## Security Features

This SDK includes several security features:

- **Automatic token refresh**: Prevents token expiration attacks
- **Request signing**: All requests are signed with your API key
- **Webhook verification**: Built-in signature verification for webhooks
- **Rate limiting support**: Respects Qredit API rate limits
- **Secure defaults**: Secure configuration out of the box
- **Input sanitization**: Automatic sanitization of user inputs

## Hall of Fame

We would like to thank the following security researchers for responsibly disclosing vulnerabilities:

<!-- Add researchers here as vulnerabilities are reported and fixed -->
- Your name could be here!

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Documentation](https://laravel.com/docs/security)
- [PHP Security Guide](https://phpsecurity.readthedocs.io/)

---

**Remember**: Security is everyone's responsibility. If you notice something, say something!