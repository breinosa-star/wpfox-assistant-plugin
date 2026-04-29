<?php
/**
 * Admin Overview page template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Fetch stats.
$conv_table    = esc_sql( GrayFox_DB::get_table( 'conversations' ) );
$kb_table      = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );
$msg_table     = esc_sql( GrayFox_DB::get_table( 'messages' ) );
$seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

$conv_count_7d = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM `{$conv_table}` WHERE started_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$seven_days_ago
) );

$doc_count = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM `{$kb_table}`" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
);

$widget_enabled = get_option( 'grayfox_enable_widget', true );

// LLM usage stats — this month only.
$llm_provider  = get_option( 'grayfox_llm_provider', 'openai' );
$llm_model     = get_option( 'grayfox_llm_model', '' );
$llm_enc_key   = get_option( 'grayfox_llm_api_key', '' );
$llm_configured = ! empty( grayfox_decrypt( $llm_enc_key ) );

$llm_usage = null;
if ( $llm_configured && ! empty( $llm_model ) ) {
	$month_start = gmdate( 'Y-m-01 00:00:00' );

	// Sum character lengths per role for all messages this month.
	$usage_rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT m.role, SUM(CHAR_LENGTH(m.content)) AS total_chars
		 FROM `{$msg_table}` m
		 JOIN `{$conv_table}` c ON c.id = m.conversation_id
		 WHERE c.started_at >= %s
		 GROUP BY m.role", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$month_start
	), ARRAY_A );

	$chars_by_role = array( 'user' => 0, 'assistant' => 0 );
	foreach ( (array) $usage_rows as $row ) {
		if ( isset( $chars_by_role[ $row['role'] ] ) ) {
			$chars_by_role[ $row['role'] ] = (int) $row['total_chars'];
		}
	}

	// Estimate tokens: ~4.5 chars per token.
	$tokens_input  = (int) round( $chars_by_role['user']      / 4.5 );
	$tokens_output = (int) round( $chars_by_role['assistant'] / 4.5 );

	$pricing = GrayFox_Settings::get_model_pricing( $llm_model );

	$cost_est = null;
	if ( $pricing ) {
		$cost_est = ( $tokens_input / 1_000_000 ) * $pricing['input_per_1m']
		          + ( $tokens_output / 1_000_000 ) * $pricing['output_per_1m'];
	}

	$dashboard_url = GrayFox_Settings::get_provider_dashboard_url( $llm_provider );

	$llm_usage = compact(
		'tokens_input', 'tokens_output', 'cost_est', 'pricing', 'dashboard_url'
	);
}
?>
<div class="wrap grayfox-admin-wrap">
	<h1><?php esc_html_e( 'GrayFox AI — Overview', 'grayfox' ); ?></h1>

	<div class="grayfox-stats-grid">

		<!-- Chatbot Status -->
		<div class="grayfox-stat-card">
			<h3><?php esc_html_e( 'Widget Status', 'grayfox' ); ?></h3>
			<?php if ( $widget_enabled ) : ?>
				<span class="grayfox-badge grayfox-badge--success"><?php esc_html_e( 'Enabled', 'grayfox' ); ?></span>
			<?php else : ?>
				<span class="grayfox-badge grayfox-badge--warning"><?php esc_html_e( 'Disabled', 'grayfox' ); ?></span>
			<?php endif; ?>
		</div>

		<!-- Conversations (7 days) -->
		<div class="grayfox-stat-card">
			<h3><?php esc_html_e( 'Conversations (last 7 days)', 'grayfox' ); ?></h3>
			<p class="grayfox-stat-number"><?php echo esc_html( number_format_i18n( $conv_count_7d ) ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-conversations' ) ); ?>">
					<?php esc_html_e( 'View all conversations', 'grayfox' ); ?>
				</a>
			</p>
		</div>

		<!-- Knowledge Base -->
		<div class="grayfox-stat-card">
			<h3><?php esc_html_e( 'Knowledge Base', 'grayfox' ); ?></h3>
			<p class="grayfox-stat-number">
				<?php
				/* translators: %d: number of documents */
				printf( esc_html__( '%d document(s)', 'grayfox' ), absint( $doc_count ) );
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-knowledge-base' ) ); ?>">
					<?php esc_html_e( 'Manage knowledge base', 'grayfox' ); ?>
				</a>
			</p>
		</div>

		<!-- LLM Usage (this month) -->
		<div class="grayfox-stat-card">
			<h3><?php esc_html_e( 'LLM Usage (this month)', 'grayfox' ); ?></h3>
			<?php if ( ! $llm_configured ) : ?>
				<span class="grayfox-badge grayfox-badge--warning"><?php esc_html_e( 'No LLM configured', 'grayfox' ); ?></span>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-settings' ) ); ?>">
						<?php esc_html_e( 'Configure LLM provider', 'grayfox' ); ?>
					</a>
				</p>
			<?php elseif ( null === $llm_usage ) : ?>
				<span class="grayfox-badge grayfox-badge--warning"><?php esc_html_e( 'No model selected', 'grayfox' ); ?></span>
			<?php else : ?>
				<p>
					<?php esc_html_e( 'Provider:', 'grayfox' ); ?>
					<strong><?php echo esc_html( ucfirst( $llm_provider ) ); ?></strong>
					&nbsp;|&nbsp;
					<?php esc_html_e( 'Model:', 'grayfox' ); ?>
					<strong><?php echo esc_html( $llm_model ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'Est. input tokens:', 'grayfox' ); ?>
					<strong><?php echo esc_html( number_format_i18n( $llm_usage['tokens_input'] ) ); ?></strong>
					<span class="grayfox-muted">(±20%)</span>
				</p>
				<p>
					<?php esc_html_e( 'Est. output tokens:', 'grayfox' ); ?>
					<strong><?php echo esc_html( number_format_i18n( $llm_usage['tokens_output'] ) ); ?></strong>
					<span class="grayfox-muted">(±20%)</span>
				</p>
				<?php if ( null !== $llm_usage['cost_est'] ) : ?>
					<p>
						<?php esc_html_e( 'Est. cost:', 'grayfox' ); ?>
						<strong>
							$<?php echo esc_html( number_format( $llm_usage['cost_est'], 4 ) ); ?>
						</strong>
						<span class="grayfox-muted">(±25%)</span>
					</p>
					<?php if ( ! empty( $llm_usage['pricing']['verified_date'] ) ) : ?>
						<p class="grayfox-muted grayfox-usage-disclaimer">
							<?php
							printf(
								/* translators: %s: pricing verification date */
								esc_html__( 'Prices verified %s. Estimates only — see provider for actual usage.', 'grayfox' ),
								esc_html( $llm_usage['pricing']['verified_date'] )
							);
							?>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p class="grayfox-muted"><?php esc_html_e( 'Pricing unavailable for this model.', 'grayfox' ); ?></p>
				<?php endif; ?>
				<p>
					<a href="<?php echo esc_url( $llm_usage['dashboard_url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'View actual usage →', 'grayfox' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

	</div><!-- .grayfox-stats-grid -->

	<div class="grayfox-quick-links">
		<h2><?php esc_html_e( 'Quick Links', 'grayfox' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-settings' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Settings', 'grayfox' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-knowledge-base' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Knowledge Base', 'grayfox' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-conversations' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Conversations', 'grayfox' ); ?>
			</a>
		</p>
	</div>

	<div class="grayfox-pro-features" style="margin-top:24px;padding:20px 24px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;opacity:0.75;">
		<h2 style="margin-top:0;color:#555;"><?php esc_html_e( 'Pro Features', 'grayfox' ); ?></h2>
		<p style="color:#666;"><?php esc_html_e( 'Extend GrayFox with the paid add-on:', 'grayfox' ); ?></p>
		<ul style="color:#666;margin-left:1.5em;list-style:disc;">
			<li><?php esc_html_e( 'Google Connect — OAuth integration with Google Calendar and Google Drive', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Appointments — AI-assisted booking system with calendar sync', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Drive Sync — automatically import Google Drive documents into your knowledge base', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Analytics — export conversation reports to Google Sheets on a schedule', 'grayfox' ); ?></li>
		</ul>
		<p style="margin-top:16px;">
			<a href="https://grayfox.io" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php esc_html_e( 'Get the Pro add-on at grayfox.io →', 'grayfox' ); ?>
			</a>
		</p>
	</div>

</div>
