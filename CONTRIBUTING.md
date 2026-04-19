# Contributing to the Qredit Laravel SDK

Thanks for considering a contribution. This package is actively maintained and we review PRs quickly. The guide below is short on purpose — read it end to end before opening your first PR.

## Code of Conduct

This project adheres to the [Contributor Covenant](.github/CODE_OF_CONDUCT.md). By participating, you agree to uphold it. Report unacceptable behavior to `shakerawad@paltechhub.com`.

## How to contribute

### Reporting bugs

Search [existing issues](https://github.com/qredit/laravel-qredit/issues) first. If you don't find one, open a [bug report](https://github.com/qredit/laravel-qredit/issues/new?template=bug_report.yml) and include:

- PHP + Laravel + package versions
- Minimal reproducer (code snippet or failing test)
- Full exception trace (redact credentials)
- Which host you hit (UAT / production / VPN)

### Suggesting features

Open a [feature request](https://github.com/qredit/laravel-qredit/issues/new?template=feature_request.yml) or start a [Discussion](https://github.com/qredit/laravel-qredit/discussions). For larger changes (new signing variants, new transport layers) please discuss before implementing — it saves everyone time.

### Security vulnerabilities

**Never open a public issue for security reports.** Email `shakerawad@paltechhub.com` or use [GitHub's private vulnerability reporting](https://github.com/qredit/laravel-qredit/security/advisories/new). See [SECURITY.md](SECURITY.md).

## Development setup

```bash
git clone https://github.com/your-fork/laravel-qredit.git
cd laravel-qredit
composer install
composer test
```

All required dev dependencies are in `composer.json` — no extra toolchain setup.

## Running the checks

```bash
composer test              # Pest — all unit + feature tests
composer test-coverage     # Fails below 80% line coverage
composer format            # Pint — autoformat + ensure PSR-12 alignment
composer check             # format + test (what CI runs)
```

Before opening a PR, `composer check` must pass locally.

## Writing code

### House rules

- **PHP 8.1+.** Use constructor property promotion, enums, readonly properties where they fit.
- **Strict types on every file.** `declare(strict_types=1);` at the top.
- **No inline comments describing what obvious code does** — explain *why* for non-obvious constraints, otherwise omit.
- **No new framework dependencies** without prior discussion (we pull in Saloon + Carbon — that's the ceiling).
- **Don't touch `vendor/`.** CI rebuilds from `composer.lock` — commit the lockfile only when you change `composer.json`.

### Signing / algorithm changes

The HMAC algorithm is verified against live UAT and pinned by golden vectors in [`tests/Unit/HmacSignerTest.php`](tests/Unit/HmacSignerTest.php). If you change `HmacSigner`:

1. Re-verify against live UAT (the `qredit:call auth` command covers this).
2. Regenerate golden vectors and commit them alongside the code change.
3. Document the why in `docs/SIGNING.md` — the "why not Angular reference" footnote in that file is the pattern.

### Adding a new endpoint wrapper

1. Create `src/Requests/{Group}/{Name}Request.php` — extend `BaseQreditRequest`, use the `HasMessageId` trait.
2. Add a facade method phpdoc to `src/Facades/Qredit.php`.
3. Wire it into `src/Qredit.php` with an `@method`-friendly public method that delegates to the request class.
4. Cover it in `tests/Feature/` using Saloon's `MockClient`.
5. Document it in `docs/API_REFERENCE.md`.

### Adding a new tenant resolver

1. Create `src/Tenancy/{Name}TenantResolver.php` implementing `TenantResolver`.
2. Add it to the list in `docs/MULTITENANCY.md` with one example.
3. Add a unit test in `tests/Unit/TenancyTest.php`.

## Writing tests

- All tests are [Pest](https://pestphp.com/). Avoid mixing PHPUnit-style classes unless you're editing a legacy test file.
- Feature tests live in `tests/Feature/`; unit tests in `tests/Unit/`.
- Prefer Saloon `MockClient` over full HTTP mocks.
- Golden-vector tests (like `HmacSignerTest`) should include the source of the expected value in a comment — "live UAT 2026-04-16" beats "regenerated".

```php
// tests/Feature/YourFeatureTest.php

it('creates a payment with amountCents', function () {
    $mock = new MockClient([
        CreatePaymentRequest::class => MockResponse::make(['status' => true]),
    ]);

    $client = Qredit::make([...]);
    $client->getConnector()->withMockClient($mock);

    $result = $client->createPayment(['amountCents' => 100]);

    expect($result['status'])->toBeTrue();
});
```

Run one file:
```bash
vendor/bin/pest tests/Feature/YourFeatureTest.php
```

## Commit messages

Conventional Commits, short and specific:

- `feat: add calculateFees request wrapper`
- `fix: handle null checkoutUrl in createPayment response`
- `docs: update SIGNING.md with ccc-version header`
- `test: pin golden vectors against live UAT`
- `refactor: extract credential resolution into helper`
- `chore: bump phpstan to v2`

One logical change per commit; squash follow-up fixes before opening your PR.

## Pull requests

1. Fork, branch from `main` (`git checkout -b feat/my-thing`).
2. Push commits, run `composer check` locally.
3. Open a PR using the template. Tie it to an issue if one exists (`Closes #123`).
4. CI must stay green on every push. If CI fails, fix it — don't rely on reviewers to flag it.
5. Expect one round of review feedback. Address it in fixup commits; squash before merge or let the maintainer squash-merge.

## Release process (maintainers)

1. Bump version references in `CHANGELOG.md` (add a new section; keep format).
2. Create a tag: `git tag -a vX.Y.Z -m "Release vX.Y.Z"`.
3. Push with tags: `git push --follow-tags`.
4. GitHub Actions publishes the release; Packagist updates automatically.

## Recognition

Every merged PR earns a line in the changelog and the [GitHub contributors list](https://github.com/qredit/laravel-qredit/graphs/contributors). Meaningful contributions that sustain the project can be highlighted in README's Credits section — open a PR for that.

## Financial support

If you or your company depends on this SDK and want to support maintenance, see the Sponsor button on the repo (`.github/FUNDING.yml`). Corporate sponsors are listed in the README.

Thanks again.
