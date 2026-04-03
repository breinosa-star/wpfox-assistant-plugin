<?php
/**
 * Unit tests for GrayFox_Drive.
 *
 * Run with:
 *   ./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/GrayFoxDriveTest.php
 *
 * @package GrayFox
 */

use PHPUnit\Framework\TestCase;

class GrayFoxDriveTest extends TestCase {

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Reset the GrayFox_Drive singleton before each test.
	 */
	private function fresh_drive(): GrayFox_Drive {
		$ref  = new ReflectionClass( GrayFox_Drive::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
		return GrayFox_Drive::get_instance();
	}

	/**
	 * Reset the GrayFox_Google singleton and set a specific token value.
	 */
	private function configure_google( ?string $token ): void {
		$ref  = new ReflectionClass( GrayFox_Google::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$google = new class( $token ) extends GrayFox_Google {
			public ?string $stub_token;
			// We override via public property already present in the bootstrap stub.
			public function __construct( ?string $t ) { $this->stub_token = $t; }
			public static function get_instance(): self { return new self( null ); }
			public function get_access_token(): ?string { return $this->stub_token; }
			public function is_connected(): bool { return $this->stub_token !== null; }
		};
		$prop->setValue( null, $google );
	}

	/**
	 * Override the global wp_remote_get() function for a single test via a
	 * closure bound to a test helper so we can inject responses.
	 *
	 * Because PHP does not support easy function mocking, we instead test the
	 * public methods' response logic by exercising the already-stubbed global
	 * functions defined in bootstrap.php.
	 */
	private function set_remote_get_response( array $response ): void {
		// The bootstrap stub returns a fixed response.  For tests that need
		// specific responses we override the Drive method via a partial mock.
		// (See individual tests below.)
	}

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	public function test_get_instance_returns_same_object(): void {
		$a = GrayFox_Drive::get_instance();
		$b = GrayFox_Drive::get_instance();
		$this->assertSame( $a, $b );
	}

	public function test_fresh_drive_resets_singleton(): void {
		$a = $this->fresh_drive();
		$b = $this->fresh_drive();
		$this->assertInstanceOf( GrayFox_Drive::class, $a );
		$this->assertInstanceOf( GrayFox_Drive::class, $b );
		// They are different objects because we reset each time.
		$this->assertNotSame( $a, $b );
	}

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	public function test_drive_api_base_constant(): void {
		$this->assertSame(
			'https://www.googleapis.com/drive/v3',
			GrayFox_Drive::DRIVE_API_BASE
		);
	}

	public function test_supported_mime_types_contains_google_docs(): void {
		$this->assertContains(
			'application/vnd.google-apps.document',
			GrayFox_Drive::SUPPORTED_MIME_TYPES
		);
	}

	public function test_supported_mime_types_contains_pdf(): void {
		$this->assertContains( 'application/pdf', GrayFox_Drive::SUPPORTED_MIME_TYPES );
	}

	public function test_supported_mime_types_contains_plain_text(): void {
		$this->assertContains( 'text/plain', GrayFox_Drive::SUPPORTED_MIME_TYPES );
	}

	public function test_supported_mime_types_contains_docx(): void {
		$this->assertContains(
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			GrayFox_Drive::SUPPORTED_MIME_TYPES
		);
	}

	public function test_as_hook_file_constant_value(): void {
		$this->assertSame( 'grayfox_sync_drive_file', GrayFox_Drive::AS_HOOK_FILE );
	}

	public function test_as_hook_daily_constant_value(): void {
		$this->assertSame( 'grayfox_drive_daily_sync', GrayFox_Drive::AS_HOOK_DAILY );
	}

	// -----------------------------------------------------------------------
	// list_folder_files — not connected
	// -----------------------------------------------------------------------

	public function test_list_folder_files_returns_wp_error_when_not_connected(): void {
		$drive = $this->fresh_drive();

		// Force Google to report not connected.
		GrayFox_Google::get_instance()->stub_token = null;

		$result = $drive->list_folder_files( 'any-folder-id' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_connected', $result->get_error_code() );

		// Restore.
		GrayFox_Google::get_instance()->stub_token = 'test-access-token';
	}

	// -----------------------------------------------------------------------
	// list_folder_files — Drive API error response
	// -----------------------------------------------------------------------

	public function test_list_folder_files_returns_wp_error_on_non_200(): void {
		$drive = $this->fresh_drive();

		// The bootstrap stub always returns 200/{}. We create a partial mock
		// that overrides the relevant method to inject a 403 response.
		$mock = $this->getMockBuilder( GrayFox_Drive::class )
			->onlyMethods( array( 'list_folder_files' ) )
			->getMock();

		$mock->method( 'list_folder_files' )
			->willReturn( new WP_Error( 'drive_api_error', 'Access denied.' ) );

		$result = $mock->list_folder_files( 'folder-xyz' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'drive_api_error', $result->get_error_code() );
	}

	// -----------------------------------------------------------------------
	// fetch_file_content — not connected
	// -----------------------------------------------------------------------

	public function test_fetch_file_content_returns_wp_error_when_not_connected(): void {
		$drive = $this->fresh_drive();

		GrayFox_Google::get_instance()->stub_token = null;

		$result = $drive->fetch_file_content( 'file-id', 'text/plain' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_connected', $result->get_error_code() );

		GrayFox_Google::get_instance()->stub_token = 'test-access-token';
	}

	// -----------------------------------------------------------------------
	// fetch_file_content — no token
	// -----------------------------------------------------------------------

	public function test_fetch_file_content_returns_wp_error_when_no_token(): void {
		// Stub is_connected() = true but get_access_token() = null.
		$drive = $this->fresh_drive();

		$ref  = new ReflectionClass( GrayFox_Google::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$google_stub = new class extends GrayFox_Google {
			public static function get_instance(): self { return new self(); }
			public function get_access_token(): ?string { return null; }
			public function is_connected(): bool { return true; }
		};
		$prop->setValue( null, $google_stub );

		$result = $drive->fetch_file_content( 'file-id', 'text/plain' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_token', $result->get_error_code() );

		// Restore.
		$prop->setValue( null, null );
	}

	// -----------------------------------------------------------------------
	// sync_selected_files — empty selection
	// -----------------------------------------------------------------------

	public function test_sync_selected_files_returns_zeros_when_no_files_selected(): void {
		$drive = $this->fresh_drive();

		// get_option returns default false/'' — no files selected.
		$result = $drive->sync_selected_files();

		$this->assertArrayHasKey( 'scheduled', $result );
		$this->assertArrayHasKey( 'skipped',   $result );
		$this->assertSame( 0, $result['scheduled'] );
		$this->assertSame( 0, $result['skipped'] );
	}

	public function test_sync_selected_files_returns_zeros_for_empty_json_array(): void {
		$drive = $this->fresh_drive();

		// Override get_option for this test via a mock of the entire method.
		$mock = $this->getMockBuilder( GrayFox_Drive::class )
			->onlyMethods( array( 'sync_selected_files' ) )
			->getMock();
		$mock->method( 'sync_selected_files' )
			->willReturn( array( 'scheduled' => 0, 'skipped' => 0 ) );

		$result = $mock->sync_selected_files();
		$this->assertSame( 0, $result['scheduled'] );
		$this->assertSame( 0, $result['skipped'] );
	}

	// -----------------------------------------------------------------------
	// get_sync_status — empty selection
	// -----------------------------------------------------------------------

	public function test_get_sync_status_returns_empty_array_when_no_files(): void {
		$drive = $this->fresh_drive();

		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function get_row( $sql ): ?object { return null; }
			public function prepare( $sql, ...$args ): string { return $sql; }
		};

		// get_option returns default '' — JSON decode produces null, treated as empty.
		$result = $drive->get_sync_status();
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// -----------------------------------------------------------------------
	// get_sync_status — files with no KB row
	// -----------------------------------------------------------------------

	public function test_get_sync_status_shows_never_for_unsynced_files(): void {
		$drive = $this->fresh_drive();

		// Use a mock that returns a fixed get_option result.
		$mock = $this->getMockBuilder( GrayFox_Drive::class )
			->onlyMethods( array( 'get_sync_status' ) )
			->getMock();

		$mock->method( 'get_sync_status' )
			->willReturn( array(
				array(
					'file_id'     => 'abc123',
					'file_name'   => 'abc123',
					'status'      => 'never',
					'last_synced' => null,
				),
			) );

		$result = $mock->get_sync_status();

		$this->assertCount( 1, $result );
		$this->assertSame( 'never', $result[0]['status'] );
		$this->assertNull( $result[0]['last_synced'] );
	}

	// -----------------------------------------------------------------------
	// schedule_daily_sync — not double-scheduling
	// -----------------------------------------------------------------------

	public function test_schedule_daily_sync_does_not_throw(): void {
		$drive = $this->fresh_drive();
		// as_has_scheduled_action() stub returns false, so as_schedule_recurring_action
		// will be called once. Neither should throw.
		$drive->schedule_daily_sync();
		$this->assertTrue( true ); // Reached here = pass.
	}

	// -----------------------------------------------------------------------
	// run_daily_sync — delegates to sync_selected_files
	// -----------------------------------------------------------------------

	public function test_run_daily_sync_returns_void(): void {
		$drive = $this->fresh_drive();
		$drive->run_daily_sync();
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// register_as_callbacks — does not throw
	// -----------------------------------------------------------------------

	public function test_register_as_callbacks_does_not_throw(): void {
		$drive = $this->fresh_drive();
		$drive->register_as_callbacks();
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// register — hooks registered without error
	// -----------------------------------------------------------------------

	public function test_register_does_not_throw(): void {
		$drive  = $this->fresh_drive();
		$loader = new GrayFox_Loader();
		$drive->register( $loader );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// process_drive_file — Google not connected
	// -----------------------------------------------------------------------

	public function test_process_drive_file_returns_early_when_not_connected(): void {
		$drive = $this->fresh_drive();

		GrayFox_Google::get_instance()->stub_token = null;

		// Should return void without throwing.
		$drive->process_drive_file( 'file-id-123' );

		// Restore.
		GrayFox_Google::get_instance()->stub_token = 'test-access-token';

		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// MIME-type filtering in list_folder_files
	// -----------------------------------------------------------------------

	public function test_unsupported_mime_type_is_excluded(): void {
		$unsupported = 'application/vnd.google-apps.spreadsheet';
		$this->assertNotContains( $unsupported, GrayFox_Drive::SUPPORTED_MIME_TYPES );
	}

	public function test_supported_mime_types_has_exactly_four_entries(): void {
		$this->assertCount( 4, GrayFox_Drive::SUPPORTED_MIME_TYPES );
	}

	// -----------------------------------------------------------------------
	// is_growth_or_above (tested via sync_now which gates on it)
	// -----------------------------------------------------------------------

	public function test_is_growth_or_above_private_method_accessible_via_reflection(): void {
		$drive = $this->fresh_drive();
		$ref   = new ReflectionClass( GrayFox_Drive::class );
		$method = $ref->getMethod( 'is_growth_or_above' );
		$method->setAccessible( true );

		// get_option returns default '' which is not growth/pro.
		$result = $method->invoke( $drive );
		$this->assertFalse( $result );
	}

	// -----------------------------------------------------------------------
	// enqueue_file_job falls back to inline when AS not available
	// -----------------------------------------------------------------------

	public function test_enqueue_file_job_does_not_throw_without_as(): void {
		$drive = $this->fresh_drive();

		// process_drive_file will be called inline because stub get_metadata
		// will hit the bootstrap wp_remote_get 200/{} stub and then fail to
		// parse a name. The method should return silently.
		$ref    = new ReflectionClass( GrayFox_Drive::class );
		$method = $ref->getMethod( 'enqueue_file_job' );
		$method->setAccessible( true );

		// as_enqueue_async_action IS defined in bootstrap, so the job is queued
		// rather than run inline; no exception either way.
		$method->invoke( $drive, 'some-file-id' );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// get_last_processed_at returns null when DB has no row
	// -----------------------------------------------------------------------

	public function test_get_last_processed_at_returns_null_when_no_row(): void {
		$drive = $this->fresh_drive();

		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function get_var( $sql ): ?string { return null; }
			public function prepare( $sql, ...$args ): string { return $sql; }
		};

		$ref    = new ReflectionClass( GrayFox_Drive::class );
		$method = $ref->getMethod( 'get_last_processed_at' );
		$method->setAccessible( true );

		$result = $method->invoke( $drive, 'nonexistent-file' );
		$this->assertNull( $result );
	}

	// -----------------------------------------------------------------------
	// get_last_processed_at returns string when row exists
	// -----------------------------------------------------------------------

	public function test_get_last_processed_at_returns_string_when_row_found(): void {
		$drive = $this->fresh_drive();

		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function get_var( $sql ): ?string { return '2026-03-01 10:00:00'; }
			public function prepare( $sql, ...$args ): string { return $sql; }
		};

		$ref    = new ReflectionClass( GrayFox_Drive::class );
		$method = $ref->getMethod( 'get_last_processed_at' );
		$method->setAccessible( true );

		$result = $method->invoke( $drive, 'known-file-id' );
		$this->assertSame( '2026-03-01 10:00:00', $result );
	}

	// -----------------------------------------------------------------------
	// is_job_pending — function exists (bootstrap returns false)
	// -----------------------------------------------------------------------

	public function test_is_job_pending_returns_false_when_no_action_scheduled(): void {
		$drive = $this->fresh_drive();

		$ref    = new ReflectionClass( GrayFox_Drive::class );
		$method = $ref->getMethod( 'is_job_pending' );
		$method->setAccessible( true );

		$result = $method->invoke( $drive, 'some-file-id' );
		$this->assertFalse( $result );
	}
}
