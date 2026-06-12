<?php

/**
 * Pricing REST Routes
 *
 * Provides a live REST endpoint that fetches plan and pricing data from the
 * Freemius API with no transient caching — always returns the current price.
 *
 * Endpoints registered under `{namespace}/pricing`:
 *   GET /pricing — live plans + pricing tiers
 *
 * @package TheWPSquad\FreemiusRest\Routes
 */

namespace TheWPSquad\FreemiusRest\Routes;

use TheWPSquad\FreemiusRest\Route\Base;
use Throwable;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Pricing REST route handler.
 */
class Pricing extends Base {

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/pricing' => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pricing' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
		);
	}

	/**
	 * GET /pricing — live Freemius plans + pricing tiers (no cache).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_pricing() {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$result = $fs->get_api_plugin_scope()->get( '/plans.json?enriched=true' );

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

			$raw_plans = is_object( $result ) && isset( $result->plans ) && is_array( $result->plans )
				? $result->plans
				: array();

			$plans = array_map( static function ( $plan ): array {
				$features = array();
				if ( isset( $plan->features ) && is_array( $plan->features ) ) {
					foreach ( $plan->features as $feature ) {
						$features[] = array(
							'id'          => $feature->id ?? null,
							'title'       => $feature->title ?? '',
							'description' => $feature->description ?? '',
						);
					}
				}

				$pricing = array();
				if ( isset( $plan->pricing ) && is_array( $plan->pricing ) ) {
					foreach ( $plan->pricing as $tier ) {
						$pricing[] = array(
							'licenses'       => $tier->licenses ?? 1,
							'monthly_price'  => $tier->monthly_price ?? null,
							'annual_price'   => $tier->annual_price ?? null,
							'lifetime_price' => $tier->lifetime_price ?? null,
							'currency'       => $tier->currency ?? 'usd',
						);
					}
				}

				return array(
					'id'           => $plan->id ?? null,
					'name'         => $plan->name ?? '',
					'title'        => $plan->title ?? '',
					'description'  => $plan->description ?? '',
					'trial_period' => $plan->trial_period ?? 0,
					'is_featured'  => (bool) ( $plan->is_featured ?? false ),
					'features'     => $features,
					'pricing'      => $pricing,
				);
			}, $raw_plans );

			return $this->success_response( array( 'plans' => $plans ) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Pricing::get_pricing' );
		}
	}
}
