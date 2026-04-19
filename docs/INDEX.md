# Documentation index

## Getting started
- [README](../README.md) — install + quick start
- [examples/BasicUsage.php](../examples/BasicUsage.php) — copy-paste recipes for every endpoint
- [examples/MultiTenantUsage.php](../examples/MultiTenantUsage.php) — full SAAS wiring
- [examples/WebhookHandler.php](../examples/WebhookHandler.php) — signed-callback handler

## Core guides
- [API_REFERENCE.md](API_REFERENCE.md) — every wrapped endpoint, request + response shapes
- [MULTITENANCY.md](MULTITENANCY.md) — the two contracts, integration recipes for Bagisto / Stancl / Spatie
- [SIGNING.md](SIGNING.md) — HMAC SHA512 algorithm, merchant guide §7 walkthrough, debugging
- [WEBHOOKS.md](WEBHOOKS.md) — event payloads, listener patterns, signature verification
- [TESTING.md](TESTING.md) — `FakeQredit`, Saloon `MockClient`, Pest examples

## Operations
- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) — every error code, every diagnostic step
- [QREDIT_SIGNATURE_ISSUE.md](QREDIT_SIGNATURE_ISSUE.md) — current known issue with UAT credentials (live until Qredit provisions)

## For tool builders
- [LLM_IMPLEMENTATION_GUIDE.md](LLM_IMPLEMENTATION_GUIDE.md) — structured reference for AI agents working on consumers

## Decision matrix

| Question | Read this |
|---|---|
| "I need to install the SDK" | [README](../README.md) |
| "I need to call Qredit from a single Laravel app" | [README — Quick start](../README.md#quick-start--single-tenant) |
| "My app is multi-tenant (SAAS, Bagisto, subdomain per customer)" | [MULTITENANCY.md](MULTITENANCY.md) |
| "I'm adding a new endpoint wrapper" | [LLM_IMPLEMENTATION_GUIDE.md — add an endpoint](LLM_IMPLEMENTATION_GUIDE.md) |
| "I want to understand the signature algorithm" | [SIGNING.md](SIGNING.md) |
| "I'm writing a feature test" | [TESTING.md](TESTING.md) |
| "I'm getting `code 1004 Bad Signature`" | [TROUBLESHOOTING.md](TROUBLESHOOTING.md) |
| "How do I handle a webhook?" | [WEBHOOKS.md](WEBHOOKS.md) |
| "How do I embed the checkout widget?" | [README — widget section](../README.md#the-checkout-widgets-sign-endpoint) |

## Project status

| Surface | State |
|---|---|
| SDK algorithm (signing, token cache, retry) | ✅ Complete, unit-tested |
| All 18 endpoint wrappers | ✅ Complete |
| Multi-tenant contracts (`CredentialProvider`, `TenantResolver`) | ✅ Complete |
| Ready-made `/sign` + `/webhook` controllers | ✅ Complete |
| Route macros | ✅ Complete |
| `FakeQredit` test double | ✅ Complete |
| `qredit:call` CLI (Postman replacement) | ✅ Complete, full endpoint coverage |
| `qredit:install` onboarding | ✅ Complete |
| Live UAT auth verification | ⚠️ Blocked on Qredit provisioning Jira credentials — see [QREDIT_SIGNATURE_ISSUE.md](QREDIT_SIGNATURE_ISSUE.md) |

The SDK is production-ready from a code perspective. The remaining blocker is credential provisioning on the Qredit side — once resolved, no code changes needed.
