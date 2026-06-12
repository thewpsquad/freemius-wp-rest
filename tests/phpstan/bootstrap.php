<?php

// Freemius class hierarchy + constants for PHPStan runtime reflection.
if ( ! class_exists( 'Freemius' ) ) {
	require_once dirname( __DIR__, 2 ) . '/vendor/mralaminahamed/freemius-stubs/freemius-stubs.stub';
	require_once dirname( __DIR__, 2 ) . '/vendor/mralaminahamed/freemius-stubs/freemius-constants-stubs.stub';
}
