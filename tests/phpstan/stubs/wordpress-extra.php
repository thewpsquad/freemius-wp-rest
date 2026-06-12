<?php

/**
 * WordPress function/constant stubs not covered by szepeviktor/phpstan-wordpress.
 *
 * Only add what PHPStan actually flags as missing. Do not duplicate stubs
 * already provided by the WordPress stubs package.
 *
 * @package TheWPSquad\FreemiusRest\Tests\Stubs
 */

// phpcs:disable

// ---------------------------------------------------------------------------
// WordPress class stubs — minimal but sufficient for Mockery + route tests.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private array $errors     = array();
        private array $error_data = array();
        public function __construct( string $code = '', string $message = '', $data = '' ) {
            if ( '' !== $code ) { $this->add( $code, $message, $data ); }
        }
        public function add( string $code, string $message, $data = '' ): void {
            $this->errors[ $code ][] = $message;
            if ( '' !== $data ) { $this->error_data[ $code ] = $data; }
        }
        public function get_error_codes(): array { return array_keys( $this->errors ); }
        public function get_error_code(): string { $c = $this->get_error_codes(); return $c[0] ?? ''; }
        public function get_error_message( string $code = '' ): string {
            if ( '' === $code ) { $code = $this->get_error_code(); }
            return $this->errors[ $code ][0] ?? '';
        }
        public function get_error_data( string $code = '' ) {
            if ( '' === $code ) { $code = $this->get_error_code(); }
            return $this->error_data[ $code ] ?? null;
        }
        public function has_errors(): bool { return array() !== $this->errors; }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public int $status;
        public function __construct( $data = null, int $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }
        public function get_data() { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private array $params = array();
        public function set_param( string $key, $value ): void { $this->params[ $key ] = $value; }
        public function get_param( string $key ) { return $this->params[ $key ] ?? null; }
    }
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
    class WP_REST_Server {
        const READABLE   = 'GET';
        const CREATABLE  = 'POST';
        const EDITABLE   = 'POST, PUT, PATCH';
        const DELETABLE  = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

// ---------------------------------------------------------------------------
// WordPress constants
// ---------------------------------------------------------------------------

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'AUTH_KEY' ) ) {
    define( 'AUTH_KEY', 'test-auth-key' );
}

// Freemius constants used as default parameter values in freemius-stubs.stub.
// Mockery evaluates default params at mock-generation time so these must be
// defined before requiring the class stubs.
if ( ! defined( 'WP_FS__MODULE_TYPE_PLUGIN' ) ) {
    define( 'WP_FS__MODULE_TYPE_PLUGIN', 'plugin' );
}
if ( ! defined( 'WP_FS__DEFAULT_PRIORITY' ) ) {
    define( 'WP_FS__DEFAULT_PRIORITY', 10 );
}
if ( ! defined( 'WP_FS__PERIOD_ANNUALLY' ) ) {
    define( 'WP_FS__PERIOD_ANNUALLY', 'annual' );
}
if ( ! defined( 'WP_FS__TIME_24_HOURS_IN_SEC' ) ) {
    define( 'WP_FS__TIME_24_HOURS_IN_SEC', 86400 );
}
if ( ! defined( 'WP_FS__TIME_WEEK_IN_SEC' ) ) {
    define( 'WP_FS__TIME_WEEK_IN_SEC', 604800 );
}
if ( ! defined( 'WP_FS__SCRIPT_START_TIME' ) ) {
    define( 'WP_FS__SCRIPT_START_TIME', 0 );
}
if ( ! defined( 'WP_FS__SDK_VERSION' ) ) {
    define( 'WP_FS__SDK_VERSION', '2.0.0' );
}
if ( ! defined( 'WP_FS__DIR_IMG' ) ) {
    define( 'WP_FS__DIR_IMG', '' );
}
if ( ! defined( 'WP_FS__DIR_JS' ) ) {
    define( 'WP_FS__DIR_JS', '' );
}
