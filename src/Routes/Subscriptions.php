<?php

/**
 * Subscriptions REST Routes
 *
 * Proxy endpoints for managing Freemius subscriptions via the user-scope API.
 * List is cached for 1 hour; cancellation clears the cache.
 *
 * Endpoints registered under `{namespace}/subscriptions`:
 *   GET  /subscriptions             — active subscriptions list
 *   POST /subscriptions/{id}/cancel — cancel a subscription
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
 * Subscriptions REST route handler.
 */
class Subscriptions extends Base {

	/**
	 * Transient key suffix for the subscriptions list cache.
	 */
	private const SUBS_CACHE_SUFFIX = 'subscriptions_cache';

	/**
	 * Subscriptions cache TTL in seconds (1 hour).
	 */
	private const SUBS_CACHE_TTL = 3600;

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/subscriptions'                              => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_subscriptions' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/subscriptions/(?P<id>[\d]+)/cancel'        => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'cancel_subscription' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'id'     => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'reason' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
			),
		);
	}

	/**
	 * GET /subscriptions — active subscriptions (cached 1 h).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_subscriptions() {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$cache_key = $this->provider->get_cache_prefix() . self::SUBS_CACHE_SUFFIX;
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $this->success_response( $cached );
			}

			$result = $fs->get_api_user_scope()->get( '/subscriptions.json' );

			if ( is_wp_error( $result ) ) {
				return $this->error_response( 'api_error', $result->get_error_message(), 502 );
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				return $this->api_error_response( $result->error );
			}

			$subscriptions = array();
			if ( is_object( $result ) && isset( $result->subscriptions ) && is_array( $result->subscriptions ) ) {
				foreach ( $result->subscriptions as $s ) {
					$subscriptions[] = $this->normalize_subscription( $s );
				}
			}

			$payload = array( 'subscriptions' => $subscriptions );
			set_transient( $cache_key, $payload, self::SUBS_CACHE_TTL );

			return $this->success_response( $payload );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Subscriptions::get_subscriptions' );
		}
	}

	/**
	 * POST /subscriptions/{id}/cancel — cancel a subscription.
	 *
	 * Clears the subscriptions cache on success.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_subscription( WP_REST_Request $request ) {
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
			$reason = sanitize_text_field( (string) $request->get_param( 'reason' ) );

			$path   = '/subscriptions/' . $id . '.json';
			$params = array();
			if ( '' !== $reason ) {
				$params['reason'] = $reason;
			}

			$result = $fs->get_api_user_scope()->call( $path, 'DELETE', $params );

			if ( is_wp_error( $result ) ) {
				return $this->error_response( 'api_error', $result->get_error_message(), 502 );
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				return $this->api_error_response( $result->error );
			}

			delete_transient( $this->provider->get_cache_prefix() . self::SUBS_CACHE_SUFFIX );

			return $this->success_response(
				null,
				esc_html__( 'Subscription cancelled successfully.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Subscriptions::cancel_subscription' );
		}
	}

	/**
	 * Normalize a raw Freemius subscription object.
	 *
	 * @param object $s
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_subscription( object $s ): array {
		return array(
			'id'               => $s->id ?? null,
			'license_id'       => $s->license_id ?? null,
			'plan_id'          => $s->plan_id ?? null,
			'billing_cycle'    => $s->billing_cycle ?? null,
			'amount_per_cycle' => $s->amount_per_cycle ?? null,
			'currency'         => $s->currency ?? 'usd',
			'next_payment'     => $s->next_payment ?? null,
			'gateway'          => $s->gateway ?? null,
			'is_active'        => (bool) ( $s->is_active ?? true ),
			'is_cancelled'     => (bool) ( $s->is_cancelled ?? false ),
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
