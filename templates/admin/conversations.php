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

$grayfox_conv_table = esc_sql( GrayFox_DB::get_table( 'conversations' ) );
$grayfox_msg_table  = esc_sql( GrayFox_DB::get_table( 'messages' ) );

// Date range filter (default: last 30 days).
$grayfox_date_from_raw = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$grayfox_date_to_raw   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$grayfox_date_from = ! empty( $grayfox_date_from_raw ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $grayfox_date_from_raw )
	? $grayfox_date_from_raw . ' 00:00:00'
	: gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

$grayfox_date_to = ! empty( $grayfox_date_to_raw ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $grayfox_date_to_raw )
	? $grayfox_date_to_raw . ' 23:59:59'
	: gmdate( 'Y-m-d H:i:s' );

// Fetch conversations with message counts.
$grayfox_conversations = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"SELECT c.id, c.session_id, c.started_at, c.last_active_at,
			COUNT(m.id) AS message_count
	 FROM %i c
	 LEFT JOIN %i m ON m.conversation_id = c.id
	 WHERE c.started_at >= %s AND c.started_at <= %s
	 GROUP BY c.id
	 ORDER BY c.started_at DESC
	 LIMIT 100",
	$grayfox_conv_table,
	$grayfox_msg_table,
	$grayfox_date_from,
	$grayfox_date_to
), ARRAY_A );

