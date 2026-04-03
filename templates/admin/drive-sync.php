<?php
/**
 * Drive Sync admin page template.
 *
 * Lets admins connect a Google Drive folder, select files for the knowledge
 * base, and manually trigger or monitor the incremental sync.
 *
 * Gated: Growth and Pro tiers only. Starter tier sees an upgrade CTA.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$tier          = (string) get_option( 'grayfox_license_tier', 'starter' );
$is_growth     = in_array( $tier, array( 'growth', 'pro' ), true );
$google        = GrayFox_Google::get_instance();
$is_connected  = $google->is_connected();
$folder_id     = (string) get_option( 'grayfox_drive_folder_id', '' );
$last_sync     = (string) get_option( 'grayfox_drive_last_sync', '' );

$sync_statuses = array();
if ( $is_growth && $is_connected ) {
	$sync_statuses = GrayFox_Drive::get_instance()->get_sync_status();
}

// Next scheduled run via Action Scheduler.
$next_run = null;
if ( function_exists( 'as_next_scheduled_action' ) ) {
	$next_ts  = as_next_scheduled_action( GrayFox_Drive::AS_HOOK_DAILY, array(), 'grayfox' );
	$next_run = $next_ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_ts ) : null;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Drive Sync', 'grayfox' ); ?></h1>

	<?php if ( ! $is_growth ) : ?>
	<!-- ============================================================ -->
	<!-- Upgrade CTA — Starter tier                                   -->
	<!-- ============================================================ -->
	<div class="notice notice-warning inline">
		<p>
			<strong><?php esc_html_e( 'Growth or Pro licence required.', 'grayfox' ); ?></strong>
			<?php esc_html_e( 'Google Drive / Docs auto-sync is available on the Growth and Pro tiers. Upgrade your licence to enable this feature.', 'grayfox' ); ?>
		</p>
		<p>
			<a href="https://grayfox.ai/pricing" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php esc_html_e( 'Upgrade Licence', 'grayfox' ); ?>
			</a>
		</p>
	</div>
	<?php return; // Nothing else to render for Starter users. ?>
	<?php endif; ?>

	<?php if ( ! $is_connected ) : ?>
	<!-- ============================================================ -->
	<!-- Google not connected                                         -->
	<!-- ============================================================ -->
	<div class="notice notice-error inline">
		<p>
			<?php
			printf(
				/* translators: %s: URL to Google Connect page */
				esc_html__( 'Google account is not connected. %s before using Drive Sync.', 'grayfox' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=grayfox-google' ) ) . '">' . esc_html__( 'Connect your Google account', 'grayfox' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php return; ?>
	<?php endif; ?>

	<!-- ============================================================ -->
	<!-- Section 1 — Folder Setup                                     -->
	<!-- ============================================================ -->
	<div class="grayfox-card" style="max-width:860px;margin-top:20px;">
		<h2><?php esc_html_e( 'Folder Setup', 'grayfox' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Enter a Google Drive folder ID, click Load Files, then tick the documents you want to include in the knowledge base.', 'grayfox' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="grayfox-drive-folder-id"><?php esc_html_e( 'Drive Folder ID', 'grayfox' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="grayfox-drive-folder-id"
						name="grayfox_drive_folder_id"
						value="<?php echo esc_attr( $folder_id ); ?>"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms', 'grayfox' ); ?>"
					/>
					<button type="button" id="grayfox-drive-load-files" class="button button-secondary" style="margin-left:8px;">
						<?php esc_html_e( 'Load Files', 'grayfox' ); ?>
					</button>
					<p class="description">
						<?php
						printf(
							/* translators: %s: Google Drive URL */
							esc_html__( 'Find the folder ID in the URL when you open a folder in %s.', 'grayfox' ),
							'<a href="https://drive.google.com" target="_blank" rel="noopener noreferrer">Google Drive</a>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<!-- File list (populated via AJAX after Load Files click) -->
		<div id="grayfox-drive-file-list" style="display:none;margin-top:16px;">
			<h3 style="margin-bottom:8px;"><?php esc_html_e( 'Files in Folder', 'grayfox' ); ?></h3>
			<p id="grayfox-drive-file-list-loading" style="display:none;">
				<?php esc_html_e( 'Loading files\u2026', 'grayfox' ); ?>
			</p>
			<p id="grayfox-drive-file-list-empty" style="display:none;">
				<?php esc_html_e( 'No supported files found in this folder.', 'grayfox' ); ?>
			</p>
			<p id="grayfox-drive-file-list-error" style="display:none;color:#cc1818;"></p>
			<table id="grayfox-drive-files-table" class="wp-list-table widefat fixed striped" style="display:none;">
				<thead>
					<tr>
						<th style="width:30px;">
							<input type="checkbox" id="grayfox-drive-select-all" title="<?php esc_attr_e( 'Select all', 'grayfox' ); ?>" />
						</th>
						<th><?php esc_html_e( 'File Name', 'grayfox' ); ?></th>
						<th><?php esc_html_e( 'Type', 'grayfox' ); ?></th>
						<th><?php esc_html_e( 'Last Modified', 'grayfox' ); ?></th>
					</tr>
				</thead>
				<tbody id="grayfox-drive-files-tbody">
					<!-- Rows injected by JS -->
				</tbody>
			</table>

			<p style="margin-top:12px;">
				<button type="button" id="grayfox-drive-save-selection" class="button button-primary">
					<?php esc_html_e( 'Save Selection', 'grayfox' ); ?>
				</button>
				<span id="grayfox-drive-save-status" style="margin-left:10px;display:none;"></span>
			</p>
		</div>
	</div>

	<!-- ============================================================ -->
	<!-- Section 2 — Sync Status                                      -->
	<!-- ============================================================ -->
	<div class="grayfox-card" style="max-width:860px;margin-top:24px;">
		<h2><?php esc_html_e( 'Sync Status', 'grayfox' ); ?></h2>

		<p>
			<?php
			if ( ! empty( $last_sync ) ) {
				printf(
					/* translators: %s: formatted datetime */
					esc_html__( 'Last synced: %s', 'grayfox' ),
					'<strong>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sync ) ) ) . '</strong>'
				);
			} else {
				esc_html_e( 'Never synced.', 'grayfox' );
			}
			?>
		</p>

		<?php if ( ! empty( $sync_statuses ) ) : ?>
		<table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File Name', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Status', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Last Synced', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'grayfox' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sync_statuses as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item['file_name'] ?? $item['file_id'] ); ?></td>
					<td>
						<?php
						$status = $item['status'] ?? 'never';
						$badge_map = array(
							'synced'  => array( '#00a32a', 'Synced' ),
							'pending' => array( '#996800', 'Pending' ),
							'never'   => array( '#72777c', 'Never' ),
						);
						$badge = $badge_map[ $status ] ?? array( '#72777c', $status );
						printf(
							'<span style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">%s</span>',
							esc_attr( $badge[0] ),
							esc_html( $badge[1] )
						);
						?>
					</td>
					<td>
						<?php
						if ( ! empty( $item['last_synced'] ) ) {
							echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['last_synced'] ) ) );
						} else {
							esc_html_e( '&mdash;', 'grayfox' );
						}
						?>
					</td>
					<td>
						<button
							type="button"
							class="button button-secondary grayfox-drive-resync-btn"
							data-file-id="<?php echo esc_attr( $item['file_id'] ); ?>"
						>
							<?php esc_html_e( 'Re-sync', 'grayfox' ); ?>
						</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php elseif ( ! empty( get_option( 'grayfox_drive_selected_files', '[]' ) ) && '[]' !== get_option( 'grayfox_drive_selected_files', '[]' ) ) : ?>
		<p><?php esc_html_e( 'Selected files have not been synced yet.', 'grayfox' ); ?></p>
		<?php else : ?>
		<p><?php esc_html_e( 'No files selected. Use the Folder Setup section above to select files.', 'grayfox' ); ?></p>
		<?php endif; ?>

		<p style="margin-top:16px;">
			<button type="button" id="grayfox-drive-sync-now" class="button button-primary">
				<?php esc_html_e( 'Sync All Now', 'grayfox' ); ?>
			</button>
			<span id="grayfox-drive-sync-result" style="margin-left:10px;display:none;"></span>
		</p>
	</div>

	<!-- ============================================================ -->
	<!-- Section 3 — Schedule                                         -->
	<!-- ============================================================ -->
	<div class="grayfox-card" style="max-width:860px;margin-top:24px;">
		<h2><?php esc_html_e( 'Sync Schedule', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'Files sync automatically once per day via Action Scheduler.', 'grayfox' ); ?></p>
		<?php if ( $next_run ) : ?>
		<p>
			<?php
			printf(
				/* translators: %s: formatted next run datetime */
				esc_html__( 'Next scheduled run: %s', 'grayfox' ),
				'<strong>' . esc_html( $next_run ) . '</strong>'
			);
			?>
		</p>
		<?php else : ?>
		<p><?php esc_html_e( 'No scheduled run found. The schedule will be created the next time the plugin initialises.', 'grayfox' ); ?></p>
		<?php endif; ?>
	</div>
</div>
