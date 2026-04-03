<?php
/**
 * Google Calendar appointment booking — Growth tier feature.
 *
 * Handles service configuration, availability checking, appointment creation
 * and cancellation via Google Calendar API, and booking intent extraction
 * from LLM responses.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Booking
 *
 * Singleton. All Google Calendar API calls use GrayFox_Google::get_instance()->get_access_token().
 */
class GrayFox_Booking {

	/**
	 * Google Calendar Events API base URL.
	 *
	 * @var string
	 */
	const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3/calendars';

	/**
	 * Singleton instance.
	 *
	 * @var GrayFox_Booking|null
	 */
	private static ?GrayFox_Booking $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GrayFox_Booking
	 */
	public static function get_instance(): GrayFox_Booking {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'wp_ajax_nopriv_grayfox_check_availability', $this, 'handle_check_availability' );
		$loader->add_action( 'wp_ajax_grayfox_check_availability',        $this, 'handle_check_availability' );

		$loader->add_action( 'wp_ajax_nopriv_grayfox_confirm_booking', $this, 'handle_confirm_booking' );
		$loader->add_action( 'wp_ajax_grayfox_confirm_booking',        $this, 'handle_confirm_booking' );

		$loader->add_action( 'wp_ajax_grayfox_cancel_booking',          $this, 'handle_cancel_booking' );
		$loader->add_action( 'wp_ajax_grayfox_save_booking_settings',   $this, 'handle_save_booking_settings' );

		// Action Scheduler callback for async appointment creation.
		$loader->add_action( 'grayfox_process_booking', $this, 'process_booking_job' );

