<?php
/**
 * Admin Conversations page template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

global $wpdb;

$conv_table = esc_sql( GrayFox_DB::get_table( 'conversations' ) );
$msg_table  = esc_sql( GrayFox_DB::get_table( 'messages' ) );

// Date range filter (default: last 30 days).
$date_from_raw = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$date_to_raw   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$date_from = ! empty( $date_from_raw ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from_raw )
	? $date_from_raw . ' 00:00:00'
	: gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

$date_to = ! empty( $date_to_raw ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to_raw )
	? $date_to_raw . ' 23:59:59'
	: gmdate( 'Y-m-d H:i:s' );

// Fetch conversations with message counts.
$conversations = $wpdb->get_results( $wpdb->prepare(
	"SELECT c.id, c.session_id, c.started_at, c.last_active_at,
			COUNT(m.id) AS message_count
	 FROM `{$conv_table}` c
	 LEFT JOIN `{$msg_table}` m ON m.conversation_id = c.id
	 WHERE c.started_at >= %s AND c.started_at <= %s
	 GROUP BY c.id
	 ORDER BY c.started_at DESC
	 LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$date_from,
	$date_to
), ARRAY_A );
?>
<div class="wrap grayfox-admin-wrap">
	<h1><?php esc_html_e( 'Conversations', 'kbfox' ); ?></h1>

	<!-- Date filter form -->
	<form method="get" class="grayfox-date-filter">
		<input type="hidden" name="page" value="grayfox-conversations" />
		<label>
			<?php esc_html_e( 'From:', 'kbfox' ); ?>
			<input type="date"
				   name="date_from"
				   value="<?php echo esc_attr( $date_from_raw ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'To:', 'kbfox' ); ?>
			<input type="date"
				   name="date_to"
				   value="<?php echo esc_attr( $date_to_raw ?: gmdate( 'Y-m-d' ) ); ?>" />
		</label>
		<?php submit_button( __( 'Filter', 'kbfox' ), 'secondary', '', false ); ?>
	</form>

	<?php if ( empty( $conversations ) ) : ?>
		<p><?php esc_html_e( 'No conversations found for the selected period.', 'kbfox' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped grayfox-conv-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Session ID', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Started', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Last Active', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Messages', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'kbfox' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $conversations as $conv ) :
					$conv_id = (int) $conv['id'];
					?>
					<tr class="grayfox-conv-row" data-conv-id="<?php echo esc_attr( $conv_id ); ?>">
						<td>
							<code><?php echo esc_html( substr( $conv['session_id'], 0, 16 ) . '...' ); ?></code>
						</td>
						<td><?php echo esc_html( $conv['started_at'] ); ?></td>
						<td><?php echo esc_html( $conv['last_active_at'] ?? '—' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $conv['message_count'] ) ); ?></td>
						<td>
							<button type="button"
									class="button button-small grayfox-view-conv"
									data-conv-id="<?php echo esc_attr( $conv_id ); ?>">
								<?php esc_html_e( 'View Messages', 'kbfox' ); ?>
							</button>
						</td>
					</tr>
					<!-- Message history row (hidden by default) -->
					<tr id="grayfox-conv-detail-<?php echo esc_attr( $conv_id ); ?>"
						class="grayfox-conv-detail"
						style="display:none;">
						<td colspan="5">
							<?php
							// Fetch messages for this conversation.
							$messages_for_conv = $wpdb->get_results( $wpdb->prepare(
								"SELECT role, content, created_at FROM `{$msg_table}` WHERE conversation_id = %d ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$conv_id
							), ARRAY_A );
							?>
							<div class="grayfox-message-history">
								<?php if ( empty( $messages_for_conv ) ) : ?>
									<p><?php esc_html_e( 'No messages.', 'kbfox' ); ?></p>
								<?php else : ?>
									<?php foreach ( $messages_for_conv as $msg ) : ?>
										<div class="grayfox-conv-message grayfox-conv-message--<?php echo esc_attr( $msg['role'] ); ?>">
											<span class="grayfox-conv-role"><?php echo esc_html( ucfirst( $msg['role'] ) ); ?></span>
											<span class="grayfox-conv-time"><?php echo esc_html( $msg['created_at'] ); ?></span>
											<p><?php echo esc_html( $msg['content'] ); ?></p>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		(function() {
			var buttons = document.querySelectorAll('.grayfox-view-conv');
			for (var i = 0; i < buttons.length; i++) {
				buttons[i].addEventListener('click', function() {
					var convId = this.getAttribute('data-conv-id');
					var detail = document.getElementById('grayfox-conv-detail-' + convId);
					if (detail) {
						if (detail.style.display === 'none') {
							detail.style.display = '';
							this.textContent = '<?php echo esc_js( __( 'Hide Messages', 'kbfox' ) ); ?>';
						} else {
							detail.style.display = 'none';
							this.textContent = '<?php echo esc_js( __( 'View Messages', 'kbfox' ) ); ?>';
						}
					}
				});
			}
		})();
		</script>
	<?php endif; ?>
</div>
