<?php
/**
 * Admin Appointments page — list and manage appointments.
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
		<h1><?php esc_html_e( 'Appointments', 'grayfox' ); ?></h1>
		<div class="grayfox-upgrade-notice">
			<p><?php esc_html_e( 'Appointments are available on the Growth plan and above.', 'grayfox' ); ?></p>
			<a href="https://grayfox.ai/pricing" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php esc_html_e( 'Upgrade to Growth', 'grayfox' ); ?>
			</a>
		</div>
	</div>
	<?php
	return;
}

// Date range defaults (current week).
$today      = current_time( 'Y-m-d' );
$week_start = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $today ) ) );
$week_end   = gmdate( 'Y-m-d', strtotime( 'sunday this week', strtotime( $today ) ) );

$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : $week_start;
$date_to   = isset( $_GET['date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) )   : $week_end;
$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : '';

$filters = array(
	'date_from' => $date_from,
	'date_to'   => $date_to,
);

if ( ! empty( $status_filter ) ) {
	$filters['status'] = $status_filter;
}

$appointments = $booking->get_appointments( $filters );

$cancel_nonce = wp_create_nonce( 'grayfox_cancel_booking' );
?>
<div class="wrap grayfox-wrap">
	<h1><?php esc_html_e( 'Appointments', 'grayfox' ); ?></h1>

	<?php if ( isset( $_GET['cancelled'] ) && '1' === $_GET['cancelled'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Appointment cancelled successfully.', 'grayfox' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['cancel_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Failed to cancel the appointment. Please try again.', 'grayfox' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Date range + status filter -->
	<div class="grayfox-appointments-filter">
		<form method="get" action="">
			<input type="hidden" name="page" value="grayfox-appointments">
			<label for="grayfox-date-from"><?php esc_html_e( 'From', 'grayfox' ); ?></label>
			<input
				type="date"
				id="grayfox-date-from"
				name="date_from"
				value="<?php echo esc_attr( $date_from ); ?>"
			>
			<label for="grayfox-date-to"><?php esc_html_e( 'To', 'grayfox' ); ?></label>
			<input
				type="date"
				id="grayfox-date-to"
				name="date_to"
				value="<?php echo esc_attr( $date_to ); ?>"
			>
			<label for="grayfox-status-filter"><?php esc_html_e( 'Status', 'grayfox' ); ?></label>
			<select id="grayfox-status-filter" name="status_filter">
				<option value="" <?php selected( '', $status_filter ); ?>><?php esc_html_e( 'All', 'grayfox' ); ?></option>
				<option value="confirmed"  <?php selected( 'confirmed',  $status_filter ); ?>><?php esc_html_e( 'Confirmed',  'grayfox' ); ?></option>
				<option value="pending"    <?php selected( 'pending',    $status_filter ); ?>><?php esc_html_e( 'Pending',    'grayfox' ); ?></option>
				<option value="cancelled"  <?php selected( 'cancelled',  $status_filter ); ?>><?php esc_html_e( 'Cancelled',  'grayfox' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'grayfox' ); ?></button>
		</form>
	</div>

	<!-- Appointments table -->
	<?php if ( empty( $appointments ) ) : ?>
		<div class="grayfox-empty-state">
			<p><?php esc_html_e( 'No appointments found for the selected date range.', 'grayfox' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped grayfox-appointments-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Customer', 'grayfox' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Service', 'grayfox' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Start', 'grayfox' ); ?></th>
					<th scope="col"><?php esc_html_e( 'End', 'grayfox' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'grayfox' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'grayfox' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $appointments as $appt ) : ?>
					<tr data-appointment-id="<?php echo esc_attr( (string) $appt->id ); ?>">
						<td>
							<strong><?php echo esc_html( $appt->customer_name ); ?></strong><br>
							<small><?php echo esc_html( $appt->customer_email ); ?></small>
						</td>
						<td><?php echo esc_html( $appt->service ); ?></td>
						<td><?php echo esc_html( $appt->start_time ); ?></td>
						<td><?php echo esc_html( $appt->end_time ); ?></td>
						<td>
							<?php
							$status       = $appt->status;
							$status_class = match ( $status ) {
								'confirmed' => 'grayfox-badge grayfox-badge--green',
								'cancelled' => 'grayfox-badge grayfox-badge--red',
								default     => 'grayfox-badge grayfox-badge--yellow',
							};
							$status_label = match ( $status ) {
								'confirmed' => __( 'Confirmed', 'grayfox' ),
								'cancelled' => __( 'Cancelled', 'grayfox' ),
								default     => __( 'Pending', 'grayfox' ),
							};
							?>
							<span class="<?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</td>
						<td>
							<?php if ( 'cancelled' !== $appt->status ) : ?>
								<button
									class="button button-small grayfox-cancel-appointment"
									data-appointment-id="<?php echo esc_attr( (string) $appt->id ); ?>"
									data-nonce="<?php echo esc_attr( $cancel_nonce ); ?>"
									data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
								>
									<?php esc_html_e( 'Cancel', 'grayfox' ); ?>
								</button>
							<?php else : ?>
								<span class="grayfox-text-muted"><?php esc_html_e( 'Cancelled', 'grayfox' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<script>
( function () {
	'use strict';

	document.querySelectorAll( '.grayfox-cancel-appointment' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! confirm( '<?php echo esc_js( __( 'Cancel this appointment? The customer will be notified.', 'grayfox' ) ); ?>' ) ) {
				return;
			}

			var appointmentId = btn.dataset.appointmentId;
			var nonce         = btn.dataset.nonce;
			var ajaxUrl       = btn.dataset.ajaxUrl;

			btn.disabled    = true;
			btn.textContent = '<?php echo esc_js( __( 'Cancelling\u2026', 'grayfox' ) ); ?>';

			var formData = new FormData();
			formData.append( 'action',         'grayfox_cancel_booking' );
			formData.append( 'nonce',          nonce );
			formData.append( 'appointment_id', appointmentId );

			fetch( ajaxUrl, { method: 'POST', body: formData } )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						var row = btn.closest( 'tr' );
						if ( row ) {
							var statusCell  = row.querySelector( 'td:nth-child(5)' );
							var actionsCell = row.querySelector( 'td:nth-child(6)' );
							if ( statusCell ) {
								var badge = document.createElement( 'span' );
								badge.className = 'grayfox-badge grayfox-badge--red';
								badge.textContent = '<?php echo esc_js( __( 'Cancelled', 'grayfox' ) ); ?>';
								statusCell.replaceChildren( badge );
							}
							if ( actionsCell ) {
								var muted = document.createElement( 'span' );
								muted.className = 'grayfox-text-muted';
								muted.textContent = '<?php echo esc_js( __( 'Cancelled', 'grayfox' ) ); ?>';
								actionsCell.replaceChildren( muted );
							}
						}
					} else {
						alert( data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Cancel failed. Please try again.', 'grayfox' ) ); ?>' );
						btn.disabled    = false;
						btn.textContent = '<?php echo esc_js( __( 'Cancel', 'grayfox' ) ); ?>';
					}
				} )
				.catch( function () {
					alert( '<?php echo esc_js( __( 'Network error. Please try again.', 'grayfox' ) ); ?>' );
					btn.disabled    = false;
					btn.textContent = '<?php echo esc_js( __( 'Cancel', 'grayfox' ) ); ?>';
				} );
		} );
	} );
} ()  );
</script>
