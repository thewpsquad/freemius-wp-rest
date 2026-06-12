# freemius-wp-rest

Reusable WP REST API proxy routes for the [Freemius WordPress SDK](https://freemius.com/help/documentation/wordpress-sdk/).

Provides three ready-made route handlers — **Account**, **Affiliate**, and **Pricing** — that any WordPress plugin using Freemius can register by implementing a single interface.

---

## Requirements

- PHP 7.4+
- WordPress 6.0+
- Freemius WordPress SDK (bundled in the consuming plugin)

---

## Installation

```bash
composer require thewpsquad/freemius-wp-rest
```

---

## Usage

### 1. Implement `FreemiusProvider`

```php
use TheWPSquad\FreemiusRest\Contracts\FreemiusProvider;
use Freemius;
use Throwable;

class MyPluginFreemiusProvider implements FreemiusProvider {

    public function get_fs(): Freemius        { return my_fs(); }
    public function get_plugin_slug(): string { return 'my-plugin'; }
    public function get_rest_version(): string { return 'v2'; }
    public function get_capability(): string  { return 'manage_options'; }
    public function get_text_domain(): string { return 'my-plugin'; }
    public function get_cache_prefix(): string { return 'my_plugin_'; }

    public function log_error( Throwable $e, string $context ): void {
        // forward to your plugin's error logger
        error_log( "[{$context}] " . $e->getMessage() );
    }
}
```

### 2. Register routes inside `rest_api_init`

```php
add_action( 'rest_api_init', function () {
    $provider = new MyPluginFreemiusProvider();

    ( new \TheWPSquad\FreemiusRest\Routes\Account( $provider ) )->register();
    ( new \TheWPSquad\FreemiusRest\Routes\Affiliate( $provider ) )->register();
    ( new \TheWPSquad\FreemiusRest\Routes\Pricing( $provider ) )->register();
} );
```

Routes register under `{plugin_slug}/{rest_version}` — e.g. `my-plugin/v2`.

---

## Endpoints

### Account — `GET /my-plugin/v2/account`

Returns merged user + site + plan + license + boolean flags.

| Endpoint | Method | Description |
|---|---|---|
| `/account` | GET | User, site, plan, license, flags |
| `/account/license` | GET | License detail + subscription + upgrade URL |
| `/account/license/activate` | POST | Activate a license key (`license_key`) |
| `/account/license/deactivate/intent` | POST | Issue a deactivation confirmation token |
| `/account/license/deactivate` | POST | Execute deactivation (`token`) |
| `/account/tracking` | POST | Toggle Freemius usage tracking (`enabled`) |

### Affiliate — `GET /my-plugin/v2/affiliate`

Cached 12 h. Clears on successful application.

| Endpoint | Method | Description |
|---|---|---|
| `/affiliate` | GET | Affiliate status + programme terms |
| `/affiliate/apply` | POST | Submit affiliate application |

### Pricing — `GET /my-plugin/v2/pricing`

No cache — always returns live prices.

| Endpoint | Method | Description |
|---|---|---|
| `/pricing` | GET | All plans + pricing tiers from Freemius |

---

## `FreemiusProvider` interface

| Method | Returns | Purpose |
|---|---|---|
| `get_fs()` | `Freemius` | Initialised SDK instance |
| `get_plugin_slug()` | `string` | REST namespace prefix (`my-plugin`) |
| `get_rest_version()` | `string` | REST namespace version (`v2`) |
| `get_capability()` | `string` | WP capability gate (default `manage_options`) |
| `get_text_domain()` | `string` | Plugin text domain for translated error strings |
| `get_cache_prefix()` | `string` | Transient key prefix (`my_plugin_`) |
| `log_error()` | `void` | Error logging bridge to your plugin's logger |

---

## License

GPL-3.0-only — see [LICENSE](LICENSE).
