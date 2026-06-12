<?php

/**
 * Tests for the abstract Base route class helpers.
 *
 * @package TheWPSquad\FreemiusRest\Tests\Unit
 */

namespace TheWPSquad\FreemiusRest\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TheWPSquad\FreemiusRest\Route\Base;
use TheWPSquad\FreemiusRest\Tests\Stubs\MockProvider;
use WP_Error;
use WP_REST_Response;

/**
 * Concrete subclass exposing protected helpers for testing.
 */
class ConcreteRoute extends Base {
    public function get_routes(): array { return array(); }

    public function exposed_success( $data = null, string $msg = '', int $status = 200 ): WP_REST_Response {
        return $this->success_response( $data, $msg, $status );
    }

    public function exposed_error( string $code, string $message, int $status = 400 ): WP_Error {
        return $this->error_response( $code, $message, $status );
    }
}

/**
 * @covers \TheWPSquad\FreemiusRest\Route\Base
 */
class BaseTest extends TestCase {

    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'esc_html__' )->returnArg( 1 );
        Functions\when( 'register_rest_route' )->justReturn( true );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function make_route(): ConcreteRoute {
        $fs       = Mockery::mock( 'Freemius' );
        $provider = new MockProvider( $fs );

        return new ConcreteRoute( $provider );
    }

    // -------------------------------------------------------------------------
    // get_namespace()
    // -------------------------------------------------------------------------

    public function test_get_namespace_concatenates_slug_and_version(): void {
        $route = $this->make_route();
        $this->assertSame( 'my-plugin/v2', $route->get_namespace() );
    }

    // -------------------------------------------------------------------------
    // success_response()
    // -------------------------------------------------------------------------

    public function test_success_response_has_success_code(): void {
        $route    = $this->make_route();
        $response = $route->exposed_success();

        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $data = $response->get_data();
        $this->assertSame( 'success', $data['code'] );
    }

    public function test_success_response_includes_data_when_provided(): void {
        $route    = $this->make_route();
        $payload  = array( 'foo' => 'bar' );
        $response = $route->exposed_success( $payload );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'data', $data );
        $this->assertSame( $payload, $data['data'] );
    }

    public function test_success_response_omits_data_key_when_null(): void {
        $route    = $this->make_route();
        $response = $route->exposed_success( null );

        $data = $response->get_data();
        $this->assertArrayNotHasKey( 'data', $data );
    }

    public function test_success_response_includes_message_when_provided(): void {
        $route    = $this->make_route();
        $response = $route->exposed_success( null, 'Done.' );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'message', $data );
        $this->assertSame( 'Done.', $data['message'] );
    }

    public function test_success_response_omits_message_key_when_empty(): void {
        $route    = $this->make_route();
        $response = $route->exposed_success();

        $data = $response->get_data();
        $this->assertArrayNotHasKey( 'message', $data );
    }

    public function test_success_response_respects_custom_status_code(): void {
        $route    = $this->make_route();
        $response = $route->exposed_success( null, '', 201 );

        $this->assertSame( 201, $response->get_status() );
    }

    // -------------------------------------------------------------------------
    // error_response()
    // -------------------------------------------------------------------------

    public function test_error_response_returns_wp_error(): void {
        $route = $this->make_route();
        $error = $route->exposed_error( 'not_found', 'Missing.', 404 );

        $this->assertInstanceOf( WP_Error::class, $error );
        $this->assertSame( 'not_found', $error->get_error_code() );
        $this->assertSame( 'Missing.', $error->get_error_message() );
    }

    public function test_error_response_bakes_status_into_data(): void {
        $route = $this->make_route();
        $error = $route->exposed_error( 'forbidden', 'No.', 403 );

        $this->assertSame( 403, $error->get_error_data()['status'] );
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    public function test_register_calls_register_rest_route_for_each_route(): void {
        $fs       = Mockery::mock( 'Freemius' );
        $provider = new MockProvider( $fs );

        $route = new class( $provider ) extends Base {
            public function get_routes(): array {
                return array(
                    '/foo' => array( array( 'methods' => 'GET', 'callback' => '__return_null', 'permission_callback' => '__return_true' ) ),
                    '/bar' => array( array( 'methods' => 'POST', 'callback' => '__return_null', 'permission_callback' => '__return_true' ) ),
                );
            }
        };

        Functions\expect( 'register_rest_route' )
            ->twice()
            ->with( 'my-plugin/v2', Mockery::type( 'string' ), Mockery::type( 'array' ) );

        $route->register();
    }
}
