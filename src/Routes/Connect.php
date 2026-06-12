<?php

/**
 * Connect REST Routes
 *
 * Endpoints for reading and managing the Freemius opt-in/connection state.
 * The POST endpoint returns the opt-in URL for the React UI to redirect to;
 * the full opt-in flow is browser-based and cannot be completed via REST alone.
 *
 * Endpoints registered under `{namespace}/connect`:
 *   GET    /connect       — connection status, flags, and opt-in URL
 *   POST   /connect       — returns opt-in redirect URL (use to initiate re-connect)
 *   POST   /connect/skip  — skip opt-in (set anonymous mode)
 *   DELETE /connect       — disconnect this site from Freemius
 *
 * @package TheWPSquad\FreemiusRest\Routes
 */

namespace TheWPSquad\FreemiusRest\Routes;

use TheWPSquad\FreemiusRest\Route\Base;
use ReflectionMethod;
use Throwable;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Connect REST route handler.
 */
class Connect extends Base {

	/**
	 * {@inheritdoc}
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_routes(): array {
		return array(
			'/connect'      => array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'disconnect' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/connect/optin' => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_optin_url' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
			'/connect/skip' => array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'skip_optin' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			),
		);
	}

	/**
	 * GET /connect — full connection status and flags.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status() {
		try {
			$fs   = $this->provider->get_fs();
			$user = $fs->get_user();
			$site = $fs->get_site();

			return $this->success_response( array(
				'is_registered'          => (bool) $fs->is_registered(),
				'is_registered_strict'   => (bool) $fs->is_registered( true ),
				'is_anonymous'           => (bool) $fs->is_anonymous(),
				'is_pending_activation'  => (bool) $fs->is_pending_activation(),
				'is_tracking_allowed'    => (bool) $fs->is_tracking_allowed(),
				'user'                   => $user ? array(
					'id'          => $user->id,
					'email'       => $user->email,
					'first'       => $user->first,
					'last'        => $user->last,
					'is_verified' => (bool) $user->is_verified,
				) : null,
				'site'                   => $site ? array(
					'id'              => $site->id,
					'url'             => $site->url,
					'is_disconnected' => (bool) $site->is_disconnected,
				) : null,
				'account_url'            => $fs->get_account_url(),
				'upgrade_url'            => $fs->get_upgrade_url(),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Connect::get_status' );
		}
	}

	/**
	 * POST /connect/optin — return the opt-in screen URL.
	 *
	 * The React UI should redirect the browser to this URL to trigger the
	 * Freemius opt-in dialog. The actual connection handshake happens in the
	 * browser; there is no purely server-side opt-in path.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_optin_url() {
		try {
			$fs = $this->provider->get_fs();

			if ( $fs->is_registered() ) {
				return $this->success_response( array(
					'already_connected' => true,
					'account_url'       => $fs->get_account_url(),
				) );
			}

			$fs->connect_again();

			return $this->success_response( array(
				'already_connected' => false,
				'optin_url'         => $fs->get_account_url(),
			) );
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Connect::get_optin_url' );
		}
	}

	/**
	 * POST /connect/skip — skip opt-in and set anonymous mode.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function skip_optin() {
		try {
			$fs = $this->provider->get_fs();

			if ( $fs->is_registered() ) {
				return $this->error_response(
					'already_connected',
					esc_html__( 'Already connected to Freemius. Disconnect first to skip.', $this->provider->get_text_domain() ),
					409
				);
			}

			$skip = new ReflectionMethod( $fs, 'skip_connection' );
			$skip->setAccessible( true );
			$skip->invoke( $fs, null, false );

			return $this->success_response(
				array( 'is_anonymous' => (bool) $fs->is_anonymous() ),
				esc_html__( 'Opt-in skipped. Running in anonymous mode.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Connect::skip_optin' );
		}
	}

	/**
	 * DELETE /connect — disconnect this site from Freemius.
	 *
	 * Uses the plugin-scope API to remove the current install. This is
	 * irreversible without re-opting in.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function disconnect() {
		try {
			$fs = $this->provider->get_fs();

			if ( ! $fs->is_registered() ) {
				return $this->error_response(
					'not_connected',
					esc_html__( 'Not connected to Freemius.', $this->provider->get_text_domain() ),
					403
				);
			}

			$site = $fs->get_site();
			if ( ! $site || empty( $site->id ) ) {
				return $this->error_response(
					'site_not_found',
					esc_html__( 'Site installation record not found.', $this->provider->get_text_domain() ),
					404
				);
			}

			$result = $fs->get_api_user_scope()->call(
				'/installs/' . $site->id . '.json',
				'DELETE'
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

			// Clear local SDK state after successful API disconnect.
			try {
				$clear = new ReflectionMethod( $fs, '_delete_site_tracking' );
				$clear->setAccessible( true );
				$clear->invoke( $fs );
			} catch ( Throwable $_ ) {
				// Best-effort — local state cleanup is non-critical.
			}

			return $this->success_response(
				null,
				esc_html__( 'Site disconnected from Freemius successfully.', $this->provider->get_text_domain() )
			);
		} catch ( Throwable $e ) {
			return $this->handle_exception( $e, 'Connect::disconnect' );
		}
	}
}