		// Also register directly on the init hook (priority 5) so Action Scheduler
		// can fire the job even when the full plugin constructor has not yet run.
		$loader->add_action( 'init', $this, 'register_as_callback', 5 );
	}

	/**
	 * Register the Action Scheduler callback on the init hook.
	 *
	 * Called at priority 5 of 'init' to ensure AS can dispatch before later hooks fire.
	 */
	public function register_as_callback(): void {
		add_action( 'grayfox_process_booking', array( $this, 'process_booking_job' ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the calendar ID option, defaulting to "primary".
	 *
	 * @return string
	 */
	private function get_calendar_id(): string {
		$id = get_option( 'grayfox_booking_calendar_id', 'primary' );
		return ! empty( $id ) ? sanitize_text_field( $id ) : 'primary';
	}

	/**
	 * Return the booking timezone, defaulting to the WordPress site timezone.
	 *
	 * @return string
	 */
	private function get_timezone(): string {
		$tz = get_option( 'grayfox_booking_timezone', '' );
		return ! empty( $tz ) ? sanitize_text_field( $tz ) : wp_timezone_string();
	}

	/**
	 * Return the buffer minutes between appointments (default 15).
	 *
	 * @return int
	 */
	private function get_buffer_minutes(): int {
		return (int) get_option( 'grayfox_booking_buffer_minutes', 15 );
	}

	/**
	 * Return the decoded services array.
	 *
	 * @return array<int, array{name: string, duration_minutes: int, price: float}>
	 */
	private function get_services(): array {
		$raw = get_option( 'grayfox_booking_services', '[]' );
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Return the working hours array keyed by lowercase day name.
	 *
	 * @return array<string, array{open: string, close: string}|false>
	 */
	private function get_working_hours(): array {
		$raw = get_option( 'grayfox_booking_working_hours', '' );
		if ( empty( $raw ) ) {
			// Default Mon–Fri 09:00–17:00.
			$default = array();
			foreach ( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ) as $day ) {
				$default[ $day ] = array( 'open' => '09:00', 'close' => '17:00' );
			}
			foreach ( array( 'saturday', 'sunday' ) as $day ) {
				$default[ $day ] = false;
			}
			return $default;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Find a service by name and return its duration in minutes, or null.
	 *
	 * @param string $service_name Service name.
	 * @return int|null Duration in minutes, or null if not found.
	 */
	private function get_service_duration( string $service_name ): ?int {
		foreach ( $this->get_services() as $service ) {
			if ( isset( $service['name'] ) && $service['name'] === $service_name ) {
				return isset( $service['duration_minutes'] ) ? (int) $service['duration_minutes'] : null;
			}
		}
		return null;
	}

	/**
	 * Make an authenticated request to the Google Calendar API.
	 *
	 * @param string $method  HTTP method (GET, POST, DELETE).
	 * @param string $url     Full API URL.
	 * @param array  $body    Request body (for POST). Empty for GET/DELETE.
	 * @return array{code: int, body: array}|WP_Error
	 */
	private function calendar_request( string $method, string $url, array $body = array() ) {
		$access_token = GrayFox_Google::get_instance()->get_access_token();

		if ( null === $access_token ) {
			return new WP_Error( 'google_not_connected', __( 'Google account is not connected.', 'grayfox' ) );
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 15,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$decoded_body = json_decode( wp_remote_retrieve_body( $response ), true );

		return array(
			'code' => $code,
			'body' => is_array( $decoded_body ) ? $decoded_body : array(),
		);
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Check available time slots for a given date and service.
	 *
	 * Retrieves all events from Google Calendar for the date, then returns
	 * time slots that do not conflict with any existing event.
	 *
	 * @param string $date         Date in Y-m-d format.
	 * @param string $service_name Name of the service.
	 * @return array<int, array{time: string, label: string}> Available slots, or empty array on failure.
	 */
	public function check_availability( string $date, string $service_name ): array {
		$duration_minutes = $this->get_service_duration( $service_name );
		if ( null === $duration_minutes || $duration_minutes <= 0 ) {
			return array();
		}

		$timezone_string = $this->get_timezone();

		try {
			$tz = new DateTimeZone( $timezone_string );
		} catch ( \Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}

		// Determine day-of-week and working hours.
		try {
			$date_obj = new DateTime( $date, $tz );
		} catch ( \Exception $e ) {
			return array();
		}

		$day_name     = strtolower( $date_obj->format( 'l' ) ); // e.g. "monday"
		$working_hours = $this->get_working_hours();

		if ( empty( $working_hours[ $day_name ] ) || false === $working_hours[ $day_name ] ) {
			return array(); // Day is closed.
		}

		$hours = $working_hours[ $day_name ];
		if ( ! isset( $hours['open'], $hours['close'] ) ) {
			return array();
		}

		// Build candidate slots.
		$buffer_minutes = $this->get_buffer_minutes();
		$slot_step      = $duration_minutes + $buffer_minutes;

		[ $open_hour, $open_min ]   = array_map( 'intval', explode( ':', $hours['open'] ) );
		[ $close_hour, $close_min ] = array_map( 'intval', explode( ':', $hours['close'] ) );

		try {
			$slot_start = new DateTime( $date . ' ' . $hours['open'], $tz );
			$day_close  = new DateTime( $date . ' ' . $hours['close'], $tz );
		} catch ( \Exception $e ) {
			return array();
		}

		$candidate_slots = array();
		$slot_cursor     = clone $slot_start;

		while ( true ) {
			$slot_end = clone $slot_cursor;
			$slot_end->modify( '+' . $duration_minutes . ' minutes' );

			if ( $slot_end > $day_close ) {
				break;
			}

			$candidate_slots[] = array(
				'start' => clone $slot_cursor,
				'end'   => $slot_end,
			);

			$slot_cursor->modify( '+' . $slot_step . ' minutes' );
		}

		if ( empty( $candidate_slots ) ) {
			return array();
		}

		// Fetch existing events from Google Calendar.
		$calendar_id   = rawurlencode( $this->get_calendar_id() );
		$dt_min        = new DateTime( $date . ' 00:00:00', $tz );
		$dt_max        = new DateTime( $date . ' 23:59:59', $tz );
		$time_min      = $dt_min->format( DateTime::ATOM );
		$time_max      = $dt_max->format( DateTime::ATOM );
		$url           = self::CALENDAR_API_BASE . '/' . $calendar_id . '/events'
			. '?timeMin=' . rawurlencode( $time_min )
			. '&timeMax=' . rawurlencode( $time_max )
			. '&singleEvents=true';

		$result = $this->calendar_request( 'GET', $url );

		$busy_intervals = array();

		if ( ! is_wp_error( $result ) && $result['code'] === 200 ) {
			$items = $result['body']['items'] ?? array();
			foreach ( $items as $event ) {
				$event_start = $event['start']['dateTime'] ?? ( $event['start']['date'] ?? null );
				$event_end   = $event['end']['dateTime'] ?? ( $event['end']['date'] ?? null );
				if ( $event_start && $event_end ) {
					try {
						$busy_intervals[] = array(
							'start' => new DateTime( $event_start ),
							'end'   => new DateTime( $event_end ),
						);
					} catch ( \Exception $e ) {
						// Skip malformed event.
					}
				}
			}
		}

		// Filter out conflicting slots.
		$available = array();
		foreach ( $candidate_slots as $slot ) {
			$conflicts = false;
			foreach ( $busy_intervals as $busy ) {
				// Overlap: slot starts before busy ends AND slot ends after busy starts.
				if ( $slot['start'] < $busy['end'] && $slot['end'] > $busy['start'] ) {
					$conflicts = true;
					break;
				}
			}

			if ( ! $conflicts ) {
				$time_str    = $slot['start']->format( 'H:i' );
				$label       = $slot['start']->format( 'g:i A' );
				$available[] = array(
					'time'  => $time_str,
					'label' => $label,
				);
			}
		}

		return $available;
	}

	/**
	 * Create a new appointment: write a Google Calendar event and save to DB.
	 *
	 * @param array $data {
	 *     @type string $customer_name   Customer full name.
	 *     @type string $customer_email  Customer email address.
	 *     @type string $service         Service name.
	 *     @type string $start_datetime  ISO 8601 start datetime string.
	 *     @type string $end_datetime    ISO 8601 end datetime string.
	 *     @type string $notes           Optional customer notes.
	 * }
	 * @return array{appointment_id: int, google_event_id: string, confirmation: string}|WP_Error
	 */
	public function create_appointment( array $data ): array|WP_Error {
		$customer_name  = sanitize_text_field( $data['customer_name'] ?? '' );
		$customer_email = sanitize_email( $data['customer_email'] ?? '' );
		$service        = sanitize_text_field( $data['service'] ?? '' );
		$start_datetime = sanitize_text_field( $data['start_datetime'] ?? '' );
		$end_datetime   = sanitize_text_field( $data['end_datetime'] ?? '' );
		$notes          = sanitize_textarea_field( $data['notes'] ?? '' );
		$timezone       = $this->get_timezone();

		if ( empty( $customer_name ) || empty( $customer_email ) || empty( $service )
			|| empty( $start_datetime ) || empty( $end_datetime ) ) {
			return new WP_Error( 'missing_fields', __( 'Required booking fields are missing.', 'grayfox' ) );
		}

		$calendar_id = $this->get_calendar_id();
		$encoded_id  = rawurlencode( $calendar_id );

		$event_body = array(
			'summary'     => $service . ' — ' . $customer_name,
			'description' => sprintf(
				"Customer: %s\nEmail: %s\nNotes: %s",
				$customer_name,
				$customer_email,
				$notes
			),
			'start'       => array(
				'dateTime' => $start_datetime,
				'timeZone' => $timezone,
			),
			'end'         => array(
				'dateTime' => $end_datetime,
				'timeZone' => $timezone,
			),
			'attendees'   => array(
				array( 'email' => $customer_email ),
			),
			'sendUpdates' => 'all',
		);

		$url    = self::CALENDAR_API_BASE . '/' . $encoded_id . '/events';
		$result = $this->calendar_request( 'POST', $url, $event_body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $result['code'] !== 200 && $result['code'] !== 201 ) {
			return new WP_Error(
				'calendar_api_error',
				__( 'Google Calendar API returned an error.', 'grayfox' ),
				array( 'status' => $result['code'], 'body' => $result['body'] )
			);
		}

		$google_event_id = sanitize_text_field( $result['body']['id'] ?? '' );

		// Save to DB.
		global $wpdb;
		$appt_table = esc_sql( GrayFox_DB::get_table( 'appointments' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$appt_table,
			array(
				'customer_name'   => $customer_name,
				'customer_email'  => $customer_email,
				'service'         => $service,
				'start_time'      => gmdate( 'Y-m-d H:i:s', strtotime( $start_datetime ) ),
				'end_time'        => gmdate( 'Y-m-d H:i:s', strtotime( $end_datetime ) ),
				'google_event_id' => $google_event_id,
				'status'          => 'confirmed',
				'notes'           => $notes,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			error_log( 'GrayFox: appointment DB insert failed for calendar event ' . esc_attr( $google_event_id ) );
			return new WP_Error( 'db_insert_failed', __( 'Appointment saved to calendar but could not be recorded locally. Please check error log.', 'grayfox' ) );
		}

		$appointment_id = (int) $wpdb->insert_id;

		$confirmation = sprintf(
			/* translators: 1: service name, 2: formatted start datetime */
			__( 'Your %1$s appointment has been confirmed for %2$s. A confirmation has been sent to your email.', 'grayfox' ),
			$service,
			$start_datetime
		);

		return array(
			'appointment_id'  => $appointment_id,
			'google_event_id' => $google_event_id,
			'confirmation'    => $confirmation,
		);
	}

	/**
	 * Cancel an appointment: delete the Google Calendar event and update DB status.
	 *
	 * @param int $appointment_id Appointment row ID.
	 * @return bool True on success, false on failure.
	 */
	public function cancel_appointment( int $appointment_id ): bool {
		global $wpdb;

		$appt_table = esc_sql( GrayFox_DB::get_table( 'appointments' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$appt_table}` WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$appointment_id
			)
		);

		if ( ! $appointment ) {
			return false;
		}

		// Delete from Google Calendar if we have an event ID.
		if ( ! empty( $appointment->google_event_id ) ) {
			$calendar_id = $this->get_calendar_id();
			$encoded_id  = rawurlencode( $calendar_id );
			$event_id    = rawurlencode( $appointment->google_event_id );
			$url         = self::CALENDAR_API_BASE . '/' . $encoded_id . '/events/' . $event_id . '?sendUpdates=all';

			$this->calendar_request( 'DELETE', $url );
			// Non-fatal: we still update DB status even if Google call fails.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$appt_table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $appointment_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $updated !== false;
	}

	/**
	 * Get appointments with optional filters.
	 *
	 * @param array $filters Optional. Supported keys:
	 *                       'status' (string), 'date_from' (Y-m-d), 'date_to' (Y-m-d).
	 * @return array<int, object> Array of appointment row objects.
	 */
	public function get_appointments( array $filters = array() ): array {
		global $wpdb;

		$appt_table = esc_sql( GrayFox_DB::get_table( 'appointments' ) );
		$where      = array( '1=1' );
		$values     = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $filters['status'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'start_time >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'start_time <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM `{$appt_table}` WHERE {$where_sql} ORDER BY start_time DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Extract booking intent from an LLM response string.
	 *
	 * Looks for a JSON block of the form:
	 *   {"booking": {"service": ..., "date": ..., "time": ..., "customer_name": ..., "customer_email": ...}}
	 *
	 * @param string $llm_response Full LLM response text.
	 * @return array|null Booking data array if complete intent found, null otherwise.
	 */
	public function extract_booking_intent( string $llm_response ): ?array {
		// Extract the first JSON object that contains a "booking" key.
		if ( ! preg_match( '/\{[^{}]*"booking"\s*:[^{}]*\{[^{}]*\}[^{}]*\}/s', $llm_response, $matches ) ) {
			// Safe fallback: bounded character class, no adjacent wildcards.
			if ( ! preg_match( '/\{[^{}]{0,2000}\}/s', $llm_response, $matches ) ) {
				return null;
			}
		}

		$decoded = json_decode( $matches[0], true );
		if ( ! is_array( $decoded ) || empty( $decoded['booking'] ) ) {
			return null;
		}

		$booking = $decoded['booking'];

		// Require all fields to be present and non-empty.
		$required = array( 'service', 'date', 'time', 'customer_name', 'customer_email' );
		foreach ( $required as $field ) {
			if ( empty( $booking[ $field ] ) ) {
				return null;
			}
		}

		return array(
			'service'        => sanitize_text_field( $booking['service'] ),
			'date'           => sanitize_text_field( $booking['date'] ),
			'time'           => sanitize_text_field( $booking['time'] ),
			'customer_name'  => sanitize_text_field( $booking['customer_name'] ),
			'customer_email' => sanitize_email( $booking['customer_email'] ),
			'notes'          => isset( $booking['notes'] ) ? sanitize_textarea_field( $booking['notes'] ) : '',
		);
	}

	// -------------------------------------------------------------------------
	// AJAX Handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Check availability for a given date and service.
	 * Action: wp_ajax_nopriv_grayfox_check_availability / wp_ajax_grayfox_check_availability
	 */
	public function handle_check_availability(): void {
		check_ajax_referer( 'grayfox_booking', 'nonce' );

		if ( ! $this->is_booking_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Booking is not available on this plan.', 'grayfox' ) ), 403 );
			return;
		}

		$date    = isset( $_POST['date'] )    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )    : '';
		$service = isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '';

		if ( empty( $date ) || empty( $service ) ) {
			wp_send_json_error( array( 'message' => __( 'Date and service are required.', 'grayfox' ) ), 400 );
			return;
		}

		$slots = $this->check_availability( $date, $service );
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * AJAX: Confirm a new booking.
	 * Action: wp_ajax_nopriv_grayfox_confirm_booking / wp_ajax_grayfox_confirm_booking
	 */
	public function handle_confirm_booking(): void {
		check_ajax_referer( 'grayfox_booking', 'nonce' );

		if ( ! $this->is_booking_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Booking is not available on this plan.', 'grayfox' ) ), 403 );
			return;
		}

		$customer_name   = isset( $_POST['customer_name'] )   ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) )   : '';
		$customer_email  = isset( $_POST['customer_email'] )  ? sanitize_email( wp_unslash( $_POST['customer_email'] ) )        : '';
		$service         = isset( $_POST['service'] )         ? sanitize_text_field( wp_unslash( $_POST['service'] ) )         : '';
		$start_datetime  = isset( $_POST['start_datetime'] )  ? sanitize_text_field( wp_unslash( $_POST['start_datetime'] ) )  : '';
		$end_datetime    = isset( $_POST['end_datetime'] )    ? sanitize_text_field( wp_unslash( $_POST['end_datetime'] ) )    : '';
		$notes           = isset( $_POST['notes'] )           ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) )       : '';

		$result = $this->create_appointment( array(
			'customer_name'  => $customer_name,
			'customer_email' => $customer_email,
			'service'        => $service,
			'start_datetime' => $start_datetime,
			'end_datetime'   => $end_datetime,
			'notes'          => $notes,
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
			return;
		}

		wp_send_json_success( array(
			'appointment_id'       => $result['appointment_id'],
			'confirmation_message' => $result['confirmation'],
		) );
	}

	/**
	 * AJAX: Cancel an appointment (admin only).
	 * Action: wp_ajax_grayfox_cancel_booking
	 */
	public function handle_cancel_booking(): void {
		check_ajax_referer( 'grayfox_cancel_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'grayfox' ) ), 403 );
			return;
		}

		$appointment_id = isset( $_POST['appointment_id'] ) ? (int) $_POST['appointment_id'] : 0;

		if ( $appointment_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid appointment ID.', 'grayfox' ) ), 400 );
			return;
		}

		$success = $this->cancel_appointment( $appointment_id );

		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Appointment cancelled.', 'grayfox' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not cancel appointment.', 'grayfox' ) ), 500 );
		}
	}

	// -------------------------------------------------------------------------
	// Action Scheduler callback
	// -------------------------------------------------------------------------

	/**
	 * Action Scheduler job: process a booking extracted from an LLM response.
	 *
	 * @param array $booking_data Booking data array from extract_booking_intent().
	 */
	public function process_booking_job( array $booking_data ): void {
		if ( empty( $booking_data ) ) {
			return;
		}

		$date    = $booking_data['date']    ?? '';
		$time    = $booking_data['time']    ?? '';
		$service = $booking_data['service'] ?? '';

		if ( empty( $date ) || empty( $time ) || empty( $service ) ) {
			return;
		}

		// Build ISO 8601 start/end datetimes.
		$timezone     = $this->get_timezone();
		$duration     = $this->get_service_duration( $service );
		$duration     = $duration ?? 60;

		$start_raw = $date . 'T' . $time . ':00';
		try {
			$tz        = new DateTimeZone( $timezone );
			$start_dt  = new DateTime( $start_raw, $tz );
			$end_dt    = clone $start_dt;
			$end_dt->modify( '+' . $duration . ' minutes' );
		} catch ( \Exception $e ) {
			return;
		}

		$this->create_appointment( array(
			'customer_name'  => $booking_data['customer_name']  ?? '',
			'customer_email' => $booking_data['customer_email'] ?? '',
			'service'        => $service,
			'start_datetime' => $start_dt->format( 'c' ),
			'end_datetime'   => $end_dt->format( 'c' ),
			'notes'          => $booking_data['notes'] ?? '',
		) );
	}

	/**
	 * AJAX: Save booking settings (admin only).
	 * Action: wp_ajax_grayfox_save_booking_settings
	 */
	public function handle_save_booking_settings(): void {
		check_ajax_referer( 'grayfox_booking_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'grayfox' ) ), 403 );
			return;
		}

		// --- Services ---
		$raw_services = isset( $_POST['services'] ) ? wp_unslash( $_POST['services'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded_services = json_decode( $raw_services, true );
		$clean_services   = array();
		if ( is_array( $decoded_services ) ) {
			foreach ( $decoded_services as $svc ) {
				if ( ! is_array( $svc ) ) {
					continue;
				}
				$clean_services[] = array(
					'name'             => sanitize_text_field( $svc['name'] ?? '' ),
					'duration_minutes' => absint( $svc['duration_minutes'] ?? 60 ),
					'price'            => round( (float) ( $svc['price'] ?? 0 ), 2 ),
				);
			}
		}
		update_option( 'grayfox_booking_services', wp_json_encode( $clean_services ) );

		// --- Working hours ---
		$raw_hours    = isset( $_POST['working_hours'] ) ? wp_unslash( $_POST['working_hours'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded_hours = json_decode( $raw_hours, true );
		$clean_hours   = array();
		$days_of_week  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		if ( is_array( $decoded_hours ) ) {
			foreach ( $days_of_week as $day ) {
				if ( ! isset( $decoded_hours[ $day ] ) || false === $decoded_hours[ $day ] ) {
					$clean_hours[ $day ] = false;
					continue;
				}
				$day_data = $decoded_hours[ $day ];
				if ( ! is_array( $day_data ) || empty( $day_data['enabled'] ) ) {
					$clean_hours[ $day ] = false;
					continue;
				}
				$clean_hours[ $day ] = array(
					'open'    => sanitize_text_field( $day_data['open'] ?? '09:00' ),
					'close'   => sanitize_text_field( $day_data['close'] ?? '17:00' ),
					'enabled' => true,
				);
			}
		}
		update_option( 'grayfox_booking_working_hours', wp_json_encode( $clean_hours ) );

		// --- Buffer minutes ---
		$allowed_buffers = array( 0, 15, 30, 45, 60 );
		$buffer          = absint( isset( $_POST['buffer_minutes'] ) ? wp_unslash( $_POST['buffer_minutes'] ) : 15 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! in_array( $buffer, $allowed_buffers, true ) ) {
			$buffer = 15;
		}
		update_option( 'grayfox_booking_buffer_minutes', $buffer );

		// --- Calendar ID ---
		$calendar_id = isset( $_POST['calendar_id'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_id'] ) ) : 'primary';
		update_option( 'grayfox_booking_calendar_id', ! empty( $calendar_id ) ? $calendar_id : 'primary' );

		// --- Timezone ---
		$timezone = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : wp_timezone_string();
		if ( empty( $timezone ) ) {
			$timezone = wp_timezone_string();
		}
		update_option( 'grayfox_booking_timezone', $timezone );

		wp_send_json_success( array( 'message' => __( 'Booking settings saved.', 'grayfox' ) ) );
	}

	// -------------------------------------------------------------------------
	// Tier check
	// -------------------------------------------------------------------------

	/**
	 * Whether the booking feature is enabled for the current license tier.
	 *
	 * @return bool
	 */
	public function is_booking_enabled(): bool {
		return in_array( GrayFox_License::get_verified_tier(), array( 'growth', 'pro' ), true );
	}
}
