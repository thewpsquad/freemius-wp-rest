<?php

/**
 * FreemiusProvider Interface
 *
 * Contract that each plugin must implement to supply a configured Freemius
 * instance and plugin-level metadata to the shared REST route classes.
 *
 * @package TheWPSquad\FreemiusRest\Contracts
 */

namespace TheWPSquad\FreemiusRest\Contracts;

use Freemius;
use Throwable;

/**
 * Supplies the Freemius instance and plugin context to REST route classes.
 */
interface FreemiusProvider {

	/**
	 * Return the initialised Freemius SDK instance for this plugin.
	 *
	 * Must be callable after the Freemius SDK has been initialised (i.e. on or
	 * after the `init` hook when using the standard SDK bootstrap).
	 */
	public function get_fs(): Freemius;

	/**
	 * REST namespace slug, e.g. `'divi-squad'`.
	 *
	 * Combined with get_rest_version() to form the full namespace
	 * (`divi-squad/v2`).
	 */
	public function get_plugin_slug(): string;

	/**
	 * REST API version string, e.g. `'v2'`.
	 */
	public function get_rest_version(): string;

	/**
	 * WordPress capability required to access all Freemius REST routes.
	 *
	 * Typically `'manage_options'`.
	 */
	public function get_capability(): string;

	/**
	 * Text domain used for translatable strings returned by this package.
	 *
	 * Should match the consuming plugin's `Text Domain` header value.
	 */
	public function get_text_domain(): string;

	/**
	 * Prefix for transient/option keys created by this package.
	 *
	 * e.g. `'divi_squad_'` → produces `divi_squad_affiliate_cache`,
	 * `divi_squad_deactivate_intent_<id>`, etc.
	 */
	public function get_cache_prefix(): string;

	/**
	 * Log a caught exception with plugin-level context.
	 *
	 * Implementations should forward to the plugin's own error logger so that
	 * exceptions from this package appear in the same log as plugin errors.
	 *
	 * @param Throwable $exception The caught exception.
	 * @param string    $context   Human-readable label for where it was caught.
	 */
	public function log_error( Throwable $exception, string $context ): void;
}
