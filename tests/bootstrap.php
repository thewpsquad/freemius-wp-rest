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

// Freemius + WordPress extra stubs (constants, missing functions).
require_once __DIR__ . '/Stubs/freemius.php';
require_once __DIR__ . '/Stubs/wordpress-extra.php';
