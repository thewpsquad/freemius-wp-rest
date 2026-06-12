<?php

/**
 * Tests for the Notices route handler.
 *
 * @package TheWPSquad\FreemiusRest\Tests\Unit\Routes
 */

namespace TheWPSquad\FreemiusRest\Tests\Unit\Routes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use TheWPSquad\FreemiusRest\Routes\Notices;
use TheWPSquad\FreemiusRest\Tests\Unit\Support\MockProvider;
use WP_Error;

/**
 * @covers \TheWPSquad\FreemiusRest\Routes\Notices
 */
class NoticesRoutesTest extends TestCase {

    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'esc_html__' )->returnArg( 1 );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_user_meta' )->justReturn( array() );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function make_notices( Mockery\MockInterface $fs ): Notices {
        return new Notices( new MockProvider( $fs ) );
    }

    // -------------------------------------------------------------------------
    // Route structure
    // -------------------------------------------------------------------------

    public function test_get_routes_has_notices_and_dismiss_endpoints(): void {
        $fs      = Mockery::mock( 'Freemius' );
        $notices = $this->make_notices( $fs );
        $routes  = $notices->get_routes();

        $this->assertArrayHasKey( '/notices', $routes );
        $this->assertArrayHasKey( '/notices/(?P<id>[\w-]+)/dismiss', $routes );
    }

    // -------------------------------------------------------------------------
    // get_notices() — not connected, not anonymous → not_connected notice
    // -------------------------------------------------------------------------

    public function test_get_notices_returns_not_connected_notice_when_unregistered(): void {
        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( false );
        $fs->shouldReceive( 'is_anonymous' )->andReturn( false );
        $fs->shouldReceive( 'get_account_url' )->andReturn( 'https://example.com/account' );

        $notices  = $this->make_notices( $fs );
        $response = $notices->get_notices();

        $this->assertNotInstanceOf( WP_Error::class, $response );
        $data    = $response->get_data()['data'];
        $ids     = array_column( $data['notices'], 'id' );
        $this->assertContains( 'not_connected', $ids );
    }

    // -------------------------------------------------------------------------
    // get_notices() — free plan → no_license notice
    // -------------------------------------------------------------------------

    public function test_get_notices_returns_no_license_notice_on_free_plan(): void {
        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'is_anonymous' )->andReturn( false );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( true );
        $fs->shouldReceive( 'is_trial' )->andReturn( false );
        $fs->shouldReceive( '_get_license' )->andReturn( null );
        $fs->shouldReceive( 'is_pending_activation' )->andReturn( false );
        $fs->shouldReceive( 'get_upgrade_url' )->andReturn( 'https://example.com/upgrade' );

        $notices  = $this->make_notices( $fs );
        $response = $notices->get_notices();

        $data = $response->get_data()['data'];
        $ids  = array_column( $data['notices'], 'id' );
        $this->assertContains( 'no_license', $ids );
    }

    // -------------------------------------------------------------------------
    // get_notices() — license expiring soon
    // -------------------------------------------------------------------------

    public function test_get_notices_returns_license_expiring_notice_within_14_days(): void {
        $license              = new \stdClass();
        $license->is_cancelled = false;
        $license->expiration  = gmdate( 'Y-m-d H:i:s', time() + ( 7 * DAY_IN_SECONDS ) ); // 7 days

        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'is_anonymous' )->andReturn( false );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( false );
        $fs->shouldReceive( 'is_trial' )->andReturn( false );
        $fs->shouldReceive( '_get_license' )->andReturn( $license );
        $fs->shouldReceive( 'is_pending_activation' )->andReturn( false );
        $fs->shouldReceive( 'get_upgrade_url' )->andReturn( '' );

        $notices  = $this->make_notices( $fs );
        $response = $notices->get_notices();

        $data = $response->get_data()['data'];
        $ids  = array_column( $data['notices'], 'id' );
        $this->assertContains( 'license_expiring', $ids );
        $this->assertNotContains( 'license_expired', $ids );
    }

    // -------------------------------------------------------------------------
    // get_notices() — expired license
    // -------------------------------------------------------------------------

    public function test_get_notices_returns_license_expired_notice_when_past_expiry(): void {
        $license               = new \stdClass();
        $license->is_cancelled = false;
        $license->expiration   = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'is_anonymous' )->andReturn( false );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( false );
        $fs->shouldReceive( 'is_trial' )->andReturn( false );
        $fs->shouldReceive( '_get_license' )->andReturn( $license );
        $fs->shouldReceive( 'is_pending_activation' )->andReturn( false );
        $fs->shouldReceive( 'get_upgrade_url' )->andReturn( '' );

        $notices  = $this->make_notices( $fs );
        $response = $notices->get_notices();

        $data = $response->get_data()['data'];
        $ids  = array_column( $data['notices'], 'id' );
        $this->assertContains( 'license_expired', $ids );
        $this->assertNotContains( 'license_expiring', $ids );
    }

    // -------------------------------------------------------------------------
    // get_notices() — pending activation
    // -------------------------------------------------------------------------

    public function test_get_notices_returns_pending_activation_notice(): void {
        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'is_anonymous' )->andReturn( false );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( true );
        $fs->shouldReceive( 'is_trial' )->andReturn( false );
        $fs->shouldReceive( '_get_license' )->andReturn( null );
        $fs->shouldReceive( 'is_pending_activation' )->andReturn( true );
        $fs->shouldReceive( 'get_upgrade_url' )->andReturn( '' );
        $fs->shouldReceive( 'get_account_url' )->andReturn( '' );

        $notices  = $this->make_notices( $fs );
        $response = $notices->get_notices();

        $data = $response->get_data()['data'];
        $ids  = array_column( $data['notices'], 'id' );
        $this->assertContains( 'pending_activation', $ids );
    }

    // -------------------------------------------------------------------------
    // get_notices() — all clear (no notices)
    // -------------------------------------------------------------------------

    public function test_get_notices_returns_empty_when_fully_licensed(): void {
        $license               = new \stdClass();
        $license->is_cancelled = false;
        $license->expiration   = gmdate( 'Y-m-d H:i:s', time() + ( 60 * DAY_IN_SECONDS ) );

        $fs = Mockery::mock( 'Freemius' );
        $fs->shouldReceive( 'is_registered' )->andReturn( true );
        $fs->shouldReceive( 'is_anonymous' )->andReturn( false );
        $fs->shouldReceive( 'is_free_plan' )->andReturn( false );
        $fs->shouldReceive( 'is_trial' )->andReturn( false );
        $fs->shouldReceive( '_get_license' )->andReturn( $license );
        $fs->shouldReceive( 'is_pending_activation' )->andReturn( false );

        $notices  = $this->make_notices( $fs );
        $response = $notices->get_notices();

        $data = $response->get_data()['data'];
        $this->assertCount( 0, $data['notices'] );
    }

    // -------------------------------------------------------------------------
    // dismiss_notice()
    // -------------------------------------------------------------------------

    public function test_dismiss_notice_returns_400_for_empty_id(): void {
        $fs      = Mockery::mock( 'Freemius' );
        $notices = $this->make_notices( $fs );

        Functions\when( 'sanitize_key' )->justReturn( '' );

        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_param' )->with( 'id' )->andReturn( '' );

        $response = $notices->dismiss_notice( $request );

        $this->assertInstanceOf( WP_Error::class, $response );
        $this->assertSame( 'invalid_notice_id', $response->get_error_code() );
    }

    public function test_dismiss_notice_stores_id_in_user_meta(): void {
        $fs      = Mockery::mock( 'Freemius' );
        $notices = $this->make_notices( $fs );

        Functions\when( 'sanitize_key' )->returnArg( 1 );
        Functions\expect( 'update_user_meta' )
            ->once()
            ->with( 1, 'my_plugin__fs_dismissed_notices', Mockery::on( function ( $val ) {
                return in_array( 'license_expiring', $val, true );
            } ) );

        $request = Mockery::mock( 'WP_REST_Request' );
        $request->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 'license_expiring' );

        $response = $notices->dismiss_notice( $request );

        $this->assertNotInstanceOf( WP_Error::class, $response );
        $this->assertSame( 'success', $response->get_data()['code'] );
    }
}
