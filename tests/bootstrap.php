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

// Freemius class + constant stubs (generated from the real SDK).
require_once dirname( __DIR__ ) . '/vendor/mralaminahamed/freemius-stubs/freemius-stubs.stub';
require_once dirname( __DIR__ ) . '/vendor/mralaminahamed/freemius-stubs/freemius-constants-stubs.stub';

// WordPress extra stubs (DAY_IN_SECONDS, AUTH_KEY) not provided by Brain\Monkey.
require_once __DIR__ . '/Stubs/wordpress-extra.php';
