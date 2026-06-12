# Changelog

All notable changes to this package are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

## [1.0.0] — 2026-06-12

### Added

- `FreemiusProvider` interface — contract for injecting Freemius instance + plugin metadata
- `Route\Base` — abstract base class with namespace construction, route registration, permission checking, and response helpers
- `Routes\Account` — six endpoints: `GET /account`, `GET /account/license`, `POST /account/license/activate`, `POST /account/license/deactivate/intent`, `POST /account/license/deactivate`, `POST /account/tracking`
- `Routes\Affiliate` — two endpoints: `GET /affiliate` (cached 12 h), `POST /affiliate/apply`
- `Routes\Pricing` — one endpoint: `GET /pricing` (live, no cache)
- CI workflow — PHP 7.4–8.3 syntax check + PHPCS matrix
- PHPCS config — WordPress-Core + WordPress-Docs + PHPCompatibilityWP (7.4+)
