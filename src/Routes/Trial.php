<?php

/**
 * Trial REST Routes
 *
 * Endpoints for reading trial state and starting a free trial via the
 * Freemius user-scope API.
 *
 * Endpoints registered under `{namespace}/trial`:
 *   GET  /trial        — trial status, eligibility, days remaining, trial URL
 *   POST /trial/start  — start a trial (plan_id required)
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
 * Trial REST route handler.
 */
class Trial extends Base {

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/trial'       => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_trial' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/trial/start' => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_trial' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'plan_id' => array(
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
	 * GET /trial — trial status, eligibility, and trial start URL.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_trial() {
		try {
			$fs   = $this->provider->get_fs();
			$plan = $fs->get_plan();

			$trial_days     = 0;
			$trial_end_date = null;
			$days_remaining = null;

			if ( $fs->is_trial() ) {
				$site = $fs->get_site();
				if ( $site && ! empty( $site->trial_ends ) ) {
					$end_ts         = strtotime( $site->trial_ends );
					$trial_end_date = $site->trial_ends;
					$days_remaining = max( 0, (int) ceil( ( $end_ts - time() ) / DAY_IN_SECONDS ) );
				}
			}

			if ( $plan && isset( $plan->trial_period ) ) {
				$trial_days = (int) $plan->trial_period;
			}

			return $this->success_response( array(
				'is_trial'            => (bool) $fs->is_trial(),
				'is_trial_used'       => (bool) $fs->is_trial_utilized(),
				'in_trial_promotion'  => (bool) $fs->is_in_trial_promotion(),
				'trial_days'          => $trial_days,
				'days_remaining'      => $days_remaining,
				'trial_end_date'      => $trial_end_date,
				'trial_url'           => $fs->get_trial_url(),
				'is_registered'       => (bool) $fs->is_registered(),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Trial::get_trial' );
		}
	}

	/**
	 * POST /trial/start — start a trial for a given plan.
	 *
	 * Requires the user to be registered (opted in). Returns the trial URL
	 * if the plan has a browser-based trial flow; otherwise starts via API.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_trial( WP_REST_Request $request ) {
		try {
			$fs = $this->provider->get_fs();

			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Must be connected to Freemius to start a trial.', $this->provider->get_text_domain() ),
					403
				);
			}

			if ( $fs->is_trial() ) {
				return $this->error_response(
					'already_in_trial',
					esc_html__( 'A trial is already active.', $this->provider->get_text_domain() ),
					409
				);
			}

			if ( $fs->is_trial_utilized() ) {
				return $this->error_response(
					'trial_already_used',
					esc_html__( 'Trial has already been used for this site.', $this->provider->get_text_domain() ),
					409
				);
			}

			$plan_id = absint( $request->get_param( 'plan_id' ) );
			$result  = $fs->get_api_user_scope()->call(
				'/trials.json',
				'POST',
				array( 'plan_id' => $plan_id )
			);

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

			return $this->success_response(
				array(
					'trial_url'  => $fs->get_trial_url(),
					'is_trial'   => (bool) $fs->is_trial(),
					'plan_id'    => $plan_id,
				),
				esc_html__( 'Trial started successfully.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Trial::start_trial' );
		}
	}
}
