<?php
/**
 * Unit tests for GrayFox_Booking.
 *
 * Run with: ./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/GrayFoxBookingTest.php
 *
 * @package GrayFox
 */

use PHPUnit\Framework\TestCase;

class GrayFoxBookingTest extends TestCase {

	/**
	 * Return a fresh (reset) singleton for each test.
	 */
	private function fresh_booking(): GrayFox_Booking {
		// Reset the singleton so each test starts clean.
		$ref = new ReflectionClass( GrayFox_Booking::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		return GrayFox_Booking::get_instance();
	}

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	public function test_get_instance_returns_same_object(): void {
		$a = GrayFox_Booking::get_instance();
		$b = GrayFox_Booking::get_instance();
		$this->assertSame( $a, $b );
	}

	// -----------------------------------------------------------------------
	// extract_booking_intent — valid input
	// -----------------------------------------------------------------------

	public function test_extract_booking_intent_returns_array_for_valid_json(): void {
		$booking = $this->fresh_booking();
		$response = 'Sure! {"booking": {"service": "Consultation", "date": "2026-04-01", "time": "10:00", "customer_name": "Jane Doe", "customer_email": "jane@example.com"}} Let me confirm.';

		$result = $booking->extract_booking_intent( $response );

		$this->assertIsArray( $result );
		$this->assertSame( 'Consultation', $result['service'] );
		$this->assertSame( '2026-04-01', $result['date'] );
		$this->assertSame( '10:00', $result['time'] );
		$this->assertSame( 'Jane Doe', $result['customer_name'] );
		$this->assertSame( 'jane@example.com', $result['customer_email'] );
	}

	public function test_extract_booking_intent_includes_notes_when_present(): void {
		$booking = $this->fresh_booking();
		$response = '{"booking": {"service": "Haircut", "date": "2026-04-02", "time": "14:30", "customer_name": "Bob Smith", "customer_email": "bob@example.com", "notes": "Please confirm"}}';

		$result = $booking->extract_booking_intent( $response );

		$this->assertIsArray( $result );
		$this->assertSame( 'Please confirm', $result['notes'] );
	}

	// -----------------------------------------------------------------------
	// extract_booking_intent — invalid/incomplete input
	// -----------------------------------------------------------------------

	public function test_extract_booking_intent_returns_null_when_no_json(): void {
		$booking = $this->fresh_booking();
		$result  = $booking->extract_booking_intent( 'Just a plain text response with no booking data.' );
		$this->assertNull( $result );
	}

	public function test_extract_booking_intent_returns_null_when_missing_required_field(): void {
		$booking = $this->fresh_booking();
		// Missing customer_email.
		$response = '{"booking": {"service": "Massage", "date": "2026-04-03", "time": "09:00", "customer_name": "Alice"}}';

		$result = $booking->extract_booking_intent( $response );
		$this->assertNull( $result );
	}

	public function test_extract_booking_intent_returns_null_for_empty_string(): void {
		$booking = $this->fresh_booking();
		$this->assertNull( $booking->extract_booking_intent( '' ) );
	}

	public function test_extract_booking_intent_returns_null_when_service_is_empty_string(): void {
		$booking = $this->fresh_booking();
		$response = '{"booking": {"service": "", "date": "2026-04-04", "time": "11:00", "customer_name": "Tom", "customer_email": "tom@example.com"}}';

		$result = $booking->extract_booking_intent( $response );
		$this->assertNull( $result );
	}

	// -----------------------------------------------------------------------
	// check_availability — day disabled
	// -----------------------------------------------------------------------

	public function test_check_availability_returns_empty_for_disabled_day(): void {
		$booking = $this->fresh_booking();

		// Override get_working_hours to mark Saturday as disabled.
		// We test via the public interface: pass a Saturday date.
		// The default working hours from bootstrap get_option returns '' which triggers
		// the default Mon-Fri schedule. Saturday = not enabled => [].

		// 2026-04-04 is a Saturday.
		$result = $booking->check_availability( '2026-04-04', 'Consultation' );
		$this->assertSame( array(), $result );
	}

	public function test_check_availability_returns_empty_for_unknown_service(): void {
		$booking = $this->fresh_booking();
		// Service not in the services list — get_services() returns [] from stub,
		// so get_service_duration returns null => empty array.
		$result = $booking->check_availability( '2026-04-06', 'Unknown Service' );
		$this->assertSame( array(), $result );
	}

	// -----------------------------------------------------------------------
	// is_booking_enabled
	// -----------------------------------------------------------------------

	public function test_is_booking_enabled_false_for_starter(): void {
		$booking = $this->fresh_booking();
		// get_option returns default 'starter' from bootstrap stub.
		$this->assertFalse( $booking->is_booking_enabled() );
	}

	// -----------------------------------------------------------------------
	// create_appointment — missing required fields
	// -----------------------------------------------------------------------

	public function test_create_appointment_returns_wp_error_on_missing_fields(): void {
		$booking = $this->fresh_booking();

		$result = $booking->create_appointment( array(
			'customer_name'  => '',
			'customer_email' => '',
			'service'        => '',
			'start_datetime' => '',
			'end_datetime'   => '',
		) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_fields', $result->get_error_code() );
	}

	public function test_create_appointment_returns_wp_error_when_google_disconnected(): void {
		$booking = $this->fresh_booking();

		// Simulate Google disconnected by setting stub_token to null.
		GrayFox_Google::get_instance()->stub_token = null;

		$result = $booking->create_appointment( array(
			'customer_name'  => 'Jane Doe',
			'customer_email' => 'jane@example.com',
			'service'        => 'Consultation',
			'start_datetime' => '2026-04-06T10:00:00',
			'end_datetime'   => '2026-04-06T11:00:00',
			'notes'          => '',
		) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'google_not_connected', $result->get_error_code() );

		// Restore for other tests.
		GrayFox_Google::get_instance()->stub_token = 'test-access-token';
	}

	// -----------------------------------------------------------------------
	// cancel_appointment — invalid ID
	// -----------------------------------------------------------------------

	public function test_cancel_appointment_returns_false_for_nonexistent_id(): void {
		$booking = $this->fresh_booking();

		// The DB stub in bootstrap does not have a real $wpdb, so we expect false
		// because get_row will return null on any real query. Since $wpdb is not
		// defined in unit tests, cancel_appointment will attempt to use it. We mock
		// $wpdb as a global with a get_row that always returns null.
		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function get_row( $sql ) { return null; }
			public function prepare( $sql, ...$args ): string { return $sql; }
			public function update( $table, $data, $where, $format = null, $where_format = null ) { return false; }
		};

		$result = $booking->cancel_appointment( 9999 );
		$this->assertFalse( $result );
	}

	// -----------------------------------------------------------------------
	// get_appointments — returns array
	// -----------------------------------------------------------------------

	public function test_get_appointments_returns_array(): void {
		$booking = $this->fresh_booking();

		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function get_results( $sql ): array { return array(); }
			public function prepare( $sql, ...$args ): string { return $sql; }
		};

		$result = $booking->get_appointments();
		$this->assertIsArray( $result );
	}

