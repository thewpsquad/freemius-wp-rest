<?php

/**
 * Account REST Routes
 *
 * Provides REST endpoints for reading and mutating Freemius account data:
 * license status, plan info, user details, activation/deactivation, and
 * usage-tracking consent.
 *
 * Endpoints registered under `{namespace}/account`:
 *   GET    /account                           — merged user + site + plan + license + flags
 *   GET    /account/license                   — license detail + subscription + upgrade URL
 *   POST   /account/license/activate          — activate a license key
 *   POST   /account/license/deactivate/intent — step 1: generate confirmation token
 *   POST   /account/license/deactivate        — step 2: execute deactivation
 *   POST   /account/tracking                  — toggle Freemius usage tracking
 *
 * @package TheWPSquad\FreemiusRest\Routes
 */

namespace TheWPSquad\FreemiusRest\Routes;

use TheWPSquad\FreemiusRest\Route\Base;
use ReflectionMethod;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Account REST route handler.
 */
class Account extends Base {

	/**
	 * Transient key suffix for deactivation intent tokens.
	 */
	private const DEACTIVATE_INTENT_SUFFIX = 'deactivate_intent_';

	/**
	 * Deactivation intent TTL in seconds (5 minutes).
	 */
	private const DEACTIVATE_INTENT_TTL = 300;

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/account'                           => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_account' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/account/license'                   => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_license' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/account/license/activate'          => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_license' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'license_key' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			),
			'/account/license/deactivate/intent' => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_intent' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/account/license/deactivate'        => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_license' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'token' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			),
			'/account/tracking'                  => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_tracking' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'enabled' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			),
		);
	}

	/**
	 * GET /account — merged user + site + plan + license + flags.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_account() {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$user    = $fs->get_user();
			$site    = $fs->get_site();
			$plan    = $fs->get_plan();
			$license = $fs->_get_license();

			return $this->success_response( array(
				'user'    => $user ? array(
					'id'          => $user->id,
					'email'       => $user->email,
					'first'       => $user->first,
					'last'        => $user->last,
					'is_verified' => (bool) $user->is_verified,
				) : null,
				'site'    => $site ? array(
					'id'              => $site->id,
					'url'             => $site->url,
					'version'         => $site->version,
					'is_premium'      => (bool) $site->is_premium,
					'is_disconnected' => (bool) $site->is_disconnected,
				) : null,
				'plan'    => $plan ? array(
					'id'           => $plan->id,
					'name'         => $plan->name,
					'title'        => $plan->title,
					'trial_period' => $plan->trial_period,
					'license_type' => $plan->license_type,
				) : null,
				'license' => $license ? array(
					'id'              => $license->id,
					'quota'           => $license->quota,
					'activated'       => $license->activated,
					'expiration'      => $license->expiration,
					'is_whitelabeled' => (bool) $license->is_whitelabeled,
					'is_cancelled'    => (bool) $license->is_cancelled,
				) : null,
				'flags'   => array(
					'is_registered'      => (bool) $fs->is_registered(),
					'is_paying'          => (bool) $fs->is_paying(),
					'is_trial'           => (bool) $fs->is_trial(),
					'is_free_plan'       => (bool) $fs->is_free_plan(),
					'can_use_premium'    => (bool) $fs->can_use_premium_code(),
					'has_active_license' => (bool) $fs->has_active_license(),
				),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Account::get_account' );
		}
	}

	/**
	 * GET /account/license — license detail + subscription + upgrade URL.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_license() {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$license      = $fs->_get_license();
			$plan         = $fs->get_plan();
			$subscription = $license ? $fs->_get_subscription( $license->id ) : null;

			$masked_key = null;
			if ( $license && ! empty( $license->secret_key ) ) {
				$key_str    = (string) $license->secret_key;
				$masked_key = '••••••••' . ( strlen( $key_str ) > 4 ? substr( $key_str, -4 ) : '****' );
			}

			return $this->success_response( array(
				'license'      => $license ? array(
					'id'           => $license->id,
					'key'          => $masked_key,
					'quota'        => $license->quota,
					'activated'    => $license->activated,
					'expiration'   => $license->expiration,
					'is_cancelled' => (bool) $license->is_cancelled,
				) : null,
				'subscription' => $subscription ? array(
					'billing_cycle'    => $subscription->billing_cycle,
					'amount_per_cycle' => $subscription->amount_per_cycle,
					'currency'         => $subscription->currency ?? 'usd',
					'next_payment'     => $subscription->next_payment ?? null,
					'gateway'          => $subscription->gateway,
				) : null,
				'plan'         => $plan ? array(
					'name'  => $plan->name,
					'title' => $plan->title,
				) : null,
				'upgrade_url'  => $fs->get_upgrade_url(),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Account::get_license' );
		}
	}

	/**
	 * POST /account/license/activate — activate a license key.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_license( WP_REST_Request $request ) {
		try {
			$license_key = trim( (string) $request->get_param( 'license_key' ) );
			if ( '' === $license_key ) {
				return $this->error_response(
					'missing_license_key',
					esc_html__( 'A license key is required.', $this->provider->get_text_domain() ),
					400
				);
			}

			$fs      = $this->provider->get_fs();
			$message = '';
			$success = false;

			if ( $fs->is_registered() ) {
				$activate = new ReflectionMethod( $fs, 'activate_license' );
				$activate->setAccessible( true );
				$result = $activate->invoke( $fs, $license_key );

				$success = is_array( $result ) && true === ( $result['success'] ?? false );
				if ( ! $success && is_array( $result ) && ! empty( $result['error'] ) ) {
					$message = is_string( $result['error'] ) ? $result['error'] : '';
				}
			} else {
				$fs->opt_in( false, false, false, $license_key, false, false, false, null, array(), false );
				$success = $fs->is_paying() || $fs->can_use_premium_code( true );
			}

			if ( '' === $message && ! $success ) {
				$message = esc_html__( 'Could not activate the license. Check the key and try again.', $this->provider->get_text_domain() );
			}

			return $this->success_response( array(
				'success' => $success,
				'is_pro'  => (bool) $fs->can_use_premium_code( true ),
				'message' => $message,
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Account::activate_license' );
		}
	}

	/**
	 * POST /account/license/deactivate/intent — step 1: issue a confirmation token.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_intent() {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$license = $fs->_get_license();
			if ( ! $license || ! $fs->has_active_license() ) {
				return $this->error_response(
					'no_active_license',
					esc_html__( 'No active license to deactivate.', $this->provider->get_text_domain() ),
					403
				);
			}

			$site      = $fs->get_site();
			$site_id   = $site ? $site->id : 0;
			$timestamp = time();
			$token     = hash_hmac( 'sha256', "{$license->id}:{$site_id}:{$timestamp}", AUTH_KEY );

			set_transient(
				$this->intent_key( $site_id ),
				array(
					'token'      => $token,
					'license_id' => $license->id,
					'created_at' => $timestamp,
				),
				self::DEACTIVATE_INTENT_TTL
			);

			return $this->success_response( array(
				'token'      => $token,
				'summary'    => sprintf(
					/* translators: 1: activations used, 2: total quota */
					esc_html__( 'This will deactivate the license on this site (%1$d of %2$d activations used). The license key will remain valid for use elsewhere.', $this->provider->get_text_domain() ),
					absint( $license->activated ),
					absint( $license->quota )
				),
				'expires_at' => gmdate( 'c', $timestamp + self::DEACTIVATE_INTENT_TTL ),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Account::deactivate_intent' );
		}
	}

	/**
	 * POST /account/license/deactivate — step 2: execute with confirmed token.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate_license( WP_REST_Request $request ) {
		try {
			$fs = $this->provider->get_fs();
			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$token   = trim( (string) $request->get_param( 'token' ) );
			$site    = $fs->get_site();
			$site_id = $site ? $site->id : 0;
			$stored  = get_transient( $this->intent_key( $site_id ) );

			if ( false === $stored || ! is_array( $stored ) || ! isset( $stored['token'] ) ) {
				return $this->error_response(
					'token_expired',
					esc_html__( 'Deactivation token has expired. Please request a new one.', $this->provider->get_text_domain() ),
					409
				);
			}

			if ( ! hash_equals( $stored['token'], $token ) ) {
				return $this->error_response(
					'token_invalid',
					esc_html__( 'Invalid deactivation token.', $this->provider->get_text_domain() ),
					409
				);
			}

			$current_license = $fs->_get_license();
			if ( ! $current_license || (int) $stored['license_id'] !== (int) $current_license->id ) {
				delete_transient( $this->intent_key( $site_id ) );

				return $this->error_response(
					'license_mismatch',
					esc_html__( 'License changed since deactivation was initiated. Please request a new token.', $this->provider->get_text_domain() ),
					409
				);
			}

			$deactivate = new ReflectionMethod( $fs, '_deactivate_license' );
			$deactivate->setAccessible( true );
			$deactivate->invoke( $fs );

			delete_transient( $this->intent_key( $site_id ) );

			return $this->success_response(
				null,
				esc_html__( 'License deactivated successfully.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Account::deactivate_license' );
		}
	}

	/**
	 * POST /account/tracking — toggle Freemius usage tracking.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_tracking( WP_REST_Request $request ) {
		try {
			$enabled = (bool) $request->get_param( 'enabled' );
			$fs      = $this->provider->get_fs();

			if ( $fs->is_registered( true ) ) {
				$toggle = new ReflectionMethod( $fs, 'toggle_site_tracking' );
				$toggle->setAccessible( true );
				$toggle->invoke( $fs, $enabled );
			}

			return $this->success_response( array(
				'allowed'    => (bool) $fs->is_tracking_allowed(),
				'can_toggle' => (bool) $fs->is_registered( true ),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Account::set_tracking' );
		}
	}

	/**
	 * Build the transient key for a deactivation intent token.
	 */
	private function intent_key( int $site_id ): string {
		return $this->provider->get_cache_prefix() . self::DEACTIVATE_INTENT_SUFFIX . $site_id;
	}
}
