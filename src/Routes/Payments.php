<?php

/**
 * Payments REST Routes
 *
 * Proxy endpoints for Freemius payment history via the user-scope API.
 * List is cached for 1 hour; individual payment records are not cached.
 *
 * Endpoints registered under `{namespace}/payments`:
 *   GET /payments            — paginated payment list
 *   GET /payments/{id}       — single payment detail
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
 * Payments REST route handler.
 */
class Payments extends Base {

	/**
	 * Transient key suffix for the payments list cache.
	 */
	private const PAYMENTS_CACHE_SUFFIX = 'payments_cache_';

	/**
	 * Payments list cache TTL in seconds (1 hour).
	 */
	private const PAYMENTS_CACHE_TTL = 3600;

	/**
	 * Maximum records per page.
	 */
	private const MAX_PER_PAGE = 50;

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/payments'       => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_payments' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'count'  => array(
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => self::MAX_PER_PAGE,
							'sanitize_callback' => 'absint',
						),
						'offset' => array(
							'type'              => 'integer',
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			),
			'/payments/(?P<id>[\d]+)' => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_payment' ),
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
	 * GET /payments — paginated payment list (cached 1 h per page).
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payments( WP_REST_Request $request ) {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$count  = (int) $request->get_param( 'count' );
			$offset = (int) $request->get_param( 'offset' );

			$cache_key = $this->provider->get_cache_prefix()
				. self::PAYMENTS_CACHE_SUFFIX
				. "{$count}_{$offset}";

			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $this->success_response( $cached );
			}

			$result = $fs->get_api_user_scope()->get(
				"/payments.json?count={$count}&offset={$offset}"
			);

			if ( is_wp_error( $result ) ) {
				return $this->error_response( 'api_error', $result->get_error_message(), 502 );
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				return $this->api_error_response( $result->error );
			}

			$payments = array();
			if ( is_object( $result ) && isset( $result->payments ) && is_array( $result->payments ) ) {
				foreach ( $result->payments as $p ) {
					$payments[] = $this->normalize_payment( $p );
				}
			}

			$payload = array(
				'payments' => $payments,
				'total'    => is_object( $result ) ? ( $result->total ?? count( $payments ) ) : count( $payments ),
				'count'    => $count,
				'offset'   => $offset,
			);

			set_transient( $cache_key, $payload, self::PAYMENTS_CACHE_TTL );

			return $this->success_response( $payload );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Payments::get_payments' );
		}
	}

	/**
	 * GET /payments/{id} — single payment detail (not cached).
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment( WP_REST_Request $request ) {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$id     = absint( $request->get_param( 'id' ) );
			$result = $fs->get_api_user_scope()->get( "/payments/{$id}.json" );

			if ( is_wp_error( $result ) ) {
				return $this->error_response( 'api_error', $result->get_error_message(), 502 );
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				return $this->api_error_response( $result->error );
			}

			if ( ! is_object( $result ) || ! isset( $result->id ) ) {
				return $this->error_response(
					'not_found',
					esc_html__( 'Payment not found.', $this->provider->get_text_domain() ),
					404
				);
			}

			return $this->success_response( $this->normalize_payment( $result ) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Payments::get_payment' );
		}
	}

	/**
	 * Normalize a raw Freemius payment object to a consistent array shape.
	 *
	 * @param object $p Raw payment object from the API.
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_payment( object $p ): array {
		return array(
			'id'              => $p->id ?? null,
			'gross'           => $p->gross ?? null,
			'net'             => $p->net ?? null,
			'tax'             => $p->tax ?? null,
			'currency'        => $p->currency ?? 'usd',
			'created'         => $p->created ?? null,
			'gateway'         => $p->gateway ?? null,
			'transaction_id'  => $p->transaction_id ?? null,
			'subscription_id' => $p->subscription_id ?? null,
			'plan_id'         => $p->plan_id ?? null,
			'is_refunded'     => (bool) ( $p->is_refunded ?? false ),
		);
	}

	/**
	 * Convert a Freemius API error object to a WP_Error response.
	 *
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
