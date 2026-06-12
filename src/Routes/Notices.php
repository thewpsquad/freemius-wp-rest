<?php

/**
 * Notices REST Routes
 *
 * Computes actionable notices derived from the current Freemius SDK state
 * (license expiry, trial ending, not connected, etc.) and exposes them as
 * REST resources. Each notice has a stable string ID that can be dismissed
 * per-user via WordPress user meta.
 *
 * Endpoints registered under `{namespace}/notices`:
 *   GET  /notices              — list of active, non-dismissed notices
 *   POST /notices/{id}/dismiss — dismiss a notice for the current user
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
 * Notices REST route handler.
 */
class Notices extends Base {

	/**
	 * User meta key prefix for dismissed notices.
	 */
	private const DISMISSED_META_KEY = '_fs_dismissed_notices';

	/**
	 * Days before license/trial expiry to start showing a warning.
	 */
	private const EXPIRY_WARN_DAYS = 14;

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/notices'                         => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notices' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/notices/(?P<id>[\w-]+)/dismiss'  => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'dismiss_notice' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'id' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			),
		);
	}

	/**
	 * GET /notices — computed, undismissed notices for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_notices() {
		try {
			$fs        = $this->provider->get_fs();
			$dismissed = $this->get_dismissed_ids();
			$notices   = array();

			foreach ( $this->compute_notices( $fs ) as $notice ) {
				if ( ! in_array( $notice['id'], $dismissed, true ) ) {
					$notices[] = $notice;
				}
			}

			return $this->success_response( array(
				'notices' => $notices,
				'total'   => count( $notices ),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Notices::get_notices' );
		}
	}

	/**
	 * POST /notices/{id}/dismiss — mark a notice as dismissed for the current user.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_notice( WP_REST_Request $request ) {
		try {
			$notice_id = sanitize_key( (string) $request->get_param( 'id' ) );
			if ( '' === $notice_id ) {
				return $this->error_response(
					'invalid_notice_id',
					esc_html__( 'Invalid notice ID.', $this->provider->get_text_domain() ),
					400
				);
			}

			$user_id   = get_current_user_id();
			$dismissed = $this->get_dismissed_ids( $user_id );

			if ( ! in_array( $notice_id, $dismissed, true ) ) {
				$dismissed[] = $notice_id;
				update_user_meta(
					$user_id,
					$this->provider->get_cache_prefix() . self::DISMISSED_META_KEY,
					$dismissed
				);
			}

			return $this->success_response(
				array( 'dismissed_id' => $notice_id ),
				esc_html__( 'Notice dismissed.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Notices::dismiss_notice' );
		}
	}

	/**
	 * Compute all applicable notices from the current Freemius SDK state.
	 *
	 * Each notice has: id (string), type (info|warning|error), message (string),
	 * action_url (string|null), action_label (string|null).
	 *
	 * @param object $fs Freemius instance.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function compute_notices( object $fs ): array {
		$notices = array();
		$td      = $this->provider->get_text_domain();

		// Not connected to Freemius.
		if ( ! $fs->is_registered() && ! $fs->is_anonymous() ) {
			$notices[] = array(
				'id'           => 'not_connected',
				'type'         => 'info',
				'message'      => esc_html__( 'Connect your site to Freemius to enable licensing features.', $td ),
				'action_url'   => $fs->get_account_url(),
				'action_label' => esc_html__( 'Connect Now', $td ),
			);

			return $notices; // No further checks without a connection.
		}

		// No active license — suggest upgrade.
		if ( $fs->is_free_plan() && ! $fs->is_trial() ) {
			$notices[] = array(
				'id'           => 'no_license',
				'type'         => 'info',
				'message'      => esc_html__( 'Upgrade to a paid plan to unlock premium features.', $td ),
				'action_url'   => $fs->get_upgrade_url(),
				'action_label' => esc_html__( 'Upgrade', $td ),
			);
		}

		// License expiring soon.
		$license = $fs->_get_license();
		if ( $license && ! $license->is_cancelled && ! empty( $license->expiration ) ) {
			$expires_at = strtotime( $license->expiration );
			if ( $expires_at && $expires_at > time() ) {
				$days_left = (int) ceil( ( $expires_at - time() ) / DAY_IN_SECONDS );
				if ( $days_left <= self::EXPIRY_WARN_DAYS ) {
					$notices[] = array(
						'id'           => 'license_expiring',
						'type'         => 'warning',
						'message'      => sprintf(
							/* translators: %d: days remaining */
							esc_html__( 'Your license expires in %d day(s). Renew to keep premium features active.', $td ),
							$days_left
						),
						'action_url'   => $fs->get_upgrade_url(),
						'action_label' => esc_html__( 'Renew License', $td ),
					);
				}
			} elseif ( $expires_at && $expires_at < time() ) {
				$notices[] = array(
					'id'           => 'license_expired',
					'type'         => 'error',
					'message'      => esc_html__( 'Your license has expired. Premium features are disabled.', $td ),
					'action_url'   => $fs->get_upgrade_url(),
					'action_label' => esc_html__( 'Renew License', $td ),
				);
			}
		}

		// Trial ending soon.
		if ( $fs->is_trial() ) {
			$site = $fs->get_site();
			if ( $site && ! empty( $site->trial_ends ) ) {
				$end_ts    = strtotime( $site->trial_ends );
				$days_left = $end_ts ? max( 0, (int) ceil( ( $end_ts - time() ) / DAY_IN_SECONDS ) ) : 0;

				if ( $days_left <= self::EXPIRY_WARN_DAYS ) {
					$notices[] = array(
						'id'           => 'trial_ending',
						'type'         => 'warning',
						'message'      => sprintf(
							/* translators: %d: days remaining */
							esc_html__( 'Your trial ends in %d day(s). Upgrade now to avoid interruption.', $td ),
							$days_left
						),
						'action_url'   => $fs->get_upgrade_url(),
						'action_label' => esc_html__( 'Upgrade Now', $td ),
					);
				}
			}
		}

		// Pending email verification.
		if ( $fs->is_pending_activation() ) {
			$notices[] = array(
				'id'           => 'pending_activation',
				'type'         => 'warning',
				'message'      => esc_html__( 'Please verify your email address to complete the Freemius connection.', $td ),
				'action_url'   => $fs->get_account_url(),
				'action_label' => esc_html__( 'Go to Account', $td ),
			);
		}

		return $notices;
	}

	/**
	 * Return the list of notice IDs dismissed by the given user (or current user).
	 *
	 * @param int $user_id 0 = use get_current_user_id().
	 *
	 * @return string[]
	 */
	private function get_dismissed_ids( int $user_id = 0 ): array {
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$meta = get_user_meta(
			$user_id,
			$this->provider->get_cache_prefix() . self::DISMISSED_META_KEY,
			true
		);

		return is_array( $meta ) ? $meta : array();
	}
}
