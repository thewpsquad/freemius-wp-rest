<?php

/**
 * Addons REST Routes
 *
 * Proxy endpoints for Freemius add-on data via the plugin-scope API.
 * Data is cached for 6 hours as add-on listings rarely change.
 *
 * Endpoints registered under `{namespace}/addons`:
 *   GET /addons        — list all add-ons with activation state
 *   GET /addons/{id}   — single add-on detail
 *
 * @package TheWPSquad\FreemiusRest\Routes
 */

namespace TheWPSquad\FreemiusRest\Routes;

use TheWPSquad\FreemiusRest\Route\Base;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Addons REST route handler.
 */
class Addons extends Base {

	/**
	 * Transient key suffix for the add-ons list cache.
	 */
	private const ADDONS_CACHE_SUFFIX = 'addons_cache';

	/**
	 * Add-ons cache TTL in seconds (6 hours).
	 */
	private const ADDONS_CACHE_TTL = 21600;

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/addons'                      => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_addons' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/addons/(?P<id>[\d]+)'        => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_addon' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			),
		);
	}

	/**
	 * GET /addons — list all add-ons with activation state (cached 6 h).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_addons() {
		try {
			$fs = $this->provider->get_fs();

			$cache_key = $this->provider->get_cache_prefix() . self::ADDONS_CACHE_SUFFIX;
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $this->success_response( $cached );
			}

			// Try SDK local cache first; fall back to API.
			$sdk_addons = $fs->get_addons();

			if ( ! empty( $sdk_addons ) && is_array( $sdk_addons ) ) {
				$addons = array_map( function ( $addon ) use ( $fs ): array {
					return $this->normalize_addon( $addon, $fs );
				}, $sdk_addons );
			} else {
				$result = $fs->get_api_plugin_scope()->get( '/addons.json?enriched=true' );

				if ( is_wp_error( $result ) ) {
					return $this->error_response( 'api_error', $result->get_error_message(), 502 );
				}

				if ( is_object( $result ) && isset( $result->error ) ) {
					return $this->api_error_response( $result->error );
				}

				$raw    = is_object( $result ) && isset( $result->plugins ) && is_array( $result->plugins )
					? $result->plugins
					: array();
				$addons = array_map( function ( $addon ) use ( $fs ): array {
					return $this->normalize_addon( $addon, $fs );
				}, $raw );
			}

			$payload = array( 'addons' => $addons );
			set_transient( $cache_key, $payload, self::ADDONS_CACHE_TTL );

			return $this->success_response( $payload );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Addons::get_addons' );
		}
	}

	/**
	 * GET /addons/{id} — single add-on detail (not cached).
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_addon( WP_REST_Request $request ) {
		try {
			$fs = $this->provider->get_fs();
			$id = absint( $request->get_param( 'id' ) );

			// Check SDK local cache first.
			$sdk_addon = $fs->get_addon_by_id( $id );
			if ( $sdk_addon ) {
				return $this->success_response( $this->normalize_addon( $sdk_addon, $fs ) );
			}

			$result = $fs->get_api_plugin_scope()->get( "/addons/{$id}.json?enriched=true" );

			if ( is_wp_error( $result ) ) {
				return $this->error_response( 'api_error', $result->get_error_message(), 502 );
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				return $this->api_error_response( $result->error );
			}

			if ( ! is_object( $result ) || ! isset( $result->id ) ) {
				return $this->error_response(
					'not_found',
					esc_html__( 'Add-on not found.', $this->provider->get_text_domain() ),
					404
				);
			}

			return $this->success_response( $this->normalize_addon( $result, $fs ) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Addons::get_addon' );
		}
	}

	/**
	 * Normalize a raw add-on object to a consistent array shape.
	 *
	 * @param object $addon
	 * @param object $fs    Freemius instance (for activation state).
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_addon( object $addon, object $fs ): array {
		$id = isset( $addon->id ) ? (int) $addon->id : null;

		return array(
			'id'           => $id,
			'slug'         => $addon->slug ?? '',
			'title'        => $addon->title ?? '',
			'description'  => $addon->short_description ?? $addon->description ?? '',
			'icon'         => $addon->icon ?? null,
			'pricing_url'  => $id ? $fs->get_addon_url( $id ) : null,
			'is_activated' => $id ? (bool) $fs->is_addon_activated( $id ) : false,
			'plans'        => isset( $addon->plans ) && is_array( $addon->plans )
				? array_map( static function ( $p ): array {
					return array(
						'id'    => $p->id ?? null,
						'name'  => $p->name ?? '',
						'title' => $p->title ?? '',
					);
				}, $addon->plans )
				: array(),
		);
	}

	/**
	 * @param mixed $error
	 *
	 * @return WP_Error
	 */
	private function api_error_response( $error ): WP_Error {
		$msg = is_string( $error )
			? $error
			: ( is_object( $error ) && isset( $error->message ) ? (string) $error->message : '' );

		return $this->error_response( 'api_error', $msg, 502 );
	}
}
