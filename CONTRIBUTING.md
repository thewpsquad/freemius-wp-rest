# Contributing

Contributions are welcome. Please read this before opening a PR.

---

## Scope

This package is intentionally narrow: **Freemius proxy routes for the WP REST API**. PRs that add plugin-specific logic, non-Freemius endpoints, or framework dependencies will be declined.

Good contributions:
- New Freemius-related route classes (e.g. payments, subscriptions)
- New `FreemiusProvider` interface methods backed by a real Freemius SDK method
- Bug fixes in existing route handlers
- PHP version compatibility fixes

---

## Setup

```bash
git clone https://github.com/thewpsquad/freemius-wp-rest.git
cd freemius-wp-rest
composer install
```

---

## Coding standards

```bash
vendor/bin/phpcs
```

- WordPress Coding Standards (WPCS 3.x)
- PHPCompatibilityWP — target PHP 7.4+
- No union types, no promoted constructor properties, no named arguments
- Variable text domain (`$this->provider->get_text_domain()`) is intentional — do not replace with a hardcoded string

---

## PHP compatibility

All code must work on PHP 7.4 through 8.3. Check with:

```bash
find src -name "*.php" -exec php -l {} \;
```

Test against multiple PHP versions before submitting.

---

## Interface changes

`FreemiusProvider` is the public API contract. Any change that adds, removes, or modifies a method signature is a **breaking change** and requires a major version bump. Discuss in an issue before implementing.

---

## Submitting a PR

1. Fork the repo and create a branch from `trunk`
2. Make changes — one logical change per PR
3. Run PHPCS and fix all errors
4. Add a `CHANGELOG.md` entry under `[Unreleased]`
5. Fill in the PR template
6. Open the PR against `trunk`

---

## Commit style

```
type(scope): short description

feat(routes): add Payments route handler
fix(account): guard against null subscription in get_license
chore(ci): add PHP 8.3 to matrix
```

Types: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`
