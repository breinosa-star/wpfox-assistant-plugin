<?php
/**
 * Admin Booking Settings page.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$booking = GrayFox_Booking::get_instance();

if ( ! $booking->is_booking_enabled() ) {
	?>
	<div class="wrap grayfox-wrap">
		<h1><?php esc_html_e( 'Booking Settings', 'grayfox' ); ?></h1>
		<div class="grayfox-upgrade-notice">
			<p><?php esc_html_e( 'Booking settings are available on the Growth plan and above.', 'grayfox' ); ?></p>
			<a href="https://grayfox.ai/pricing" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php esc_html_e( 'Upgrade to Growth', 'grayfox' ); ?>
			</a>
		</div>
	</div>
	<?php
	return;
}

// Load current saved values.
$raw_services     = get_option( 'grayfox_booking_services', '[]' );
$services         = json_decode( $raw_services, true );
$services         = is_array( $services ) ? $services : array();

$raw_hours        = get_option( 'grayfox_booking_working_hours', '' );
$working_hours    = ! empty( $raw_hours ) ? json_decode( $raw_hours, true ) : null;

$buffer_minutes   = (int) get_option( 'grayfox_booking_buffer_minutes', 15 );
$calendar_id      = get_option( 'grayfox_booking_calendar_id', 'primary' );
$booking_timezone = get_option( 'grayfox_booking_timezone', wp_timezone_string() );

$days_of_week = array(
	'monday'    => __( 'Monday', 'grayfox' ),
	'tuesday'   => __( 'Tuesday', 'grayfox' ),
	'wednesday' => __( 'Wednesday', 'grayfox' ),
	'thursday'  => __( 'Thursday', 'grayfox' ),
	'friday'    => __( 'Friday', 'grayfox' ),
	'saturday'  => __( 'Saturday', 'grayfox' ),
	'sunday'    => __( 'Sunday', 'grayfox' ),
);

// Default working hours if not yet configured.
if ( null === $working_hours || ! is_array( $working_hours ) ) {
	$working_hours = array();
	foreach ( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ) as $day ) {
		$working_hours[ $day ] = array( 'open' => '09:00', 'close' => '17:00', 'enabled' => true );
	}
	foreach ( array( 'saturday', 'sunday' ) as $day ) {
		$working_hours[ $day ] = false;
	}
}

$settings_nonce = wp_create_nonce( 'grayfox_booking_settings' );
?>
<div class="wrap grayfox-wrap" id="grayfox-booking-settings">
	<h1><?php esc_html_e( 'Booking Settings', 'grayfox' ); ?></h1>

	<div id="grayfox-booking-settings-notice" class="notice" style="display:none;"></div>

	<!-- ================================================================
	     SERVICES
	     ================================================================ -->
	<h2><?php esc_html_e( 'Services', 'grayfox' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Define the services customers can book. Each service has a name, duration, and price.', 'grayfox' ); ?></p>

	<table class="wp-list-table widefat fixed striped" id="grayfox-services-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Service Name', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Duration (minutes)', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Price ($)', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Remove', 'grayfox' ); ?></th>
			</tr>
		</thead>
		<tbody id="grayfox-services-rows">
			<?php if ( empty( $services ) ) : ?>
				<tr class="grayfox-service-row">
					<td><input type="text" class="grayfox-service-name regular-text" placeholder="<?php esc_attr_e( 'e.g. Consultation', 'grayfox' ); ?>" value=""></td>
					<td><input type="number" class="grayfox-service-duration small-text" min="5" step="5" value="60"></td>
					<td><input type="number" class="grayfox-service-price small-text" min="0" step="0.01" value="0"></td>
					<td><button type="button" class="button button-small grayfox-remove-service"><?php esc_html_e( 'Remove', 'grayfox' ); ?></button></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $services as $svc ) : ?>
					<tr class="grayfox-service-row">
						<td><input type="text" class="grayfox-service-name regular-text" placeholder="<?php esc_attr_e( 'e.g. Consultation', 'grayfox' ); ?>" value="<?php echo esc_attr( $svc['name'] ?? '' ); ?>"></td>
						<td><input type="number" class="grayfox-service-duration small-text" min="5" step="5" value="<?php echo esc_attr( (string) ( $svc['duration_minutes'] ?? 60 ) ); ?>"></td>
						<td><input type="number" class="grayfox-service-price small-text" min="0" step="0.01" value="<?php echo esc_attr( (string) ( $svc['price'] ?? 0 ) ); ?>"></td>
						<td><button type="button" class="button button-small grayfox-remove-service"><?php esc_html_e( 'Remove', 'grayfox' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	<p>
		<button type="button" id="grayfox-add-service" class="button">
			<?php esc_html_e( '+ Add Service', 'grayfox' ); ?>
		</button>
	</p>

	<!-- ================================================================
	     WORKING HOURS
	     ================================================================ -->
	<h2><?php esc_html_e( 'Working Hours', 'grayfox' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Set your open and close times for each day. Untoggled days are treated as closed.', 'grayfox' ); ?></p>

	<table class="wp-list-table widefat fixed" id="grayfox-hours-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Day', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Enabled', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Open', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Close', 'grayfox' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $days_of_week as $day_key => $day_label ) :
				$day_config = $working_hours[ $day_key ] ?? false;
				$enabled    = is_array( $day_config ) && ! empty( $day_config['enabled'] );
				$open       = is_array( $day_config ) ? ( $day_config['open'] ?? '09:00' ) : '09:00';
				$close      = is_array( $day_config ) ? ( $day_config['close'] ?? '17:00' ) : '17:00';
			?>
				<tr class="grayfox-day-row">
					<td><strong><?php echo esc_html( $day_label ); ?></strong></td>
					<td>
						<label class="grayfox-toggle">
							<input
								type="checkbox"
								class="grayfox-day-enabled"
								data-day="<?php echo esc_attr( $day_key ); ?>"
								<?php checked( $enabled ); ?>
							>
							<span class="grayfox-toggle-slider"></span>
						</label>
					</td>
					<td>
						<input
							type="time"
							class="grayfox-day-open"
							data-day="<?php echo esc_attr( $day_key ); ?>"
							value="<?php echo esc_attr( $open ); ?>"
							<?php echo $enabled ? '' : 'disabled'; ?>
						>
					</td>
					<td>
						<input
							type="time"
							class="grayfox-day-close"
							data-day="<?php echo esc_attr( $day_key ); ?>"
							value="<?php echo esc_attr( $close ); ?>"
							<?php echo $enabled ? '' : 'disabled'; ?>
						>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- ================================================================
	     BUFFER TIME
	     ================================================================ -->
	<h2><?php esc_html_e( 'Buffer Time', 'grayfox' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Time added between consecutive appointments.', 'grayfox' ); ?></p>
	<select id="grayfox-buffer-minutes" name="buffer_minutes">
		<?php foreach ( array( 0, 15, 30, 45, 60 ) as $option ) : ?>
			<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $buffer_minutes, $option ); ?>>
				<?php
				if ( 0 === $option ) {
					esc_html_e( 'No buffer', 'grayfox' );
				} else {
					/* translators: %d: number of minutes */
					echo esc_html( sprintf( _n( '%d minute', '%d minutes', $option, 'grayfox' ), $option ) );
				}
				?>
			</option>
		<?php endforeach; ?>
	</select>

	<!-- ================================================================
	     CALENDAR ID
	     ================================================================ -->
	<h2><?php esc_html_e( 'Google Calendar', 'grayfox' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="grayfox-calendar-id"><?php esc_html_e( 'Calendar ID', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="grayfox-calendar-id"
					class="regular-text"
					value="<?php echo esc_attr( $calendar_id ); ?>"
					placeholder="primary"
				>
				<p class="description"><?php esc_html_e( 'Enter "primary" to use the connected account\'s default calendar, or paste a specific calendar ID.', 'grayfox' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="grayfox-booking-timezone"><?php esc_html_e( 'Booking Timezone', 'grayfox' ); ?></label>
			</th>
			<td>
				<select id="grayfox-booking-timezone" name="timezone">
					<?php
					$tz_list = timezone_identifiers_list();
					foreach ( $tz_list as $tz ) :
					?>
						<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $booking_timezone, $tz ); ?>>
							<?php echo esc_html( $tz ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Timezone used for all booking slots and calendar events.', 'grayfox' ); ?></p>
			</td>
		</tr>
	</table>

	<!-- ================================================================
	     SAVE BUTTON
	     ================================================================ -->
	<p class="submit">
		<button type="button" id="grayfox-save-booking-settings" class="button button-primary">
			<?php esc_html_e( 'Save Booking Settings', 'grayfox' ); ?>
		</button>
	</p>
</div>

<script>
( function () {
	'use strict';

	var ajaxUrl  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce    = '<?php echo esc_js( $settings_nonce ); ?>';
	var notice   = document.getElementById( 'grayfox-booking-settings-notice' );

	// ------------------------------------------------------------------
	// Service row template
	// ------------------------------------------------------------------
	var serviceRowTemplate = '<tr class="grayfox-service-row">'
		+ '<td><input type="text" class="grayfox-service-name regular-text" placeholder="<?php echo esc_js( __( 'e.g. Consultation', 'grayfox' ) ); ?>" value=""></td>'
		+ '<td><input type="number" class="grayfox-service-duration small-text" min="5" step="5" value="60"></td>'
		+ '<td><input type="number" class="grayfox-service-price small-text" min="0" step="0.01" value="0"></td>'
		+ '<td><button type="button" class="button button-small grayfox-remove-service"><?php echo esc_js( __( 'Remove', 'grayfox' ) ); ?></button></td>'
		+ '</tr>';

	// Add service row.
	document.getElementById( 'grayfox-add-service' ).addEventListener( 'click', function () {
		var tbody = document.getElementById( 'grayfox-services-rows' );
		var tmp   = document.createElement( 'tbody' );
		tmp.innerHTML = serviceRowTemplate;
		tbody.appendChild( tmp.firstElementChild );
	} );

	// Remove service row (delegated).
	document.getElementById( 'grayfox-services-rows' ).addEventListener( 'click', function ( e ) {
		if ( e.target && e.target.classList.contains( 'grayfox-remove-service' ) ) {
			var row = e.target.closest( 'tr' );
			if ( row ) {
				row.parentNode.removeChild( row );
			}
		}
	} );

	// ------------------------------------------------------------------
	// Toggle day enabled/disabled
	// ------------------------------------------------------------------
	document.querySelectorAll( '.grayfox-day-enabled' ).forEach( function ( checkbox ) {
		checkbox.addEventListener( 'change', function () {
			var row   = checkbox.closest( 'tr' );
			var open  = row.querySelector( '.grayfox-day-open' );
			var close = row.querySelector( '.grayfox-day-close' );
			if ( open  ) { open.disabled  = ! checkbox.checked; }
			if ( close ) { close.disabled = ! checkbox.checked; }
		} );
	} );

	// ------------------------------------------------------------------
	// Collect form data
	// ------------------------------------------------------------------
	function collectServices() {
		var rows     = document.querySelectorAll( '.grayfox-service-row' );
		var services = [];
		rows.forEach( function ( row ) {
			var name     = row.querySelector( '.grayfox-service-name' );
			var duration = row.querySelector( '.grayfox-service-duration' );
			var price    = row.querySelector( '.grayfox-service-price' );
			if ( name && name.value.trim() !== '' ) {
				services.push( {
					name:             name.value.trim(),
					duration_minutes: parseInt( duration ? duration.value : '60', 10 ),
					price:            parseFloat( price ? price.value : '0' ),
				} );
			}
		} );
		return services;
	}

	function collectWorkingHours() {
		var hours = {};
		document.querySelectorAll( '.grayfox-day-enabled' ).forEach( function ( checkbox ) {
			var day   = checkbox.dataset.day;
			var row   = checkbox.closest( 'tr' );
			var open  = row.querySelector( '.grayfox-day-open' );
			var close = row.querySelector( '.grayfox-day-close' );
			if ( checkbox.checked ) {
				hours[ day ] = {
					enabled: true,
					open:    open  ? open.value  : '09:00',
					close:   close ? close.value : '17:00',
				};
			} else {
				hours[ day ] = false;
			}
		} );
		return hours;
	}

	// ------------------------------------------------------------------
	// Show notice
	// ------------------------------------------------------------------
	function showNotice( message, type ) {
		notice.className     = 'notice notice-' + ( type || 'success' ) + ' is-dismissible';
		notice.style.display = 'block';
		var p = document.createElement( 'p' );
		p.textContent = message;
		notice.replaceChildren( p );
		setTimeout( function () { notice.style.display = 'none'; }, 5000 );
	}

	// ------------------------------------------------------------------
	// Save
	// ------------------------------------------------------------------
	document.getElementById( 'grayfox-save-booking-settings' ).addEventListener( 'click', function () {
		var btn      = this;
		btn.disabled = true;
		btn.textContent = '<?php echo esc_js( __( 'Saving\u2026', 'grayfox' ) ); ?>';

		var formData = new FormData();
		formData.append( 'action',        'grayfox_save_booking_settings' );
		formData.append( 'nonce',         nonce );
		formData.append( 'services',      JSON.stringify( collectServices() ) );
		formData.append( 'working_hours', JSON.stringify( collectWorkingHours() ) );

		var bufferEl = document.getElementById( 'grayfox-buffer-minutes' );
		formData.append( 'buffer_minutes', bufferEl ? bufferEl.value : '15' );

		var calendarEl = document.getElementById( 'grayfox-calendar-id' );
		formData.append( 'calendar_id', calendarEl ? calendarEl.value.trim() : 'primary' );

		var timezoneEl = document.getElementById( 'grayfox-booking-timezone' );
		formData.append( 'timezone', timezoneEl ? timezoneEl.value : '' );

		fetch( ajaxUrl, { method: 'POST', body: formData } )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				btn.disabled    = false;
				btn.textContent = '<?php echo esc_js( __( 'Save Booking Settings', 'grayfox' ) ); ?>';
				if ( data.success ) {
					showNotice( data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Settings saved.', 'grayfox' ) ); ?>', 'success' );
				} else {
					showNotice( data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Save failed. Please try again.', 'grayfox' ) ); ?>', 'error' );
				}
			} )
			.catch( function () {
				btn.disabled    = false;
				btn.textContent = '<?php echo esc_js( __( 'Save Booking Settings', 'grayfox' ) ); ?>';
				showNotice( '<?php echo esc_js( __( 'Network error. Please try again.', 'grayfox' ) ); ?>', 'error' );
			} );
	} );
} () );
</script>
