<?php
/**
 * Admin Build Site page template — 5-step wizard.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$saved_sitemap = get_option( GrayFox_SiteBuilder::SITEMAP_OPTION, array() );
$build_status  = get_option( GrayFox_SiteBuilder::BUILD_OPTION, array( 'status' => 'idle' ) );
$format        = get_option( GrayFox_SiteBuilder::FORMAT_OPTION, 'blocks' );

// If a build has already completed show step 5 directly.
$initial_step  = ( 'complete' === ( $build_status['status'] ?? '' ) ) ? 5 : 1;
?>
<div class="wrap grayfox-admin-wrap" data-initial-step="<?php echo esc_attr( $initial_step ); ?>">
	<h1><?php esc_html_e( 'Build Site from Knowledge Base', 'grayfox' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'GrayFox will read your knowledge base and generate a full page structure for your WordPress site. Pages are created as drafts — you can edit or discard them at any time.', 'grayfox' ); ?>
	</p>

	<!-- Step indicator -->
	<div class="grayfox-steps" id="grayfox-step-indicator" style="display:flex;gap:0;margin:24px 0 32px;border-bottom:2px solid #ddd;">
		<?php
		$steps = array(
			1 => __( '1. Sitemap', 'grayfox' ),
			2 => __( '2. Environment', 'grayfox' ),
			3 => __( '3. Format', 'grayfox' ),
			4 => __( '4. Generate', 'grayfox' ),
			5 => __( '5. Results', 'grayfox' ),
			6 => __( '6. Menus', 'grayfox' ),
			7 => __( '7. Audit', 'grayfox' ),
		);
		foreach ( $steps as $n => $label ) :
			$extra_attrs = '';
		?>
			<div class="grayfox-step-tab"
				 data-step="<?php echo esc_attr( $n ); ?>"
				 <?php echo $extra_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				 style="padding:8px 20px;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;font-weight:600;color:#555;">
				<?php echo esc_html( $label ); ?>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- ===== Step 1: Sitemap Preview ===== -->
	<div class="grayfox-step" data-step="1" style="display:none;">
		<h2><?php esc_html_e( 'Step 1: Sitemap Preview', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'GrayFox will analyze your knowledge base and suggest a page structure. You can edit the page titles before saving.', 'grayfox' ); ?></p>

		<button type="button" id="grayfox-generate-sitemap" class="button button-primary">
			<?php esc_html_e( 'Generate Sitemap Preview', 'grayfox' ); ?>
		</button>
		<span id="grayfox-sitemap-status" style="margin-left:12px;font-style:italic;"></span>

		<div id="grayfox-sitemap-preview" style="margin-top:20px;display:none;">
			<div id="grayfox-sitemap-notice" class="notice notice-info inline" style="padding:8px 12px;"></div>
			<div id="grayfox-sitemap-editor" style="margin-top:12px;"></div>
			<p>
				<button type="button" id="grayfox-save-sitemap" class="button button-primary" style="margin-top:8px;">
					<?php esc_html_e( 'Save & Continue', 'grayfox' ); ?>
				</button>
			</p>
		</div>

		<?php if ( ! empty( $saved_sitemap ) ) : ?>
			<p style="margin-top:12px;">
				<?php esc_html_e( 'A sitemap is already saved.', 'grayfox' ); ?>
				<button type="button" class="button" id="grayfox-use-saved-sitemap">
					<?php esc_html_e( 'Use Saved Sitemap & Continue', 'grayfox' ); ?>
				</button>
			</p>
		<?php endif; ?>
	</div>

	<!-- ===== Step 2: Environment Detection ===== -->
	<div class="grayfox-step" data-step="2" style="display:none;">
		<h2><?php esc_html_e( 'Step 2: Environment', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'Detecting your WordPress theme and page builder environment…', 'grayfox' ); ?></p>
		<div id="grayfox-env-result" style="margin-top:12px;"></div>
		<p style="margin-top:16px;">
			<button type="button" id="grayfox-env-continue" class="button button-primary" style="display:none;">
				<?php esc_html_e( 'Continue', 'grayfox' ); ?>
			</button>
		</p>
	</div>

	<!-- ===== Step 3: Format Choice ===== -->
	<div class="grayfox-step" data-step="3" style="display:none;">
		<h2><?php esc_html_e( 'Step 3: Page Format', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'Choose how pages will be built.', 'grayfox' ); ?></p>

		<fieldset id="grayfox-format-fieldset">
			<label style="display:block;margin-bottom:10px;cursor:pointer;">
				<input type="radio" name="grayfox_build_format" value="blocks"
					   <?php checked( $format, 'blocks' ); ?> style="margin-right:6px;" />
				<strong><?php esc_html_e( 'WordPress Blocks', 'grayfox' ); ?></strong>
				— <?php esc_html_e( 'works with any theme, no plugins required.', 'grayfox' ); ?>
			</label>
			<label style="display:block;cursor:pointer;" id="grayfox-elementor-label">
				<input type="radio" name="grayfox_build_format" value="elementor"
					   <?php checked( $format, 'elementor' ); ?> style="margin-right:6px;" />
				<strong><?php esc_html_e( 'Elementor', 'grayfox' ); ?></strong>
				— <?php esc_html_e( 'requires Elementor 3.0.0 or newer.', 'grayfox' ); ?>
			</label>
		</fieldset>
		<span id="grayfox-format-notice" style="display:block;margin-top:8px;color:#d63638;"></span>

		<p style="margin-top:16px;">
			<button type="button" id="grayfox-save-format" class="button button-primary">
				<?php esc_html_e( 'Save Format & Continue', 'grayfox' ); ?>
			</button>
		</p>
	</div>

	<!-- ===== Step 4: Generate ===== -->
	<div class="grayfox-step" data-step="4" style="display:none;">
		<h2><?php esc_html_e( 'Step 4: Generate Site', 'grayfox' ); ?></h2>

		<div id="grayfox-estimate-block" style="margin-bottom:16px;">
			<button type="button" id="grayfox-estimate-cost" class="button">
				<?php esc_html_e( 'Estimate Cost', 'grayfox' ); ?>
			</button>
			<div id="grayfox-estimate-result" style="margin-top:8px;font-size:13px;"></div>
		</div>

		<?php $unsplash_saved = ! empty( get_option( GrayFox_SiteBuilder::UNSPLASH_OPTION, '' ) ); ?>
		<details style="margin-bottom:16px;"<?php echo $unsplash_saved ? '' : ' open'; ?>>
			<summary style="cursor:pointer;font-weight:600;">
				<?php esc_html_e( 'Unsplash API Key (optional — for stock photos)', 'grayfox' ); ?>
				<?php if ( $unsplash_saved ) : ?>
					<span style="margin-left:8px;color:#46b450;font-weight:normal;">&#10003; <?php esc_html_e( 'Key saved', 'grayfox' ); ?></span>
				<?php endif; ?>
			</summary>
			<div style="margin-top:10px;">
				<input type="password"
					   id="grayfox-unsplash-key"
					   class="regular-text"
					   placeholder="<?php echo $unsplash_saved ? esc_attr__( 'Enter new key to replace saved key', 'grayfox' ) : esc_attr__( 'Enter Unsplash Access Key', 'grayfox' ); ?>" />
				<button type="button" id="grayfox-save-unsplash" class="button" style="margin-left:8px;">
					<?php esc_html_e( 'Save Key', 'grayfox' ); ?>
				</button>
				<span id="grayfox-unsplash-status" style="margin-left:8px;"></span>
				<p class="description" style="margin-top:6px;">
					<?php esc_html_e( 'Stored encrypted. If no key is set, images are generated via DALL-E. Get a free key at unsplash.com/developers.', 'grayfox' ); ?>
				</p>
			</div>
		</details>

		<p>
			<button type="button" id="grayfox-start-generation" class="button button-primary button-hero">
				<?php esc_html_e( 'Generate Site', 'grayfox' ); ?>
			</button>
		</p>

		<div id="grayfox-progress-block" style="display:none;margin-top:20px;">
			<p id="grayfox-progress-text" style="font-weight:600;"></p>
			<progress id="grayfox-progress-bar" value="0" max="100" style="width:400px;max-width:100%;height:18px;display:block;margin-bottom:12px;"></progress>
			<ul id="grayfox-progress-list" style="max-height:300px;overflow-y:auto;"></ul>
		</div>
	</div>

	<!-- ===== Step 5: Results ===== -->
	<div class="grayfox-step" data-step="5" style="display:none;">
		<h2><?php esc_html_e( 'Step 5: Results', 'grayfox' ); ?></h2>

		<?php if ( 'complete' === ( $build_status['status'] ?? '' ) ) : ?>
			<div class="notice notice-success inline" style="padding:8px 12px;">
				<p>
					<?php
					/* translators: %d: number of pages created */
					printf( esc_html__( '%d page(s) created as drafts. Review each page below and request revisions as needed.', 'grayfox' ), count( $build_status['pages'] ?? array() ) );
					?>
				</p>
			</div>

			<table id="grayfox-results-table" class="widefat striped" style="margin-top:16px;">
				<thead>
					<tr>
						<th style="width:22%;"><?php esc_html_e( 'Page', 'grayfox' ); ?></th>
						<th style="width:12%;text-align:center;"><?php esc_html_e( 'Copy', 'grayfox' ); ?></th>
						<th style="width:12%;text-align:center;"><?php esc_html_e( 'Arrangement', 'grayfox' ); ?></th>
						<th style="width:12%;text-align:center;"><?php esc_html_e( 'Images', 'grayfox' ); ?></th>
						<th style="width:18%;"><?php esc_html_e( 'Action', 'grayfox' ); ?></th>
						<th style="width:14%;"><?php esc_html_e( 'Additional context', 'grayfox' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Status', 'grayfox' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $build_status['pages'] ?? array() as $page ) :
					if ( empty( $page['post_id'] ) || 'complete' !== $page['status'] ) continue;
					$post_id = (int) $page['post_id'];
					$rev_status = esc_attr( $page['revision_status'] ?? '' );
				?>
					<tr data-post-id="<?php echo absint( $post_id ); ?>">
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" target="_blank">
								<?php echo esc_html( $page['title'] ?? "Page #{$post_id}" ); ?>
							</a>
							&nbsp;
							<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" style="color:#888;font-size:12px;">&#8599;</a>
						</td>
						<td style="text-align:center;color:#46b450;font-size:16px;">&#10003;</td>
						<td style="text-align:center;color:#46b450;font-size:16px;">&#10003;</td>
						<td style="text-align:center;color:#46b450;font-size:16px;">&#10003;</td>
						<td>
							<select class="grayfox-revision-action" style="width:100%;">
								<option value=""><?php esc_html_e( '— No change —', 'grayfox' ); ?></option>
								<optgroup label="<?php esc_attr_e( 'Revise copy', 'grayfox' ); ?>">
									<option value="revise_copy|shorter"><?php esc_html_e( 'Shorter and punchier', 'grayfox' ); ?></option>
									<option value="revise_copy|conversational"><?php esc_html_e( 'More conversational tone', 'grayfox' ); ?></option>
									<option value="revise_copy|detailed"><?php esc_html_e( 'More detailed and specific', 'grayfox' ); ?></option>
									<option value="revise_copy|benefits"><?php esc_html_e( 'Focus on benefits, not features', 'grayfox' ); ?></option>
									<option value="revise_copy|authoritative"><?php esc_html_e( 'Sound more authoritative', 'grayfox' ); ?></option>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Re-arrange layout', 'grayfox' ); ?>">
									<option value="rearrange|different"><?php esc_html_e( 'Try a completely different layout', 'grayfox' ); ?></option>
									<option value="rearrange|visual"><?php esc_html_e( 'Add more visual variety', 'grayfox' ); ?></option>
									<option value="rearrange|simpler"><?php esc_html_e( 'Simpler, more focused structure', 'grayfox' ); ?></option>
									<option value="rearrange|dense"><?php esc_html_e( 'More information-dense (tables, specs)', 'grayfox' ); ?></option>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'New images', 'grayfox' ); ?>">
									<option value="new_images|fresh"><?php esc_html_e( 'Replace all images', 'grayfox' ); ?></option>
								</optgroup>
							</select>
						</td>
						<td>
							<input type="text"
								   class="grayfox-revision-hint"
								   maxlength="50"
								   placeholder="<?php esc_attr_e( 'Optional hint…', 'grayfox' ); ?>"
								   style="width:100%;display:none;" />
						</td>
						<td style="text-align:center;">
							<span class="grayfox-revision-status" data-status="<?php echo esc_attr( $rev_status ); ?>">
								<?php
								$labels = array(
									'pending'    => '<span style="color:#996800;">&#9679; Queued</span>',
									'processing' => '<span style="color:#2271b1;">&#8635; Processing</span>',
									'done'       => '<span style="color:#46b450;">&#10003; Done</span>',
									'error'      => '<span style="color:#d63638;">&#10007; Error</span>',
								);
								echo isset( $labels[ $rev_status ] ) ? wp_kses_post( $labels[ $rev_status ] ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div style="margin-top:16px;display:flex;align-items:center;gap:16px;">
				<button type="button" id="grayfox-submit-revisions" class="button button-primary" disabled>
					<?php esc_html_e( 'Submit revision requests', 'grayfox' ); ?>
				</button>
				<span id="grayfox-revisions-status" style="font-style:italic;color:#555;"></span>
			</div>

			<div style="margin-top:32px;padding:20px 24px;background:#f0f6fc;border:1px solid #c3d9ef;border-radius:4px;">
				<h3 style="margin-top:0;"><?php esc_html_e( "What's next?", 'grayfox' ); ?></h3>
				<p><?php esc_html_e( 'Your site is live. Here are the natural next steps to get more from it:', 'grayfox' ); ?></p>
				<ul style="margin-left:1.5em;list-style:disc;">
					<li><?php esc_html_e( 'Connect your booking system so the chatbot can schedule appointments automatically', 'grayfox' ); ?></li>
					<li><?php esc_html_e( 'Sync your Google Drive documents into the knowledge base so your chatbot stays up to date', 'grayfox' ); ?></li>
					<li><?php esc_html_e( 'Export conversation analytics to Google Sheets to track what visitors are asking', 'grayfox' ); ?></li>
				</ul>
				<p style="margin-top:16px;">
					<a href="https://grayfox.io" target="_blank" rel="noopener noreferrer" class="button button-primary">
						<?php esc_html_e( 'Get the Pro add-on at grayfox.io →', 'grayfox' ); ?>
					</a>
				</p>
			</div>

		<?php else : ?>
			<p><?php esc_html_e( 'No completed build found. Generate the site in Step 4 first.', 'grayfox' ); ?></p>
		<?php endif; ?>

		<div style="margin-top:32px;padding-top:16px;border-top:1px solid #ddd;">
			<h3 style="color:#d63638;"><?php esc_html_e( 'Remove Generated Pages', 'grayfox' ); ?></h3>
			<p><?php esc_html_e( 'This will move all GrayFox-generated pages to the Trash. Non-generated pages are not affected.', 'grayfox' ); ?></p>
			<button type="button" id="grayfox-undo-build" class="button button-secondary" style="border-color:#d63638;color:#d63638;">
				<?php esc_html_e( 'Remove All GrayFox-Generated Pages', 'grayfox' ); ?>
			</button>
			<span id="grayfox-undo-status" style="margin-left:8px;"></span>
		</div>
	</div>

	<!-- ===== Step 6: Menus ===== -->
	<div class="grayfox-step" data-step="6" style="display:none;">
		<h2><?php esc_html_e( 'Step 6: Navigation Menus', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'Configure the header and footer navigation menus for your site. Use Smart Suggest to let GrayFox categorize your pages automatically.', 'grayfox' ); ?></p>

		<div id="grayfox-footer-hint" style="display:none;margin-bottom:12px;padding:8px 12px;border-radius:4px;background:#fff8e5;border:1px solid #f0c36d;font-size:12px;color:#6a4c00;"></div>

		<div style="margin-bottom:16px;">
			<button type="button" id="grayfox-suggest-footer" class="button" style="display:none;">
				<?php esc_html_e( 'Suggest', 'grayfox' ); ?>
			</button>
			<span id="grayfox-suggest-footer-status" style="margin-left:8px;font-style:italic;color:#555;"></span>
		</div>

<div id="grayfox-footer-loading" style="color:#555;font-style:italic;"><?php esc_html_e( 'Loading menu configuration…', 'grayfox' ); ?></div>
		<div id="grayfox-footer-columns" style="display:none;margin-top:16px;gap:24px;flex-wrap:wrap;"></div>

		<p style="margin-top:16px;">
			<button type="button" id="grayfox-save-footer" class="button button-primary" style="display:none;">
				<?php esc_html_e( 'Save Menus', 'grayfox' ); ?>
			</button>
			<button type="button" id="grayfox-reset-footer" class="button" style="display:none;margin-left:8px;">
				<?php esc_html_e( 'Reset Menus', 'grayfox' ); ?>
			</button>
			<span id="grayfox-footer-status" style="margin-left:12px;font-style:italic;"></span>
		</p>
	</div>

	<!-- ===== Step 7: Site Audit ===== -->
	<div class="grayfox-step" data-step="7" style="display:none;">
		<h2><?php esc_html_e( 'Step 7: Site Audit', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'Scan all generated pages for issues. Use Auto-Fix for deterministic repairs or AI Assist for intelligent suggestions requiring review.', 'grayfox' ); ?></p>

		<div style="margin-bottom:20px;display:flex;align-items:center;gap:12px;">
			<button type="button" id="grayfox-run-audit" class="button button-primary">
				<?php esc_html_e( 'Run All Scans', 'grayfox' ); ?>
			</button>
			<span id="grayfox-audit-status" style="font-style:italic;color:#555;"></span>
		</div>

		<?php
		$audit_sections = array(
			'accessibility'   => array(
				'label'    => __( 'Accessibility (ADA/WCAG)', 'grayfox' ),
				'btn'      => __( 'Auto-Fix', 'grayfox' ),
				'has_fix'  => true,
				'has_llm'  => true,
				'llm_label'=> __( 'AI: Improve Alt Text', 'grayfox' ),
				'llm_action' => 'llmGenerateAltText',
			),
			'broken_links'    => array(
				'label'    => __( 'Broken / Empty Links', 'grayfox' ),
				'btn'      => __( 'Auto-Fix Structure', 'grayfox' ),
				'has_fix'  => true,
				'has_llm'  => true,
				'llm_label'=> __( 'AI: Suggest Links', 'grayfox' ),
				'llm_action' => 'llmSuggestLinkTargets',
			),
			'content_quality' => array(
				'label'    => __( 'Content Quality', 'grayfox' ),
				'btn'      => __( 'Publish Drafts', 'grayfox' ),
				'has_fix'  => true,
				'has_llm'  => true,
				'llm_label'=> __( 'AI: Fix Placeholders', 'grayfox' ),
				'llm_action' => 'llmReplacePlaceholders',
			),
			'wp_health'       => array(
				'label'    => __( 'WordPress Health', 'grayfox' ),
				'btn'      => __( 'Auto-Fix', 'grayfox' ),
				'has_fix'  => true,
				'has_llm'  => false,
				'llm_label'=> '',
				'llm_action' => '',
			),
			'seo'             => array(
				'label'    => __( 'SEO Basics', 'grayfox' ),
				'btn'      => '',
				'has_fix'  => false,
				'has_llm'  => false,
				'llm_label'=> '',
				'llm_action' => '',
			),
		);
		foreach ( $audit_sections as $section_key => $section ) :
		?>
		<div class="grayfox-audit-section" data-section="<?php echo esc_attr( $section_key ); ?>"
			 data-llm-action="<?php echo esc_attr( $section['llm_action'] ); ?>"
			 style="border:1px solid #ddd;border-radius:4px;margin-bottom:12px;">
			<div class="grayfox-audit-section-header"
				 style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;cursor:pointer;background:#f9f9f9;">
				<strong><?php echo wp_kses_post( $section['label'] ); ?></strong>
				<span class="grayfox-audit-badge" data-status="idle"
					  style="font-size:12px;padding:2px 8px;border-radius:10px;background:#ddd;color:#555;">
					<?php esc_html_e( 'Not scanned', 'grayfox' ); ?>
				</span>
			</div>
			<div class="grayfox-audit-section-body" style="display:none;padding:12px 16px;">
				<table class="wp-list-table widefat striped grayfox-audit-table" style="margin-bottom:12px;">
					<thead>
						<tr>
							<th style="width:25%;"><?php esc_html_e( 'Page', 'grayfox' ); ?></th>
							<th><?php esc_html_e( 'Issue', 'grayfox' ); ?></th>
							<th style="width:12%;text-align:center;"><?php esc_html_e( 'Severity', 'grayfox' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>

				<?php if ( $section['has_fix'] ) : ?>
					<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
						<button type="button" class="grayfox-fix-section button button-primary"
								data-section="<?php echo esc_attr( $section_key ); ?>"
								style="display:none;">
							<?php echo wp_kses_post( $section['btn'] ); ?>
						</button>
						<?php if ( $section['has_llm'] ) : ?>
						<button type="button" class="grayfox-llm-assist button"
								data-section="<?php echo esc_attr( $section_key ); ?>"
								style="display:none;">
							<?php echo wp_kses_post( $section['llm_label'] ); ?>
						</button>
						<?php endif; ?>
						<button type="button" class="grayfox-undo-fix button"
								data-section="<?php echo esc_attr( $section_key ); ?>"
								style="display:none;">
							<?php esc_html_e( 'Undo Fix', 'grayfox' ); ?>
						</button>
						<span class="grayfox-fix-status" style="font-style:italic;color:#555;"></span>
					</div>

					<?php if ( $section['has_llm'] ) : ?>
					<div class="grayfox-llm-panel" data-section="<?php echo esc_attr( $section_key ); ?>"
						 style="display:none;margin-top:12px;border:1px solid #c6d9f0;border-radius:4px;background:#f0f6fc;padding:12px;">
						<div class="grayfox-llm-panel-loading" style="color:#555;font-style:italic;"><?php esc_html_e( 'Generating AI suggestions…', 'grayfox' ); ?></div>
						<div class="grayfox-llm-panel-body" style="display:none;">
							<table class="wp-list-table widefat striped" style="margin-bottom:10px;">
								<thead>
									<tr>
										<th style="width:30px;"><input type="checkbox" class="grayfox-llm-select-all"></th>
										<th><?php esc_html_e( 'Page / Context', 'grayfox' ); ?></th>
										<th><?php esc_html_e( 'Proposed Change', 'grayfox' ); ?></th>
										<th style="width:120px;"><?php esc_html_e( 'Confidence', 'grayfox' ); ?></th>
									</tr>
								</thead>
								<tbody class="grayfox-llm-suggestions-body"></tbody>
							</table>
							<div style="display:flex;align-items:center;gap:8px;">
								<button type="button" class="grayfox-apply-llm button button-primary"
										data-section="<?php echo esc_attr( $section_key ); ?>">
									<?php esc_html_e( 'Apply Selected', 'grayfox' ); ?>
								</button>
								<button type="button" class="grayfox-close-llm-panel button"
										data-section="<?php echo esc_attr( $section_key ); ?>" style="margin-left:4px;">
									<?php esc_html_e( 'Close', 'grayfox' ); ?>
								</button>
								<span class="grayfox-llm-apply-status" style="font-style:italic;color:#555;"></span>
							</div>
						</div>
					</div>
					<?php endif; ?>

				<?php elseif ( 'seo' === $section_key ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-seo' ) ); ?>"
					   class="button button-primary" style="display:none;" id="grayfox-seo-configure">
						<?php esc_html_e( 'Configure SEO', 'grayfox' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

</div>