	public function test_get_appointments_with_status_filter_returns_array(): void {
		$booking = $this->fresh_booking();

		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function get_results( $sql ): array { return array(); }
			public function prepare( $sql, ...$args ): string { return $sql; }
		};

		$result = $booking->get_appointments( array( 'status' => 'confirmed' ) );
		$this->assertIsArray( $result );
	}

	// -----------------------------------------------------------------------
	// process_booking_job — missing data no-ops
	// -----------------------------------------------------------------------

	public function test_process_booking_job_returns_void_on_empty_data(): void {
		$booking = $this->fresh_booking();
		// Should not throw. Return type is void, so we just verify no exception.
		$booking->process_booking_job( array() );
		$this->assertTrue( true ); // Reached here = pass.
	}

	public function test_process_booking_job_returns_void_on_missing_date(): void {
		$booking = $this->fresh_booking();
		$booking->process_booking_job( array(
			'service'        => 'Consultation',
			'time'           => '10:00',
			'customer_name'  => 'Alice',
			'customer_email' => 'alice@example.com',
			// 'date' intentionally missing
		) );
		$this->assertTrue( true );
	}

	// -----------------------------------------------------------------------
	// Data sanitization in extract_booking_intent
	// -----------------------------------------------------------------------

	public function test_extract_booking_intent_strips_html_from_fields(): void {
		$booking  = $this->fresh_booking();
		$response = '{"booking": {"service": "<b>Haircut</b>", "date": "2026-04-10", "time": "09:00", "customer_name": "<script>alert(1)</script>Eve", "customer_email": "eve@example.com"}}';

		$result = $booking->extract_booking_intent( $response );

		$this->assertIsArray( $result );
		$this->assertStringNotContainsString( '<b>', $result['service'] );
		$this->assertStringNotContainsString( '<script>', $result['customer_name'] );
	}
}
