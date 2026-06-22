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
$grayfox_conv_table     = GrayFox_DB::get_table( 'conversations' );
$grayfox_kb_table       = GrayFox_DB::get_table( 'knowledge_base' );
$grayfox_msg_table      = GrayFox_DB::get_table( 'messages' );
$grayfox_seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

$grayfox_conv_count_7d = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"SELECT COUNT(*) FROM %i WHERE started_at >= %s",
	$grayfox_conv_table,
	$grayfox_seven_days_ago
) );

$grayfox_doc_count = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	"SELECT COUNT(*) FROM %i",
	$grayfox_kb_table
) );

$grayfox_widget_enabled = get_option( 'grayfox_enable_widget', true );
$grayfox_api_enabled    = (bool) get_option( 'grayfox_public_kb_api_enabled', false );

// API usage stats (last 7 days).
$grayfox_api_stats     = null;
$grayfox_api_log_table = GrayFox_DB::get_table( 'api_log' );
$grayfox_table_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $grayfox_api_log_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
if ( $grayfox_table_exists ) {
	$grayfox_api_total = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT COUNT(*) FROM %i WHERE created_at >= %s",
		$grayfox_api_log_table,
		$grayfox_seven_days_ago
	) );
	$grayfox_api_ai_calls = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT COUNT(*) FROM %i WHERE created_at >= %s AND is_ai_agent = 1",
		$grayfox_api_log_table,
		$grayfox_seven_days_ago
	) );
	$grayfox_api_avg_time = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT AVG(response_time_ms) FROM %i WHERE created_at >= %s",
		$grayfox_api_log_table,
		$grayfox_seven_days_ago
	) );
	$grayfox_api_top_queries = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT query FROM %i WHERE created_at >= %s AND query != '' GROUP BY query ORDER BY COUNT(*) DESC LIMIT 3",
		$grayfox_api_log_table,
		$grayfox_seven_days_ago
	) );
	$grayfox_api_stats = array(
		'api_total'       => $grayfox_api_total,
		'api_ai_calls'    => $grayfox_api_ai_calls,
		'api_avg_time'    => $grayfox_api_avg_time,
		'api_top_queries' => $grayfox_api_top_queries,
	);
}

// LLM usage stats — this month only.
$grayfox_llm_provider   = get_option( 'grayfox_llm_provider', 'openai' );
$grayfox_llm_model      = get_option( 'grayfox_llm_model', '' );
$grayfox_llm_enc_key    = get_option( 'grayfox_llm_api_key', '' );
$grayfox_llm_configured = ! empty( grayfox_decrypt( $grayfox_llm_enc_key ) );

