<?php

/**
 * PHPUnit test bootstrap.
 *
 * Loads Composer autoloader, stubs for WordPress + Freemius, and
 * initialises Brain\Monkey for WP function mocking.
 *
 * @package TheWPSquad\FreemiusRest\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Constants first — Mockery evaluates default param values at mock-generation
// time, so WP_FS__* constants must exist before the class stubs are loaded.
require_once __DIR__ . '/phpstan/stubs/wordpress-extra.php';

// Freemius class hierarchy stubs (generated from the real SDK).
// Constants stub excluded — it calls is_multisite() which requires WordPress.
if ( ! class_exists( 'Freemius' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/mralaminahamed/freemius-stubs/freemius-stubs.stub';
}
