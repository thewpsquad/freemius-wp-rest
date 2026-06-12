<?php

/**
 * Freemius SDK stubs for static analysis and testing.
 *
 * These are minimal stub definitions so PHPStan and PHPUnit can analyse code
 * that depends on the Freemius SDK without requiring the actual SDK to be
 * installed as a Composer dependency.
 *
 * @package TheWPSquad\FreemiusRest\Tests\Stubs
 */

// phpcs:disable

if ( ! class_exists( 'Freemius' ) ) {

    /**
     * @phpstan-consistent-constructor
     */
    class Freemius {
        public function is_registered( bool $strict = false ): bool { return false; }
        public function is_anonymous(): bool { return false; }
        public function is_pending_activation(): bool { return false; }
        public function is_premium(): bool { return false; }
        public function is_paying(): bool { return false; }
        public function is_trial(): bool { return false; }
        public function is_trial_utilized(): bool { return false; }
        public function is_in_trial_promotion(): bool { return false; }
        public function is_free_plan(): bool { return false; }
        public function has_active_license(): bool { return false; }
        public function is_tracking_allowed(): bool { return false; }
        public function can_use_premium_code( bool $refresh = false ): bool { return false; }

        public function get_user(): ?object { return null; }
        public function get_site(): ?object { return null; }
        public function get_plan(): ?object { return null; }
        public function _get_license(): ?object { return null; }
        public function _get_subscription( int $license_id ): ?object { return null; }

        public function get_account_url(): string { return ''; }
        public function get_upgrade_url(): string { return ''; }
        public function get_trial_url(): string { return ''; }

        public function get_addons(): ?array { return null; }
        public function get_addon_by_id( int $id ): ?object { return null; }
        public function is_addon_activated( int $addon_id ): bool { return false; }
        public function get_addon_url( int $addon_id ): ?string { return null; }

        public function get_affiliate(): ?object { return null; }
        public function get_affiliate_terms(): ?object { return null; }

        public function get_api_user_scope(): ?FS_Api { return null; }
        public function get_api_plugin_scope(): ?FS_Api { return null; }

        public function opt_in(
            $first     = false,
            $last      = false,
            $is_marketing_allowed = false,
            $license_key = false,
            $is_extensions_tracking_allowed = true,
            $is_diagnostic_tracking_allowed = true,
            $send_confirmation_email = true,
            $redirect = null,
            $extra = array(),
            $setup_account = true
        ): void {}

        public function connect_again(): void {}
    }
}

if ( ! class_exists( 'FS_Api' ) ) {

    class FS_Api {
        /** @return mixed */
        public function get( string $path ) { return new stdClass(); }

        /** @return mixed */
        public function post( string $path, array $params = array() ) { return new stdClass(); }

        /** @return mixed */
        public function call( string $path, string $method = 'GET', array $params = array() ) { return new stdClass(); }

        /** @param mixed $result */
        public static function is_api_error( $result ): bool { return false; }
    }
}