$grayfox_llm_usage = null;
if ( $grayfox_llm_configured && ! empty( $grayfox_llm_model ) ) {
	$grayfox_month_start = gmdate( 'Y-m-01 00:00:00' );

	$grayfox_usage_rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT m.role, SUM(CHAR_LENGTH(m.content)) AS total_chars
		 FROM %i m
		 JOIN %i c ON c.id = m.conversation_id
		 WHERE c.started_at >= %s
		 GROUP BY m.role",
		$grayfox_msg_table,
		$grayfox_conv_table,
		$grayfox_month_start
	), ARRAY_A );

	$grayfox_chars_by_role = array( 'user' => 0, 'assistant' => 0 );
	foreach ( (array) $grayfox_usage_rows as $grayfox_row ) {
		if ( isset( $grayfox_chars_by_role[ $grayfox_row['role'] ] ) ) {
			$grayfox_chars_by_role[ $grayfox_row['role'] ] = (int) $grayfox_row['total_chars'];
		}
	}

	$grayfox_tokens_input  = (int) round( $grayfox_chars_by_role['user']      / 4.5 );
	$grayfox_tokens_output = (int) round( $grayfox_chars_by_role['assistant'] / 4.5 );
	$grayfox_pricing       = GrayFox_Settings::get_model_pricing( $grayfox_llm_model );
	$grayfox_cost_est      = null;
	if ( $grayfox_pricing ) {
		$grayfox_cost_est = ( $grayfox_tokens_input / 1_000_000 ) * $grayfox_pricing['input_per_1m']
		                + ( $grayfox_tokens_output / 1_000_000 ) * $grayfox_pricing['output_per_1m'];
	}
	$grayfox_dashboard_url = GrayFox_Settings::get_provider_dashboard_url( $grayfox_llm_provider );
	$grayfox_llm_usage     = array(
		'tokens_input'  => $grayfox_tokens_input,
		'tokens_output' => $grayfox_tokens_output,
		'cost_est'      => $grayfox_cost_est,
		'pricing'       => $grayfox_pricing,
		'dashboard_url' => $grayfox_dashboard_url,
	);
}
?>
<div class="wrap grayfox-admin-wrap">

	<!-- Dashboard header -->
	<div class="grayfox-dashboard-header">
		<div class="grayfox-dashboard-title">
			<h1><?php esc_html_e( 'KBFox', 'kbfox' ); ?></h1>
			<span class="grayfox-dashboard-subtitle"><?php esc_html_e( 'Overview', 'kbfox' ); ?></span>
		</div>
		<div class="grayfox-dashboard-status">
			<span class="grayfox-status-pill <?php echo $grayfox_widget_enabled ? 'grayfox-status-pill--on' : 'grayfox-status-pill--off'; ?>">
				<span class="grayfox-status-pill__dot"></span>
				<?php esc_html_e( 'Widget', 'kbfox' ); ?>
				<?php echo $grayfox_widget_enabled ? esc_html__( 'On', 'kbfox' ) : esc_html__( 'Off', 'kbfox' ); ?>
			</span>
			<span class="grayfox-status-pill <?php echo $grayfox_llm_configured ? 'grayfox-status-pill--on' : 'grayfox-status-pill--off'; ?>">
				<span class="grayfox-status-pill__dot"></span>
				<?php esc_html_e( 'LLM', 'kbfox' ); ?>
				<?php echo $grayfox_llm_configured ? esc_html__( 'Connected', 'kbfox' ) : esc_html__( 'Not configured', 'kbfox' ); ?>
			</span>
			<?php if ( $grayfox_api_enabled ) : ?>
			<span class="grayfox-status-pill grayfox-status-pill--on">
				<span class="grayfox-status-pill__dot"></span>
				<?php esc_html_e( 'Public API On', 'kbfox' ); ?>
			</span>
			<?php endif; ?>
		</div>
	</div>

	<!-- KPI row -->
	<div class="grayfox-kpi-row">

		<div class="grayfox-kpi-card">
			<span class="grayfox-kpi-icon dashicons dashicons-format-chat"></span>
			<div class="grayfox-kpi-body">
				<span class="grayfox-kpi-number"><?php echo esc_html( number_format_i18n( $grayfox_conv_count_7d ) ); ?></span>
				<span class="grayfox-kpi-label"><?php esc_html_e( 'Conversations', 'kbfox' ); ?></span>
				<span class="grayfox-kpi-period"><?php esc_html_e( 'Last 7 days', 'kbfox' ); ?></span>
			</div>
			<a class="grayfox-kpi-link" href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-conversations' ) ); ?>">
				<?php esc_html_e( 'View →', 'kbfox' ); ?>
			</a>
		</div>

		<div class="grayfox-kpi-card">
			<span class="grayfox-kpi-icon dashicons dashicons-database"></span>
			<div class="grayfox-kpi-body">
				<span class="grayfox-kpi-number"><?php echo esc_html( number_format_i18n( $grayfox_doc_count ) ); ?></span>
				<span class="grayfox-kpi-label"><?php esc_html_e( 'KB Documents', 'kbfox' ); ?></span>
				<span class="grayfox-kpi-period"><?php esc_html_e( 'Total active', 'kbfox' ); ?></span>
			</div>
			<a class="grayfox-kpi-link" href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-knowledge-base' ) ); ?>">
				<?php esc_html_e( 'Manage →', 'kbfox' ); ?>
			</a>
		</div>

		<?php if ( $grayfox_api_enabled && null !== $grayfox_api_stats ) : ?>
		<div class="grayfox-kpi-card">
			<span class="grayfox-kpi-icon dashicons dashicons-rest-api"></span>
			<div class="grayfox-kpi-body">
				<span class="grayfox-kpi-number"><?php echo esc_html( number_format_i18n( $grayfox_api_stats['api_total'] ) ); ?></span>
				<span class="grayfox-kpi-label"><?php esc_html_e( 'API Requests', 'kbfox' ); ?></span>
				<span class="grayfox-kpi-period"><?php esc_html_e( 'Last 7 days', 'kbfox' ); ?></span>
			</div>
			<a class="grayfox-kpi-link" href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-settings' ) ); ?>">
				<?php esc_html_e( 'Settings →', 'kbfox' ); ?>
			</a>
		</div>
		<?php endif; ?>

	</div><!-- .grayfox-kpi-row -->

	<!-- Detail cards row -->
	<div class="grayfox-detail-row">

		<!-- LLM Usage -->
		<div class="grayfox-detail-card">
			<div class="grayfox-detail-card__header">
				<h2><?php esc_html_e( 'LLM Usage', 'kbfox' ); ?></h2>
				<span class="grayfox-detail-card__period"><?php esc_html_e( 'This month', 'kbfox' ); ?></span>
			</div>
			<?php if ( ! $grayfox_llm_configured ) : ?>
				<div class="grayfox-detail-empty">
					<span class="dashicons dashicons-warning"></span>
					<p><?php esc_html_e( 'No LLM configured.', 'kbfox' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-settings' ) ); ?>" class="button button-primary button-small">
						<?php esc_html_e( 'Configure now', 'kbfox' ); ?>
					</a>
				</div>
			<?php elseif ( null === $grayfox_llm_usage ) : ?>
				<div class="grayfox-detail-empty">
					<span class="dashicons dashicons-warning"></span>
					<p><?php esc_html_e( 'No model selected.', 'kbfox' ); ?></p>
				</div>
			<?php else : ?>
				<div class="grayfox-detail-card__provider">
					<span class="grayfox-badge grayfox-badge--info"><?php echo esc_html( ucfirst( $grayfox_llm_provider ) ); ?></span>
					<span class="grayfox-muted"><?php echo esc_html( $grayfox_llm_model ); ?></span>
				</div>
				<div class="grayfox-stat-row">
					<div class="grayfox-stat-item">
						<span class="grayfox-stat-item__value"><?php echo esc_html( number_format_i18n( $grayfox_llm_usage['tokens_input'] ) ); ?></span>
						<span class="grayfox-stat-item__label"><?php esc_html_e( 'Input tokens', 'kbfox' ); ?> <span class="grayfox-muted">(±20%)</span></span>
					</div>
					<div class="grayfox-stat-item">
						<span class="grayfox-stat-item__value"><?php echo esc_html( number_format_i18n( $grayfox_llm_usage['tokens_output'] ) ); ?></span>
						<span class="grayfox-stat-item__label"><?php esc_html_e( 'Output tokens', 'kbfox' ); ?> <span class="grayfox-muted">(±20%)</span></span>
					</div>
					<?php if ( null !== $grayfox_llm_usage['cost_est'] ) : ?>
					<div class="grayfox-stat-item">
						<span class="grayfox-stat-item__value">$<?php echo esc_html( number_format( $grayfox_llm_usage['cost_est'], 4 ) ); ?></span>
						<span class="grayfox-stat-item__label"><?php esc_html_e( 'Est. cost', 'kbfox' ); ?> <span class="grayfox-muted">(±25%)</span></span>
					</div>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $grayfox_llm_usage['pricing']['verified_date'] ) ) : ?>
					<p class="grayfox-muted grayfox-usage-disclaimer">
						<?php
						printf(
							/* translators: %s: pricing verification date */
							esc_html__( 'Prices verified %s. Estimates only.', 'kbfox' ),
							esc_html( $grayfox_llm_usage['pricing']['verified_date'] )
						);
						?>
					</p>
				<?php endif; ?>
				<div class="grayfox-detail-card__footer">
					<a href="<?php echo esc_url( $grayfox_llm_usage['dashboard_url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'View actual usage at provider →', 'kbfox' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<!-- Public KB API -->
		<?php if ( $grayfox_api_enabled || ( null !== $grayfox_api_stats && $grayfox_api_stats['api_total'] > 0 ) ) : ?>
		<div class="grayfox-detail-card">
			<div class="grayfox-detail-card__header">
				<h2><?php esc_html_e( 'Public KB API', 'kbfox' ); ?></h2>
				<span class="grayfox-detail-card__period"><?php esc_html_e( 'Last 7 days', 'kbfox' ); ?></span>
			</div>
			<?php if ( null !== $grayfox_api_stats && $grayfox_api_stats['api_total'] > 0 ) : ?>
				<div class="grayfox-stat-row">
					<div class="grayfox-stat-item">
						<span class="grayfox-stat-item__value"><?php echo esc_html( number_format_i18n( $grayfox_api_stats['api_ai_calls'] ) ); ?></span>
						<span class="grayfox-stat-item__label">
							<?php esc_html_e( 'AI agent calls', 'kbfox' ); ?>
							<?php if ( $grayfox_api_stats['api_total'] > 0 ) : ?>
								<span class="grayfox-muted">(<?php echo esc_html( round( $grayfox_api_stats['api_ai_calls'] / $grayfox_api_stats['api_total'] * 100 ) ); ?>%)</span>
							<?php endif; ?>
						</span>
					</div>
					<div class="grayfox-stat-item">
						<span class="grayfox-stat-item__value"><?php echo esc_html( number_format_i18n( $grayfox_api_stats['api_avg_time'] ) ); ?>ms</span>
						<span class="grayfox-stat-item__label"><?php esc_html_e( 'Avg response time', 'kbfox' ); ?></span>
					</div>
				</div>
				<?php if ( ! empty( $grayfox_api_stats['api_top_queries'] ) ) : ?>
					<div class="grayfox-top-queries">
						<p class="grayfox-top-queries__label"><?php esc_html_e( 'Top queries', 'kbfox' ); ?></p>
						<?php foreach ( $grayfox_api_stats['api_top_queries'] as $grayfox_q ) : ?>
							<span class="grayfox-query-tag"><?php echo esc_html( $grayfox_q ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="grayfox-detail-empty">
					<span class="dashicons dashicons-rest-api"></span>
					<p><?php esc_html_e( 'No API requests yet.', 'kbfox' ); ?></p>
				</div>
			<?php endif; ?>
			<div class="grayfox-detail-card__footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-settings' ) ); ?>">
					<?php esc_html_e( 'API settings →', 'kbfox' ); ?>
				</a>
			</div>
		</div>
		<?php endif; ?>

	</div><!-- .grayfox-detail-row -->

	<!-- Quick actions -->
	<div class="grayfox-actions-bar">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-settings' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Settings', 'kbfox' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-knowledge-base' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Knowledge Base', 'kbfox' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-conversations' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'Conversations', 'kbfox' ); ?>
		</a>
	</div>

	<!-- Pro upsell -->
	<div class="grayfox-upsell">
		<div class="grayfox-upsell__content">
			<h2><?php esc_html_e( 'Unlock more with GrayFox Pro', 'kbfox' ); ?></h2>
			<ul class="grayfox-upsell__features">
				<li>
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'SEO Optimization — AI-generated, fact-dense pages built for agent and search discoverability', 'kbfox' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-art"></span>
					<?php esc_html_e( 'Theme Builder — generate a branded WordPress theme from your knowledge base', 'kbfox' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-layout"></span>
					<?php esc_html_e( 'Site Builder — publish a full website from your KB in minutes', 'kbfox' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e( 'Booking — AI-assisted appointment scheduling with Google Calendar sync', 'kbfox' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-cart"></span>
					<?php esc_html_e( 'Sales — AI sales assistant that qualifies leads and drives conversions through chat', 'kbfox' ); ?>
				</li>
				<li>
					<span class="dashicons dashicons-email-alt"></span>
					<?php esc_html_e( 'Marketing Pipelines — automated follow-up sequences triggered by chat interactions', 'kbfox' ); ?>
				</li>
			</ul>
		</div>
		<div class="grayfox-upsell__action">
			<a href="https://plugins.grayfoxdc.com" target="_blank" rel="noopener noreferrer" class="button button-primary button-large">
				<?php esc_html_e( 'Get GrayFox Pro →', 'kbfox' ); ?>
			</a>
		</div>
	</div>

</div>