// Fetch leads: conversations with at least a name, email, or phone, filtered by the same date range.
$grayfox_leads = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"SELECT id, visitor_name, visitor_email, visitor_phone, started_at, last_active_at, message_count
	 FROM %i
	 WHERE started_at >= %s AND started_at <= %s
	   AND ( ( visitor_name IS NOT NULL AND visitor_name <> '' )
	      OR ( visitor_email IS NOT NULL AND visitor_email <> '' )
	      OR ( visitor_phone IS NOT NULL AND visitor_phone <> '' ) )
	 ORDER BY started_at DESC
	 LIMIT 200",
	$grayfox_conv_table,
	$grayfox_date_from,
	$grayfox_date_to
), ARRAY_A );
?>
<div class="wrap grayfox-admin-wrap">
	<h1><?php esc_html_e( 'Interactions', 'kbfox' ); ?></h1>

	<!-- Date filter form -->
	<form method="get" class="grayfox-date-filter">
		<input type="hidden" name="page" value="grayfox-conversations" />
		<label>
			<?php esc_html_e( 'From:', 'kbfox' ); ?>
			<input type="date"
				   name="date_from"
				   value="<?php echo esc_attr( $grayfox_date_from_raw ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>" />
		</label>
		<label>
			<?php esc_html_e( 'To:', 'kbfox' ); ?>
			<input type="date"
				   name="date_to"
				   value="<?php echo esc_attr( $grayfox_date_to_raw ?: gmdate( 'Y-m-d' ) ); ?>" />
		</label>
		<?php submit_button( __( 'Filter', 'kbfox' ), 'secondary', '', false ); ?>
	</form>

	<?php if ( empty( $grayfox_conversations ) ) : ?>
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
				<?php foreach ( $grayfox_conversations as $grayfox_conv ) :
					$grayfox_conv_id = (int) $grayfox_conv['id'];
					?>
					<tr class="grayfox-conv-row" data-conv-id="<?php echo esc_attr( $grayfox_conv_id ); ?>">
						<td>
							<code><?php echo esc_html( substr( $grayfox_conv['session_id'], 0, 16 ) . '...' ); ?></code>
						</td>
						<td><?php echo esc_html( $grayfox_conv['started_at'] ); ?></td>
						<td><?php echo esc_html( $grayfox_conv['last_active_at'] ?? '—' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $grayfox_conv['message_count'] ) ); ?></td>
						<td>
							<button type="button"
									class="button button-small grayfox-view-conv"
									data-conv-id="<?php echo esc_attr( $grayfox_conv_id ); ?>">
								<?php esc_html_e( 'View Messages', 'kbfox' ); ?>
							</button>
						</td>
					</tr>
					<!-- Message history row (hidden by default) -->
					<tr id="grayfox-conv-detail-<?php echo esc_attr( $grayfox_conv_id ); ?>"
						class="grayfox-conv-detail"
						style="display:none;">
						<td colspan="5">
							<?php
							// Fetch messages for this conversation.
							$grayfox_messages_for_conv = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								"SELECT role, content, created_at FROM `{$grayfox_msg_table}` WHERE conversation_id = %d ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$grayfox_conv_id
							), ARRAY_A );
							?>
							<div class="grayfox-message-history">
								<?php if ( empty( $grayfox_messages_for_conv ) ) : ?>
									<p><?php esc_html_e( 'No messages.', 'kbfox' ); ?></p>
								<?php else : ?>
									<?php foreach ( $grayfox_messages_for_conv as $grayfox_msg ) : ?>
										<div class="grayfox-conv-message grayfox-conv-message--<?php echo esc_attr( $grayfox_msg['role'] ); ?>">
											<span class="grayfox-conv-role"><?php echo esc_html( ucfirst( $grayfox_msg['role'] ) ); ?></span>
											<span class="grayfox-conv-time"><?php echo esc_html( $grayfox_msg['created_at'] ); ?></span>
											<p><?php echo esc_html( $grayfox_msg['content'] ); ?></p>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php endif; ?>

	<hr style="margin:40px 0 24px;" />

	<h2><?php esc_html_e( 'Leads', 'kbfox' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Visitors who shared their name, email, or phone during a conversation.', 'kbfox' ); ?></p>

	<?php if ( empty( $grayfox_leads ) ) : ?>
		<p><?php esc_html_e( 'No leads found for the selected period.', 'kbfox' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped grayfox-leads-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Email', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Date', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Last Active', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Messages', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'kbfox' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $grayfox_leads as $grayfox_lead ) :
					$grayfox_lead_id = (int) $grayfox_lead['id'];
					?>
					<tr>
						<td><?php echo esc_html( $grayfox_lead['visitor_name'] ?: '—' ); ?></td>
						<td>
							<?php if ( ! empty( $grayfox_lead['visitor_email'] ) ) : ?>
								<a href="mailto:<?php echo esc_attr( $grayfox_lead['visitor_email'] ); ?>">
									<?php echo esc_html( $grayfox_lead['visitor_email'] ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $grayfox_lead['visitor_phone'] ) ) : ?>
								<a href="tel:<?php echo esc_attr( $grayfox_lead['visitor_phone'] ); ?>">
									<?php echo esc_html( $grayfox_lead['visitor_phone'] ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $grayfox_lead['started_at'] ); ?></td>
						<td><?php echo esc_html( $grayfox_lead['last_active_at'] ?? '—' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $grayfox_lead['message_count'] ) ); ?></td>
						<td>
							<button type="button"
									class="button button-small grayfox-view-conv"
									data-conv-id="<?php echo esc_attr( $grayfox_lead_id ); ?>">
								<?php esc_html_e( 'View Messages', 'kbfox' ); ?>
							</button>
						</td>
					</tr>
					<tr id="grayfox-conv-detail-<?php echo esc_attr( $grayfox_lead_id ); ?>"
						class="grayfox-conv-detail"
						style="display:none;">
						<td colspan="7">
							<?php
							$grayfox_lead_messages = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								"SELECT role, content, created_at FROM `{$grayfox_msg_table}` WHERE conversation_id = %d ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
								$grayfox_lead_id
							), ARRAY_A );
							?>
							<div class="grayfox-message-history">
								<?php if ( empty( $grayfox_lead_messages ) ) : ?>
									<p><?php esc_html_e( 'No messages.', 'kbfox' ); ?></p>
								<?php else : ?>
									<?php foreach ( $grayfox_lead_messages as $grayfox_msg ) : ?>
										<div class="grayfox-conv-message grayfox-conv-message--<?php echo esc_attr( $grayfox_msg['role'] ); ?>">
											<span class="grayfox-conv-role"><?php echo esc_html( ucfirst( $grayfox_msg['role'] ) ); ?></span>
											<span class="grayfox-conv-time"><?php echo esc_html( $grayfox_msg['created_at'] ); ?></span>
											<p><?php echo esc_html( $grayfox_msg['content'] ); ?></p>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
