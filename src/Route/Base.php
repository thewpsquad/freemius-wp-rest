<?php

/**
 * Base REST Route
 *
 * Abstract foundation for all Freemius proxy route classes. Handles namespace
 * construction, route registration, permission checking, and response helpers.
 *
 * @package TheWPSquad\FreemiusRest\Route
 */

namespace TheWPSquad\FreemiusRest\Route;

use TheWPSquad\FreemiusRest\Contracts\FreemiusProvider;
use Throwable;
use WP_Error;
use WP_REST_Response;

/**
 * Abstract base for Freemius REST proxy route handlers.
 */
abstract class Base {

	/**
	 * @var FreemiusProvider
	 */
	protected FreemiusProvider $provider;

	/**
	 * @param FreemiusProvider $provider Plugin-supplied Freemius context.
	 */
	public function __construct( FreemiusProvider $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Return route definitions keyed by path.
	 *
	 * Each value is an array of handler arrays accepted by register_rest_route().
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	abstract public function get_routes(): array;

	/**
	 * REST namespace: `{plugin_slug}/{rest_version}`.
	 */
	public function get_namespace(): string {
		return $this->provider->get_plugin_slug() . '/' . $this->provider->get_rest_version();
	}

	/**
	 * Register all routes defined by get_routes().
	 *
	 * Call this inside a `rest_api_init` callback or equivalent.
	 */
	public function register(): void {
		$namespace = $this->get_namespace();

		foreach ( $this->get_routes() as $route => $handlers ) {
			register_rest_route( $namespace, $route, $handlers );
		}
	}

	/**
	 * Permission callback — enforces get_capability() from the provider.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permissions() {
		if ( ! current_user_can( $this->provider->get_capability() ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permissions to perform this action.', $this->provider->get_text_domain() ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Build a standardised success response envelope.
	 *
	 * @param mixed  $data
	 * @param string $message
	 * @param int    $status
	 *
	 * @return WP_REST_Response
	 */
	protected function success_response( $data = null, string $message = '', int $status = 200 ): WP_REST_Response {
		$response = array( 'code' => 'success' );

		if ( '' !== $message ) {
			$response['message'] = $message;
		}

		if ( null !== $data ) {
			$response['data'] = $data;
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Build a WP_Error with a status code baked into its data.
	 *
	 * @param string               $code
	 * @param string               $message
	 * @param int                  $status
	 * @param array<string, mixed> $extra
	 *
	 * @return WP_Error
	 */
	protected function error_response( string $code, string $message, int $status = 400, array $extra = array() ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array_merge( array( 'status' => $status ), $extra )
		);
	}

	/**
	 * Log a caught exception via the provider and return a 500 error response.
	 *
	 * @param Throwable $exception
	 * @param string    $context   Label for the log entry.
	 * @param string    $message   Optional client-facing message override.
	 * @param int       $status
	 *
	 * @return WP_Error
	 */
	protected function handle_exception( Throwable $exception, string $context, string $message = '', int $status = 500 ): WP_Error {
		$this->provider->log_error( $exception, $context );

		if ( '' === $message ) {
			$message = __( 'An unexpected error occurred.', $this->provider->get_text_domain() );
		}

		return $this->error_response( 'server_error', $message, $status );
	}
}
