<?php

/**
 * Tests for the Account route handler.
 *
 * @package TheWPSquad\FreemiusRest\Tests\Unit\Routes
 */

namespace TheWPSquad\FreemiusRest\Tests\Unit\Routes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TheWPSquad\FreemiusRest\Routes\Account;
use TheWPSquad\FreemiusRest\Tests\Unit\Support\MockProvider;
use WP_Error;

/**
 * @covers \TheWPSquad\FreemiusRest\Routes\Account
 */
class AccountRoutesTest extends TestCase {

    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'esc_html__' )->returnArg( 1 );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function make_account( Mockery\MockInterface $fs ): Account {
        return new Account( new MockProvider( $fs ) );
    }

    // -------------------------------------------------------------------------
    // Route structure
    // -------------------------------------------------------------------------

    public function test_get_routes_returns_all_six_endpoints(): void {
        $fs      = Mockery::mock( 'Freemius' );
        $account = $this->make_account( $fs );
        $routes  = $account->get_routes();

        $this->assertArrayHasKey( '/account', $routes );
        $this->assertArrayHasKey( '/account/license', $routes );
        $this->assertArrayHasKey( '/account/license/activate', $routes );
        $this->assertArrayHasKey( '/account/license/deactivate/intent', $routes );
        $this->assertArrayHasKey( '/account/license/deactivate', $routes );
        $this->assertArrayHasKey( '/account/tracking', $routes );
        $this->assertCount( 6, $routes );
    }

    public function test_all_routes_have_permission_callback(): void {
        $fs      = Mockery::mock( 'Freemius' );
        $account = $this->make_account( $fs );

        foreach ( $account->get_routes() as $path => $handlers ) {
            foreach ( $handlers as $handler ) {
                $this->assertArrayHasKey(
                    'permission_callback',
                    $handler,
                    "Route {$path} is missing permission_callback"
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // get_account() — not connected
    // -------------------------------------------------------------------------

    public function test_get_account_returns_403_when_not_registered(): void {
        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->once()->andReturn( false );

        $account  = $this->make_account( $fs );
        $response = $account->get_account();

        $this->assertInstanceOf( WP_Error::class, $response );
        $this->assertSame( 'not_connected', $response->get_error_code() );
        $this->assertSame( 403, $response->get_error_data()['status'] );
    }

    // -------------------------------------------------------------------------
    // get_account() — connected
    // -------------------------------------------------------------------------

    public function test_get_account_returns_success_when_registered(): void {
        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'get_user' )->andReturn( null );
        $fs->shouldReceive( 'get_site' )->andReturn( null );
        $fs->shouldReceive( 'get_plan' )->andReturn( null );
        $fs->shouldReceive( '_get_license' )->andReturn( null );
        $fs->shouldReceive( 'is_paying' )->andReturn( false );
        $fs->shouldReceive( 'is_trial' )->andReturn( false );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( true );
        $fs->shouldReceive( 'can_use_premium_code' )->andReturn( false );
        $fs->shouldReceive( 'has_active_license' )->andReturn( false );

        $account  = $this->make_account( $fs );
        $response = $account->get_account();

        $this->assertNotInstanceOf( WP_Error::class, $response );
        $data = $response->get_data();
        $this->assertSame( 'success', $data['code'] );
        $this->assertArrayHasKey( 'flags', $data['data'] );
    }

    public function test_get_account_flags_are_booleans(): void {
        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'get_user' )->andReturn( null );
        $fs->shouldReceive( 'get_site' )->andReturn( null );
        $fs->shouldReceive( 'get_plan' )->andReturn( null );
        $fs->shouldReceive( '_get_license' )->andReturn( null );
        $fs->shouldReceive( 'is_paying' )->andReturn( 1 );  // truthy int
        $fs->shouldReceive( 'is_trial' )->andReturn( 0 );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( true );
        $fs->shouldReceive( 'can_use_premium_code' )->andReturn( false );
        $fs->shouldReceive( 'has_active_license' )->andReturn( false );

        $account  = $this->make_account( $fs );
        $response = $account->get_account();

        $flags = $response->get_data()['data']['flags'];
        foreach ( $flags as $key => $value ) {
            $this->assertIsBool( $value, "Flag '{$key}' must be strictly bool" );
        }
    }

    // -------------------------------------------------------------------------
    // activate_license() — validation
    // -------------------------------------------------------------------------

    public function test_activate_license_returns_400_for_empty_key(): void {
        $fs = Mockery::mock( 'Freemius' );

        Functions\when( 'trim' )->alias( 'trim' );

        $account = $this->make_account( $fs );
        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_param' )
            ->with( 'license_key' )
            ->andReturn( '   ' );

        $response = $account->activate_license( $request );

        $this->assertInstanceOf( WP_Error::class, $response );
        $this->assertSame( 'missing_license_key', $response->get_error_code() );
        $this->assertSame( 400, $response->get_error_data()['status'] );
    }
}
