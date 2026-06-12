<?php

/**
 * Test implementation of FreemiusProvider.
 *
 * @package TheWPSquad\FreemiusRest\Tests\Stubs
 */

namespace TheWPSquad\FreemiusRest\Tests\Stubs;

use Freemius;
use TheWPSquad\FreemiusRest\Contracts\FreemiusProvider;
use Throwable;

/**
 * Concrete FreemiusProvider for use in tests.
 *
 * Pass a Freemius mock as $fs to control SDK responses.
 */
class MockProvider implements FreemiusProvider {

    /** @var Freemius */
    private Freemius $fs;

    /** @var string[] */
    private array $errors = array();

    public function __construct( Freemius $fs ) {
        $this->fs = $fs;
    }

    public function get_fs(): Freemius        { return $this->fs; }
    public function get_plugin_slug(): string { return 'my-plugin'; }
    public function get_rest_version(): string { return 'v2'; }
    public function get_capability(): string  { return 'manage_options'; }
    public function get_text_domain(): string { return 'my-plugin'; }
    public function get_cache_prefix(): string { return 'my_plugin_'; }

    public function log_error( Throwable $exception, string $context ): void {
        $this->errors[] = "[{$context}] " . $exception->getMessage();
    }

    /**
     * Return logged error strings (for assertions).
     *
     * @return string[]
     */
    public function get_logged_errors(): array {
        return $this->errors;
    }
}
