<?php

/**
 * Affiliate REST Routes
 *
 * Endpoints for reading affiliate status (cached 12 h) and submitting
 * affiliate applications via the Freemius user-scope API.
 *
 * Endpoints registered under `{namespace}/affiliate`:
 *   GET  /affiliate       — affiliate status + programme terms (cached)
 *   POST /affiliate/apply — submit an affiliate application
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
 * Affiliate REST route handler.
 */
class Affiliate extends Base {

	/**
	 * Transient key suffix for the affiliate data cache.
	 */
	private const AFFILIATE_CACHE_SUFFIX = 'affiliate_cache';

	/**
	 * Affiliate cache TTL in seconds (12 hours).
	 */
	private const AFFILIATE_CACHE_TTL = 43200;

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/affiliate'       => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_affiliate' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/affiliate/apply' => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_affiliate' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'promotion_methods'  => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'string' ),
						),
						'website'            => array(
							'type'    => 'string',
							'default' => '',
						),
						'additional_domains' => array(
							'type'    => 'array',
							'default' => array(),
							'items'   => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
	}

	/**
	 * GET /affiliate — affiliate status + programme terms (cached 12 h).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_affiliate() {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$cache_key = $this->provider->get_cache_prefix() . self::AFFILIATE_CACHE_SUFFIX;
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $this->success_response( $cached );
			}

			$affiliate = $fs->get_affiliate();
			$terms     = $fs->get_affiliate_terms();

			$affiliate_data = null;
			if ( $affiliate && is_object( $affiliate ) ) {
				$affiliate_data = array(
					'status'          => $affiliate->status ?? '',
					'commission'      => $affiliate->commission ?? 0,
					'commission_type' => $affiliate->commission_type ?? 'percentage',
					'cookie_days'     => $affiliate->cookie_days ?? 30,
					'default_url'     => $affiliate->default_url ?? '',
					'is_active'       => (bool) ( $affiliate->is_active ?? false ),
					'paypal_email'    => $affiliate->paypal_email ?? '',
				);
			}

			$terms_data = null;
			if ( $terms && is_object( $terms ) ) {
				$terms_data = array(
					'commission'         => $terms->commission ?? 0,
					'cookie_days'        => $terms->cookie_days ?? 30,
					'reward_type'        => $terms->reward_type ?? 'cash',
					'install_commission' => $terms->install_commission ?? 0,
				);
			}

			$payload = array(
				'affiliate' => $affiliate_data,
				'terms'     => $terms_data,
				'cached_at' => gmdate( 'c' ),
			);

			set_transient( $cache_key, $payload, self::AFFILIATE_CACHE_TTL );

			return $this->success_response( $payload );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Affiliate::get_affiliate' );
		}
	}

	/**
	 * POST /affiliate/apply — submit affiliate application. Clears cache on success.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_affiliate( WP_REST_Request $request ) {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$promotion_methods = $request->get_param( 'promotion_methods' );
			if ( empty( $promotion_methods ) || ! is_array( $promotion_methods ) ) {
				return $this->error_response(
					'missing_promotion_methods',
					esc_html__( 'At least one promotion method is required.', $this->provider->get_text_domain() ),
					400
				);
			}

			$payload = array(
				'promotion_methods'  => array_map( 'strval', $promotion_methods ),
				'website'            => (string) ( $request->get_param( 'website' ) ?? '' ),
				'additional_domains' => (array) ( $request->get_param( 'additional_domains' ) ?? array() ),
			);

			$result = $fs->get_api_user_scope()->post( '/affiliates.json', $payload );

			if ( is_wp_error( $result ) ) {
				return $this->error_response( 'api_error', $result->get_error_message(), 502 );
			}

			if ( is_object( $result ) && isset( $result->error ) ) {
				$msg = is_string( $result->error )
					? $result->error
					: ( is_object( $result->error ) && isset( $result->error->message )
						? (string) $result->error->message
						: '' );

				return $this->error_response( 'api_error', $msg, 502 );
			}

			delete_transient( $this->provider->get_cache_prefix() . self::AFFILIATE_CACHE_SUFFIX );

			return $this->success_response(
				array(
					'success'      => true,
					'affiliate_id' => is_object( $result ) ? ( $result->id ?? null ) : null,
				),
				esc_html__( 'Affiliate application submitted successfully.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Affiliate::apply_affiliate' );
		}
	}
}
