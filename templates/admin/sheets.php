<?php
/**
 * Admin page: Google Sheets Analytics.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sheets_instance    = GrayFox_Sheets::get_instance();
// NOTE: Keep this tier list in sync with GrayFox_Sheets::is_pro_or_above().
$tier               = GrayFox_License::get_verified_tier();
$is_pro             = in_array( $tier, array( 'pro' ), true );
$is_google_connected = GrayFox_Google::get_instance()->is_connected();
$spreadsheet_id     = (string) get_option( 'grayfox_sheets_spreadsheet_id', '' );
$default_range      = (string) get_option( 'grayfox_sheets_default_range', '' );
$scheduled_reports  = $sheets_instance->get_scheduled_reports();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Analytics', 'grayfox' ); ?></h1>

	<?php if ( ! $is_pro ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Google Sheets Analytics requires a Pro licence.', 'grayfox' ); ?>
				<a href="<?php echo esc_url( 'https://grayfox.io/pricing' ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'grayfox' ); ?></a>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( ! $is_google_connected ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'A Google account must be connected before using Sheets Analytics.', 'grayfox' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-google' ) ); ?>"><?php esc_html_e( 'Connect Google Account', 'grayfox' ); ?></a>
			</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<!-- =====================================================================
	     Section 1: Spreadsheet Settings
	     ===================================================================== -->
	<h2><?php esc_html_e( 'Spreadsheet Settings', 'grayfox' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="gf-sheets-spreadsheet-id"><?php esc_html_e( 'Spreadsheet ID', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sheets-spreadsheet-id"
					class="regular-text"
					value="<?php echo esc_attr( $spreadsheet_id ); ?>"
					placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sheets-default-range"><?php esc_html_e( 'Default Range', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sheets-default-range"
					class="regular-text"
					value="<?php echo esc_attr( $default_range ); ?>"
					placeholder="Sheet1!A1:Z100"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sheets-sheet-select"><?php esc_html_e( 'Available Sheets', 'grayfox' ); ?></label>
			</th>
			<td>
				<select id="gf-sheets-sheet-select">
					<option value=""><?php esc_html_e( '— load sheets first —', 'grayfox' ); ?></option>
				</select>
				<button type="button" id="gf-sheets-load-btn" class="button button-secondary">
					<?php esc_html_e( 'Load Sheets', 'grayfox' ); ?>
				</button>
			</td>
		</tr>
	</table>
	<p>
		<button type="button" id="gf-sheets-save-settings-btn" class="button button-primary">
			<?php esc_html_e( 'Save Settings', 'grayfox' ); ?>
		</button>
		<span id="gf-sheets-settings-msg" style="margin-left:10px;"></span>
	</p>

	<hr />

	<!-- =====================================================================
	     Section 2: Ask a Question
	     ===================================================================== -->
	<h2><?php esc_html_e( 'Ask a Question', 'grayfox' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="gf-sheets-query-spreadsheet-id"><?php esc_html_e( 'Spreadsheet ID', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sheets-query-spreadsheet-id"
					class="regular-text"
					value="<?php echo esc_attr( $spreadsheet_id ); ?>"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sheets-query-range"><?php esc_html_e( 'Range', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sheets-query-range"
					class="regular-text"
					value="<?php echo esc_attr( $default_range ); ?>"
					placeholder="Sheet1!A1:Z100"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sheets-question"><?php esc_html_e( 'Question', 'grayfox' ); ?></label>
			</th>
			<td>
				<textarea
					id="gf-sheets-question"
					rows="3"
					class="large-text"
					placeholder="<?php esc_attr_e( 'What were total sales last month?', 'grayfox' ); ?>"
				></textarea>
			</td>
		</tr>
	</table>
	<p>
		<button type="button" id="gf-sheets-analyze-btn" class="button button-primary">
			<?php esc_html_e( 'Analyze', 'grayfox' ); ?>
		</button>
	</p>
	<div id="gf-sheets-answer" style="background:#f9f9f9;border:1px solid #ddd;padding:12px;min-height:40px;white-space:pre-wrap;"></div>

	<hr />

	<!-- =====================================================================
	     Section 3: Scheduled Reports
	     ===================================================================== -->
	<h2><?php esc_html_e( 'Scheduled Reports', 'grayfox' ); ?></h2>

	<h3><?php esc_html_e( 'Schedule New Report', 'grayfox' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="gf-sched-spreadsheet-id"><?php esc_html_e( 'Spreadsheet ID', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sched-spreadsheet-id"
					class="regular-text"
					value="<?php echo esc_attr( $spreadsheet_id ); ?>"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sched-range"><?php esc_html_e( 'Range', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sched-range"
					class="regular-text"
					value="<?php echo esc_attr( $default_range ); ?>"
					placeholder="Sheet1!A1:Z100"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sched-question"><?php esc_html_e( 'Question', 'grayfox' ); ?></label>
			</th>
			<td>
				<textarea
					id="gf-sched-question"
					rows="3"
					class="large-text"
					placeholder="<?php esc_attr_e( 'What were total sales last month?', 'grayfox' ); ?>"
				></textarea>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sched-report-sheet"><?php esc_html_e( 'Report Sheet Name', 'grayfox' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="gf-sched-report-sheet"
					class="regular-text"
					value="<?php echo esc_attr( __( 'GrayFox Report', 'grayfox' ) ); ?>"
				/>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="gf-sched-frequency"><?php esc_html_e( 'Frequency', 'grayfox' ); ?></label>
			</th>
			<td>
				<select id="gf-sched-frequency">
					<option value="daily"><?php esc_html_e( 'Daily', 'grayfox' ); ?></option>
					<option value="weekly"><?php esc_html_e( 'Weekly', 'grayfox' ); ?></option>
				</select>
			</td>
		</tr>
	</table>
	<p>
		<button type="button" id="gf-sched-submit-btn" class="button button-primary">
			<?php esc_html_e( 'Schedule', 'grayfox' ); ?>
		</button>
		<span id="gf-sched-msg" style="margin-left:10px;"></span>
	</p>

	<h3><?php esc_html_e( 'Existing Scheduled Reports', 'grayfox' ); ?></h3>
	<table id="gf-sched-table" class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Spreadsheet ID', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Range', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Question', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Frequency', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Last Run', 'grayfox' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'grayfox' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $scheduled_reports ) ) : ?>
				<tr id="gf-sched-empty-row">
					<td colspan="6"><?php esc_html_e( 'No scheduled reports yet.', 'grayfox' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $scheduled_reports as $report ) : ?>
					<tr data-report-id="<?php echo esc_attr( $report['id'] ?? '' ); ?>">
						<td><?php echo esc_html( $report['spreadsheet_id'] ?? '' ); ?></td>
						<td><?php echo esc_html( $report['range'] ?? '' ); ?></td>
						<td><?php echo esc_html( $report['question'] ?? '' ); ?></td>
						<td><?php echo esc_html( $report['frequency'] ?? '' ); ?></td>
						<td><?php echo esc_html( $report['last_run'] ?? __( 'Never', 'grayfox' ) ); ?></td>
						<td>
							<button
								type="button"
								class="button button-small gf-delete-report-btn"
								data-report-id="<?php echo esc_attr( $report['id'] ?? '' ); ?>"
							><?php esc_html_e( 'Delete', 'grayfox' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
