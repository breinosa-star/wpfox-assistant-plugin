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
<div class="wrap grayfox-admin-wrap">
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
		);
		foreach ( $steps as $n => $label ) :
		?>
			<div class="grayfox-step-tab"
				 data-step="<?php echo esc_attr( $n ); ?>"
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

		<details style="margin-bottom:16px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Unsplash API Key (optional — for featured images)', 'grayfox' ); ?></summary>
			<div style="margin-top:10px;">
				<input type="password"
					   id="grayfox-unsplash-key"
					   class="regular-text"
					   placeholder="<?php esc_attr_e( 'Enter Unsplash Access Key', 'grayfox' ); ?>" />
				<button type="button" id="grayfox-save-unsplash" class="button" style="margin-left:8px;">
					<?php esc_html_e( 'Save Key', 'grayfox' ); ?>
				</button>
				<span id="grayfox-unsplash-status" style="margin-left:8px;"></span>
				<p class="description" style="margin-top:6px;">
					<?php esc_html_e( 'Your key is stored encrypted. Leave blank to skip featured images. Get a free key at unsplash.com/developers.', 'grayfox' ); ?>
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
					printf( esc_html__( '%d page(s) created as drafts.', 'grayfox' ), count( $build_status['pages'] ?? array() ) );
					?>
				</p>
			</div>

			<ul id="grayfox-results-list" style="margin-top:12px;">
				<?php foreach ( $build_status['pages'] ?? array() as $page ) :
					if ( empty( $page['post_id'] ) ) continue;
				?>
					<li>
						<a href="<?php echo esc_url( get_edit_post_link( $page['post_id'] ) ); ?>" target="_blank">
							<?php echo esc_html( $page['title'] ?? "Page #{$page['post_id']}" ); ?>
						</a>
						<span style="color:#888;margin-left:6px;">
							(<?php echo 'complete' === $page['status'] ? esc_html__( 'Created', 'grayfox' ) : esc_html__( 'Failed', 'grayfox' ); ?>)
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div style="margin-top:24px;padding-top:16px;border-top:1px solid #ddd;">
			<h3 style="color:#d63638;"><?php esc_html_e( 'Remove Generated Pages', 'grayfox' ); ?></h3>
			<p><?php esc_html_e( 'This will move all GrayFox-generated pages to the Trash. Non-generated pages are not affected.', 'grayfox' ); ?></p>
			<button type="button" id="grayfox-undo-build" class="button button-secondary" style="border-color:#d63638;color:#d63638;">
				<?php esc_html_e( 'Remove All GrayFox-Generated Pages', 'grayfox' ); ?>
			</button>
			<span id="grayfox-undo-status" style="margin-left:8px;"></span>
		</div>
	</div>
</div>
