<?php
/**
 * Admin Theme Builder page template — 4-step wizard.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$brand_profile        = get_option( GrayFox_ThemeBuilder::BRAND_PROFILE_OPTION, array() );

// Normalize: support both legacy flat profile and manifest format (theme.colors etc.).
$profile_colors  = $brand_profile['colors']  ?? $brand_profile['theme']['colors']  ?? [];
$profile_typo    = $brand_profile['typography'] ?? $brand_profile['theme']['typography'] ?? [];
$profile_style   = $brand_profile['visual_style'] ?? $brand_profile['theme']['style_archetype'] ?? '';
$profile_spacing = $brand_profile['spacing_style'] ?? $brand_profile['theme']['spacing_style'] ?? 'comfortable';

$has_profile          = ! empty( $profile_colors );
$initial_step         = $has_profile ? 3 : 1;
$provider             = get_option( 'grayfox_llm_provider', 'openai' );
$supports_vision      = GrayFox_ThemeBuilder::get_instance()->provider_supports_vision( $provider );
$elementor_active     = defined( 'ELEMENTOR_VERSION' );
$is_block_theme       = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
$generated_themes     = get_option( GrayFox_ThemeBuilder::GENERATED_THEMES_OPTION, array() );
$themes_count         = count( $generated_themes );
$can_create           = $themes_count < GrayFox_ThemeBuilder::MAX_THEMES;
$active_theme_slug    = get_stylesheet();

$google_fonts_list = array(
	'Inter', 'Open Sans', 'Roboto', 'Lato', 'Montserrat', 'Raleway', 'Nunito', 'Poppins',
	'Source Sans Pro', 'Merriweather', 'Playfair Display', 'Lora', 'PT Serif', 'Crimson Text',
	'Oswald', 'Ubuntu', 'Noto Sans', 'Fira Sans', 'DM Sans', 'Mulish',
);
?>
<div class="wrap grayfox-admin-wrap" id="grayfox-theme-builder" data-initial-step="<?php echo esc_attr( $initial_step ); ?>">
	<h1><?php esc_html_e( 'Theme Builder', 'grayfox' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Generate a brand-coherent color palette and typography for your site. GrayFox reads your knowledge base and optional logo to produce a cohesive visual theme compatible with WordPress and Elementor.', 'grayfox' ); ?>
	</p>

	<!-- Step indicator -->
	<div class="grayfox-steps" id="grayfox-tb-step-indicator" style="display:flex;gap:0;margin:24px 0 32px;border-bottom:2px solid #ddd;">
		<?php
		$steps = array(
			1 => __( '1. Brand Assets', 'grayfox' ),
			2 => __( '2. Preview', 'grayfox' ),
			3 => __( '3. Apply', 'grayfox' ),
			4 => __( '4. Done', 'grayfox' ),
		);
		foreach ( $steps as $n => $label ) :
		?>
			<div class="grayfox-step-tab grayfox-tb-step-tab"
				 data-step="<?php echo esc_attr( $n ); ?>"
				 style="padding:8px 20px;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;font-weight:600;color:#555;">
				<?php echo esc_html( $label ); ?>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- ═══════════════════════════════════════════════════════════════════
	     STEP 1 — Brand Assets
	     ═══════════════════════════════════════════════════════════════════ -->
	<div class="grayfox-step grayfox-tb-step" data-step="1" style="display:none;">
		<h2><?php esc_html_e( 'Step 1: Brand Assets', 'grayfox' ); ?></h2>
		<p><?php esc_html_e( 'Optionally provide a logo and brand guidelines. GrayFox will use these alongside your knowledge base to generate a theme. All fields are optional.', 'grayfox' ); ?></p>

		<?php if ( ! $supports_vision ) : ?>
		<div class="notice notice-info inline" style="margin:0 0 16px;">
			<p>
				<?php
				echo esc_html( sprintf(
					/* translators: %s: LLM provider name */
					__( 'Logo color analysis is not available with your current LLM provider (%s). You can still generate a theme from your knowledge base.', 'grayfox' ),
					esc_html( $provider )
				) );
				?>
			</p>
		</div>
		<?php endif; ?>

		<table class="form-table" style="max-width:700px;">
			<tbody>
				<tr>
					<th scope="row" style="width:160px;">
						<label><?php esc_html_e( 'Logo', 'grayfox' ); ?></label>
					</th>
					<td>
						<input type="hidden" id="grayfox-tb-logo-id" value="">
						<div id="grayfox-tb-logo-preview" style="margin-bottom:10px;display:none;">
							<img id="grayfox-tb-logo-img" src="" alt="" style="max-height:80px;max-width:240px;border:1px solid #ddd;padding:4px;background:#fff;">
						</div>
						<button type="button" id="grayfox-tb-logo-select" class="button" <?php echo ( ! $supports_vision ) ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Select Logo from Media Library', 'grayfox' ); ?>
						</button>
						<button type="button" id="grayfox-tb-logo-remove" class="button" style="display:none;margin-left:8px;">
							<?php esc_html_e( 'Remove', 'grayfox' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'JPEG, PNG, or WebP. The LLM will analyze it for colors and visual style.', 'grayfox' ); ?>
						</p>
						<div id="grayfox-tb-logo-analysis-status" style="margin-top:8px;display:none;"></div>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="grayfox-tb-guidelines"><?php esc_html_e( 'Brand Guidelines', 'grayfox' ); ?></label>
					</th>
					<td>
						<textarea id="grayfox-tb-guidelines" rows="6" style="width:100%;"
							placeholder="<?php esc_attr_e( 'Optional: paste color hex codes, font names, tone of voice notes, or any other branding guidance...', 'grayfox' ); ?>"></textarea>
						<p class="description">
							<?php esc_html_e( 'Free-form text. Include hex codes, font preferences, brand adjectives, or anything that describes your brand identity.', 'grayfox' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<label style="cursor:pointer;">
							<input type="checkbox" id="grayfox-tb-skip-branding">
							<?php esc_html_e( 'Skip — no branding materials available', 'grayfox' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'GrayFox will generate a theme based solely on your knowledge base.', 'grayfox' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<div style="margin-top:24px;">
			<button type="button" id="grayfox-tb-step1-continue" class="button button-primary button-large">
				<?php esc_html_e( 'Continue →', 'grayfox' ); ?>
			</button>
			<span id="grayfox-tb-analyzing-status" style="display:none;margin-left:16px;">
				<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
				<em><?php esc_html_e( 'Analyzing logo…', 'grayfox' ); ?></em>
			</span>
		</div>
	</div>

	<!-- ═══════════════════════════════════════════════════════════════════
	     STEP 2 — AI-Generated Theme (results-first, overrides secondary)
	     ═══════════════════════════════════════════════════════════════════ -->
	<div class="grayfox-step grayfox-tb-step" data-step="2" style="display:none;">

		<!-- ── Generating state (shown while LLM is running) ── -->
		<div id="grayfox-tb-generating-state" style="padding:40px 0;text-align:center;">
			<span class="spinner is-active" style="float:none;width:30px;height:30px;margin:0 auto 16px;display:block;"></span>
			<p style="font-size:16px;color:#555;margin:0;">
				<?php esc_html_e( 'GrayFox is analyzing your business and generating a theme…', 'grayfox' ); ?>
			</p>
		</div>

		<!-- ── Error state ── -->
		<div id="grayfox-tb-generate-error" style="display:none;">
			<div class="notice notice-error inline"><p id="grayfox-tb-error-text"></p></div>
			<button type="button" id="grayfox-tb-retry" class="button" style="margin-top:12px;">
				<?php esc_html_e( '↺ Try Again', 'grayfox' ); ?>
			</button>
		</div>

		<!-- ── Results (hidden until generated) ── -->
		<div id="grayfox-tb-results" style="display:none;">
		<div style="display:flex;gap:40px;align-items:flex-start;flex-wrap:wrap;">
		<div style="flex:1;min-width:0;max-width:660px;">

			<!-- 1. AI Rationale — first and most prominent -->
			<div id="grayfox-tb-rationale" style="margin-bottom:32px;padding:16px 20px;background:#f0f6ff;border-left:4px solid #2271b1;border-radius:0 4px 4px 0;">
				<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2271b1;margin-bottom:6px;">
					<?php esc_html_e( 'Why GrayFox chose this theme', 'grayfox' ); ?>
				</div>
				<p id="grayfox-tb-rationale-text" style="margin:0;line-height:1.6;color:#23282d;"></p>
			</div>

			<!-- 2. Color Palette -->
			<div style="margin-bottom:32px;">
				<div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px;">
					<h3 style="margin:0;"><?php esc_html_e( 'Color Palette', 'grayfox' ); ?></h3>
					<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2271b1;background:#e8f0fb;padding:2px 8px;border-radius:10px;">
						<?php esc_html_e( 'AI Selected', 'grayfox' ); ?>
					</span>
				</div>
				<p style="margin:0 0 16px;color:#666;font-size:13px;">
					<?php esc_html_e( 'These colors were chosen to reflect your brand identity. Click any swatch if you want to adjust a color.', 'grayfox' ); ?>
				</p>
				<div id="grayfox-tb-swatches" style="display:flex;flex-wrap:wrap;gap:16px;">
					<?php
					$swatch_meta = array(
						'primary'    => array( 'label' => __( 'Primary',    'grayfox' ), 'desc' => __( 'Buttons, links, key headings', 'grayfox' ) ),
						'secondary'  => array( 'label' => __( 'Secondary',  'grayfox' ), 'desc' => __( 'Section backgrounds, accents', 'grayfox' ) ),
						'accent'     => array( 'label' => __( 'Accent',     'grayfox' ), 'desc' => __( 'Highlights, hover states', 'grayfox' ) ),
						'background' => array( 'label' => __( 'Background', 'grayfox' ), 'desc' => __( 'Page background', 'grayfox' ) ),
						'text'       => array( 'label' => __( 'Text',       'grayfox' ), 'desc' => __( 'Body copy', 'grayfox' ) ),
						'muted'      => array( 'label' => __( 'Muted',      'grayfox' ), 'desc' => __( 'Captions, labels', 'grayfox' ) ),
					);
					$default_colors = array(
						'primary' => '#1a2e4a', 'secondary' => '#2d6a8f', 'accent' => '#f4a723',
						'background' => '#ffffff', 'text' => '#1e1e1e', 'muted' => '#6b7280',
					);
					foreach ( $swatch_meta as $key => $meta ) :
						$saved = $profile_colors[ $key ] ?? $default_colors[ $key ];
					?>
						<div class="grayfox-tb-swatch" data-color-key="<?php echo esc_attr( $key ); ?>"
							 style="width:100px;cursor:pointer;" title="<?php esc_attr_e( 'Click to change', 'grayfox' ); ?>">
							<div class="grayfox-tb-swatch-circle"
								 style="width:56px;height:56px;border-radius:10px;border:2px solid #e0e0e0;margin:0 auto 6px;background:<?php echo esc_attr( $saved ); ?>;">
							</div>
							<input type="color" class="grayfox-tb-color-input" data-color-key="<?php echo esc_attr( $key ); ?>"
								   value="<?php echo esc_attr( $saved ); ?>"
								   style="opacity:0;position:absolute;width:1px;height:1px;">
							<div style="text-align:center;">
								<div class="grayfox-tb-hex-label" style="font-size:11px;font-family:monospace;color:#555;">
									<?php echo esc_html( $saved ); ?>
								</div>
								<div style="font-size:12px;font-weight:600;margin-top:2px;"><?php echo esc_html( $meta['label'] ); ?></div>
								<div style="font-size:10px;color:#888;line-height:1.3;"><?php echo esc_html( $meta['desc'] ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- 3. Typography -->
			<div style="margin-bottom:32px;">
				<div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px;">
					<h3 style="margin:0;"><?php esc_html_e( 'Typography', 'grayfox' ); ?></h3>
					<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2271b1;background:#e8f0fb;padding:2px 8px;border-radius:10px;">
						<?php esc_html_e( 'AI Selected', 'grayfox' ); ?>
					</span>
				</div>
				<p style="margin:0 0 16px;color:#666;font-size:13px;">
					<?php esc_html_e( 'Fonts were chosen to complement your brand personality. The preview updates as the fonts load.', 'grayfox' ); ?>
				</p>

				<!-- Heading font -->
				<div style="margin-bottom:20px;padding:16px;border:1px solid #e0e0e0;border-radius:4px;background:#fafafa;">
					<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:10px;">
						<?php esc_html_e( 'Headings', 'grayfox' ); ?>
					</div>
					<div id="grayfox-tb-heading-preview"
						 style="font-size:28px;font-weight:700;line-height:1.2;color:#1e1e1e;margin-bottom:8px;">
						<?php esc_html_e( 'The Quick Brown Fox', 'grayfox' ); ?>
					</div>
					<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
						<span id="grayfox-tb-heading-font-label" style="font-size:13px;font-weight:600;color:#333;"></span>
						<a href="#" id="grayfox-tb-heading-change-link" style="font-size:12px;color:#2271b1;">
							<?php esc_html_e( 'change font', 'grayfox' ); ?>
						</a>
					</div>
					<div id="grayfox-tb-heading-change-panel" style="display:none;margin-top:10px;">
						<input type="text" id="grayfox-tb-heading-font-input"
							   placeholder="<?php esc_attr_e( 'Type any Google Font name, e.g. Cormorant Garamond', 'grayfox' ); ?>"
							   style="width:280px;">
						<button type="button" id="grayfox-tb-heading-font-apply" class="button" style="margin-left:8px;">
							<?php esc_html_e( 'Apply', 'grayfox' ); ?>
						</button>
						<a href="https://fonts.google.com" target="_blank" style="font-size:12px;margin-left:10px;color:#2271b1;">
							<?php esc_html_e( 'Browse Google Fonts ↗', 'grayfox' ); ?>
						</a>
					</div>
					<!-- Hidden input stores the actual value -->
					<input type="hidden" id="grayfox-tb-heading-font" value="<?php echo esc_attr( $profile_typo['heading_font'] ?? 'Inter' ); ?>">
				</div>

				<!-- Body font -->
				<div style="padding:16px;border:1px solid #e0e0e0;border-radius:4px;background:#fafafa;">
					<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#888;margin-bottom:10px;">
						<?php esc_html_e( 'Body Text', 'grayfox' ); ?>
					</div>
					<div id="grayfox-tb-body-preview"
						 style="font-size:15px;line-height:1.7;color:#555;margin-bottom:8px;">
						<?php esc_html_e( 'GrayFox helps small businesses build professional websites using AI. Your site will look great and feel consistent with your brand.', 'grayfox' ); ?>
					</div>
					<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
						<span id="grayfox-tb-body-font-label" style="font-size:13px;font-weight:600;color:#333;"></span>
						<a href="#" id="grayfox-tb-body-change-link" style="font-size:12px;color:#2271b1;">
							<?php esc_html_e( 'change font', 'grayfox' ); ?>
						</a>
					</div>
					<div id="grayfox-tb-body-change-panel" style="display:none;margin-top:10px;">
						<input type="text" id="grayfox-tb-body-font-input"
							   placeholder="<?php esc_attr_e( 'Type any Google Font name, e.g. Source Serif Pro', 'grayfox' ); ?>"
							   style="width:280px;">
						<button type="button" id="grayfox-tb-body-font-apply" class="button" style="margin-left:8px;">
							<?php esc_html_e( 'Apply', 'grayfox' ); ?>
						</button>
						<a href="https://fonts.google.com" target="_blank" style="font-size:12px;margin-left:10px;color:#2271b1;">
							<?php esc_html_e( 'Browse Google Fonts ↗', 'grayfox' ); ?>
						</a>
					</div>
					<input type="hidden" id="grayfox-tb-body-font" value="<?php echo esc_attr( $profile_typo['body_font'] ?? 'Inter' ); ?>">
				</div>
			</div>

			<!-- 4. Style Parameters — clickable option cards, not dropdowns -->
			<div style="margin-bottom:32px;">
				<div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px;">
					<h3 style="margin:0;"><?php esc_html_e( 'Design Style', 'grayfox' ); ?></h3>
					<span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2271b1;background:#e8f0fb;padding:2px 8px;border-radius:10px;">
						<?php esc_html_e( 'AI Selected', 'grayfox' ); ?>
					</span>
				</div>
				<p style="margin:0 0 16px;color:#666;font-size:13px;">
					<?php esc_html_e( 'These parameters guide how your pages look and feel. The highlighted option is what GrayFox recommends — click another if you prefer something different.', 'grayfox' ); ?>
				</p>

				<?php
				$style_groups = array(
					'visual_style' => array(
						'label'   => __( 'Visual Style', 'grayfox' ),
						'current' => $profile_style ?: 'clean',
						'options' => array(
							'clean'     => array( 'label' => __( 'Clean',     'grayfox' ), 'desc' => __( 'Professional, uncluttered, easy to navigate', 'grayfox' ) ),
							'bold'      => array( 'label' => __( 'Bold',      'grayfox' ), 'desc' => __( 'High impact, strong contrast, memorable', 'grayfox' ) ),
							'editorial' => array( 'label' => __( 'Editorial', 'grayfox' ), 'desc' => __( 'Refined, magazine-quality, sophisticated', 'grayfox' ) ),
							'minimal'   => array( 'label' => __( 'Minimal',   'grayfox' ), 'desc' => __( 'Less is more, lots of breathing room', 'grayfox' ) ),
							'technical' => array( 'label' => __( 'Technical', 'grayfox' ), 'desc' => __( 'Precise and structured, data-forward', 'grayfox' ) ),
						),
					),
					'spacing_style' => array(
						'label'   => __( 'Page Spacing', 'grayfox' ),
						'current' => $profile_spacing ?: 'comfortable',
						'options' => array(
							'tight'       => array( 'label' => __( 'Tight',       'grayfox' ), 'desc' => __( 'Compact, information-dense, efficient', 'grayfox' ) ),
							'comfortable' => array( 'label' => __( 'Comfortable', 'grayfox' ), 'desc' => __( 'Balanced rhythm, easy to read', 'grayfox' ) ),
							'spacious'    => array( 'label' => __( 'Spacious',    'grayfox' ), 'desc' => __( 'Airy layout, premium feel, lots of whitespace', 'grayfox' ) ),
						),
					),
				);
				foreach ( $style_groups as $param_key => $group ) :
				?>
				<div class="grayfox-tb-style-group" data-param="<?php echo esc_attr( $param_key ); ?>" style="margin-bottom:20px;">
					<div style="font-size:13px;font-weight:600;color:#23282d;margin-bottom:8px;">
						<?php echo esc_html( $group['label'] ); ?>
					</div>
					<div style="display:flex;gap:8px;flex-wrap:wrap;">
						<?php foreach ( $group['options'] as $val => $opt ) :
							$is_active = ( $group['current'] === $val );
						?>
						<div class="grayfox-tb-style-option <?php echo $is_active ? 'is-active' : ''; ?>"
							 data-param="<?php echo esc_attr( $param_key ); ?>"
							 data-value="<?php echo esc_attr( $val ); ?>"
							 style="padding:8px 14px;border:2px solid <?php echo $is_active ? '#2271b1' : '#ddd'; ?>;border-radius:4px;cursor:pointer;background:<?php echo $is_active ? '#f0f6ff' : '#fff'; ?>;min-width:100px;transition:all .15s;">
							<div style="font-weight:<?php echo $is_active ? '700' : '500'; ?>;font-size:13px;color:<?php echo $is_active ? '#2271b1' : '#333'; ?>;">
								<?php echo esc_html( $opt['label'] ); ?>
								<?php if ( $is_active ) : ?>
								<span style="font-size:10px;vertical-align:middle;margin-left:4px;">✓</span>
								<?php endif; ?>
							</div>
							<div style="font-size:11px;color:#888;margin-top:2px;line-height:1.3;"><?php echo esc_html( $opt['desc'] ); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<!-- Hidden input stores the current selection -->
					<input type="hidden" class="grayfox-tb-style-value" id="grayfox-tb-<?php echo esc_attr( str_replace( '_', '-', $param_key ) ); ?>"
						   data-param="<?php echo esc_attr( $param_key ); ?>"
						   value="<?php echo esc_attr( $group['current'] ); ?>">
				</div>
				<?php endforeach; ?>

			</div><!-- /style params -->

			<!-- Actions -->
			<div style="display:flex;gap:12px;align-items:center;padding-top:8px;border-top:1px solid #e0e0e0;">
				<button type="button" id="grayfox-tb-save-profile" class="button button-primary button-large">
					<?php esc_html_e( 'Accept & Continue →', 'grayfox' ); ?>
				</button>
				<button type="button" id="grayfox-tb-regenerate" class="button">
					<?php esc_html_e( '↺ Regenerate', 'grayfox' ); ?>
				</button>
				<span id="grayfox-tb-save-status" style="display:none;font-size:13px;color:#555;"></span>
			</div>

		</div><!-- /controls col -->

			<!-- ── Right: Live Preview panel ── -->
			<div style="width:300px;flex-shrink:0;position:sticky;top:32px;">
				<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#888;margin-bottom:10px;">
					<?php esc_html_e( 'Live Preview', 'grayfox' ); ?>
				</div>
				<div id="grayfox-tb-live-preview"
					 style="border-radius:8px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.16);font-size:10px;line-height:1;">
					<!-- Browser chrome -->
					<div style="background:#e8eaed;padding:7px 10px;display:flex;align-items:center;gap:5px;">
						<div style="width:8px;height:8px;border-radius:50%;background:#ff5f57;flex-shrink:0;"></div>
						<div style="width:8px;height:8px;border-radius:50%;background:#ffbd2e;flex-shrink:0;"></div>
						<div style="width:8px;height:8px;border-radius:50%;background:#28ca41;flex-shrink:0;"></div>
						<div style="flex:1;background:#fff;border-radius:3px;height:12px;margin-left:8px;"></div>
					</div>
					<!-- Nav -->
					<div class="gf-prev-nav" style="background:var(--gf-p,#1a2e4a);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
						<div style="width:60px;height:10px;background:rgba(255,255,255,.35);border-radius:2px;"></div>
						<div style="display:flex;gap:8px;">
							<div style="width:24px;height:6px;background:rgba(255,255,255,.4);border-radius:2px;"></div>
							<div style="width:24px;height:6px;background:rgba(255,255,255,.4);border-radius:2px;"></div>
							<div style="width:24px;height:6px;background:rgba(255,255,255,.4);border-radius:2px;"></div>
						</div>
					</div>
					<!-- Hero -->
					<div class="gf-prev-hero" style="background:linear-gradient(135deg,var(--gf-p,#1a2e4a),var(--gf-s,#2d6a8f));padding:24px 14px 22px;">
						<div style="font-family:var(--gf-hf,'Inter',sans-serif);font-size:15px;font-weight:700;color:#fff;line-height:1.25;margin-bottom:8px;">
							<?php esc_html_e( 'Your Business Name', 'grayfox' ); ?>
						</div>
						<div style="width:82%;height:5px;background:rgba(255,255,255,.35);border-radius:2px;margin-bottom:4px;"></div>
						<div style="width:66%;height:5px;background:rgba(255,255,255,.25);border-radius:2px;margin-bottom:14px;"></div>
						<div style="display:inline-block;background:var(--gf-a,#f4a723);padding:5px 16px;border-radius:4px;font-size:9px;font-weight:700;color:#fff;letter-spacing:.04em;">
							<?php esc_html_e( 'Get Started', 'grayfox' ); ?>
						</div>
					</div>
					<!-- Features (3 cards) -->
					<div class="gf-prev-features" style="background:var(--gf-bg,#fff);padding:14px 10px;">
						<div style="display:flex;gap:6px;">
							<?php for ( $i = 0; $i < 3; $i++ ) : ?>
							<div style="flex:1;background:#f8f9fa;border-radius:4px;padding:8px 6px;border-top:2px solid var(--gf-a,#f4a723);">
								<div style="width:14px;height:14px;border-radius:50%;background:var(--gf-p,#1a2e4a);margin-bottom:6px;"></div>
								<div style="height:5px;background:var(--gf-t,#1e1e1e);border-radius:2px;margin-bottom:4px;"></div>
								<div style="height:4px;background:var(--gf-m,#6b7280);border-radius:2px;width:80%;margin-bottom:3px;opacity:.6;"></div>
								<div style="height:4px;background:var(--gf-m,#6b7280);border-radius:2px;width:60%;opacity:.6;"></div>
							</div>
							<?php endfor; ?>
						</div>
					</div>
					<!-- Content block -->
					<div class="gf-prev-content" style="background:var(--gf-bg,#fff);padding:12px 14px;border-top:1px solid rgba(0,0,0,.06);">
						<div style="font-family:var(--gf-hf,'Inter',sans-serif);font-size:10px;font-weight:700;color:var(--gf-t,#1e1e1e);margin-bottom:6px;">
							<?php esc_html_e( 'About Us', 'grayfox' ); ?>
						</div>
						<div style="font-family:var(--gf-bf,'Inter',sans-serif);display:flex;flex-direction:column;gap:3px;">
							<div style="height:4px;background:var(--gf-m,#6b7280);border-radius:2px;opacity:.45;"></div>
							<div style="height:4px;background:var(--gf-m,#6b7280);border-radius:2px;width:90%;opacity:.45;"></div>
							<div style="height:4px;background:var(--gf-m,#6b7280);border-radius:2px;width:70%;opacity:.45;"></div>
						</div>
					</div>
					<!-- CTA band -->
					<div class="gf-prev-cta" style="background:linear-gradient(90deg,var(--gf-a,#f4a723),var(--gf-p,#1a2e4a));padding:14px;text-align:center;">
						<div style="height:6px;background:rgba(255,255,255,.5);border-radius:2px;width:55%;margin:0 auto 8px;"></div>
						<div style="display:inline-block;background:rgba(255,255,255,.25);border:1px solid rgba(255,255,255,.6);padding:3px 14px;border-radius:3px;height:10px;width:50px;"></div>
					</div>
					<!-- Footer -->
					<div class="gf-prev-footer" style="background:var(--gf-p,#1a2e4a);padding:14px;border-top:3px solid var(--gf-a,#f4a723);">
						<div style="display:flex;gap:14px;margin-bottom:10px;">
							<div style="flex:1;">
								<div style="height:6px;background:rgba(255,255,255,.5);border-radius:2px;margin-bottom:4px;width:70%;"></div>
								<div style="height:4px;background:rgba(255,255,255,.25);border-radius:2px;width:55%;"></div>
							</div>
							<div style="flex:1;">
								<div style="height:6px;background:rgba(255,255,255,.5);border-radius:2px;margin-bottom:4px;width:80%;"></div>
								<div style="height:4px;background:rgba(255,255,255,.25);border-radius:2px;width:60%;"></div>
							</div>
						</div>
						<div style="height:1px;background:rgba(255,255,255,.1);margin-bottom:8px;"></div>
						<div style="height:4px;background:rgba(255,255,255,.2);border-radius:2px;width:45%;margin:0 auto;"></div>
					</div>
				</div><!-- /#grayfox-tb-live-preview -->
				<!-- Font labels -->
				<div style="margin-top:10px;font-size:11px;color:#888;text-align:center;line-height:1.5;">
					<span id="grayfox-tb-prev-heading-font" style="font-weight:600;color:#555;"></span><br>
					<span id="grayfox-tb-prev-body-font" style="color:#aaa;font-size:10px;"></span>
				</div>
			</div><!-- /preview col -->

		</div><!-- /flex wrapper -->
		</div><!-- /#grayfox-tb-results -->
	</div>

	<!-- ═══════════════════════════════════════════════════════════════════
	     STEP 3 — Theme Manager
	     ═══════════════════════════════════════════════════════════════════ -->
	<div class="grayfox-step grayfox-tb-step" data-step="3" style="display:none;">
		<h2><?php esc_html_e( 'Step 3: Theme Manager', 'grayfox' ); ?></h2>
		<p style="color:#555;">
			<?php
			echo esc_html( sprintf(
				/* translators: %1$d: current count, %2$d: max */
				__( 'You can generate up to %2$d GrayFox themes. Each one is a standalone WordPress parent theme you can activate in Appearance → Themes. You currently have %1$d of %2$d.', 'grayfox' ),
				$themes_count,
				GrayFox_ThemeBuilder::MAX_THEMES
			) );
			?>
		</p>

		<!-- ── Generated Themes List ── -->
		<?php if ( ! empty( $generated_themes ) ) : ?>
		<div id="grayfox-tb-themes-list" style="max-width:720px;margin:20px 0 28px;">
			<div style="font-size:13px;font-weight:600;color:#23282d;margin-bottom:10px;">
				<?php esc_html_e( 'Generated Themes', 'grayfox' ); ?>
				<span id="grayfox-tb-themes-count" style="font-weight:400;color:#888;margin-left:6px;"><?php echo esc_html( $themes_count . ' / ' . GrayFox_ThemeBuilder::MAX_THEMES ); ?></span>
			</div>
			<?php foreach ( $generated_themes as $theme ) :
				$t_slug      = $theme['slug']         ?? '';
				$t_name      = $theme['display_name'] ?? $t_slug;
				$t_style     = $theme['visual_style']  ?? '';
				$t_date      = ! empty( $theme['generated_at'] ) ? date_i18n( get_option( 'date_format' ), $theme['generated_at'] ) : '—';
				$t_is_active = ( $active_theme_slug === $t_slug );
				$activate_url = wp_nonce_url(
					admin_url( 'themes.php?action=activate&stylesheet=' . rawurlencode( $t_slug ) ),
					'switch-theme_' . $t_slug
				);
			?>
			<div class="grayfox-tb-theme-card" data-slug="<?php echo esc_attr( $t_slug ); ?>"
				 style="display:flex;align-items:center;gap:16px;padding:14px 18px;margin-bottom:8px;border:2px solid <?php echo $t_is_active ? '#2271b1' : '#ddd'; ?>;border-radius:6px;background:<?php echo $t_is_active ? '#f0f6ff' : '#fff'; ?>;">

				<!-- Color dots -->
				<div style="display:flex;gap:4px;flex-shrink:0;">
					<?php
					$snapshot_colors = $profile_colors;
					foreach ( array( 'primary', 'secondary', 'accent' ) as $ck ) :
						$c = $snapshot_colors[ $ck ] ?? '#ccc';
					?>
					<div style="width:16px;height:16px;border-radius:50%;background:<?php echo esc_attr( $c ); ?>;border:1px solid rgba(0,0,0,.1);"></div>
					<?php endforeach; ?>
				</div>

				<!-- Info -->
				<div style="flex:1;min-width:0;">
					<div style="font-weight:600;font-size:13px;color:#1e1e1e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( $t_name ); ?></div>
					<div style="font-size:11px;color:#888;margin-top:2px;">
						<?php if ( $t_style ) : ?>
						<span style="background:#f0f0f0;border-radius:3px;padding:1px 6px;margin-right:6px;"><?php echo esc_html( ucfirst( $t_style ) ); ?></span>
						<?php endif; ?>
						<?php echo esc_html( $t_date ); ?>
					</div>
				</div>

				<!-- Active badge or Activate button -->
				<?php if ( $t_is_active ) : ?>
				<span style="background:#2271b1;color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:10px;white-space:nowrap;flex-shrink:0;">
					<?php esc_html_e( 'Active', 'grayfox' ); ?>
				</span>
				<?php else : ?>
				<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-small" style="flex-shrink:0;">
					<?php esc_html_e( 'Activate', 'grayfox' ); ?>
				</a>
				<?php endif; ?>

				<!-- Delete button -->
				<button type="button"
						class="grayfox-tb-delete-theme button button-small"
						data-slug="<?php echo esc_attr( $t_slug ); ?>"
						data-name="<?php echo esc_attr( $t_name ); ?>"
						style="flex-shrink:0;color:#d63638;border-color:#d63638;"
						<?php echo $t_is_active ? 'disabled title="' . esc_attr__( 'Switch to another theme first.', 'grayfox' ) . '"' : ''; ?>>
					<?php esc_html_e( 'Delete', 'grayfox' ); ?>
				</button>

			</div>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
		<p id="grayfox-tb-no-themes" style="color:#888;font-style:italic;margin:16px 0 24px;">
			<?php esc_html_e( 'No themes generated yet. Use the current brand profile below to create your first one.', 'grayfox' ); ?>
		</p>
		<?php endif; ?>

		<!-- ── Create New Theme ── -->
		<div id="grayfox-tb-create-section" <?php echo ! $can_create ? 'style="display:none;"' : ''; ?>>
			<div style="border-top:1px solid #e0e0e0;padding-top:20px;max-width:720px;">
				<div style="font-size:13px;font-weight:600;color:#23282d;margin-bottom:12px;">
					<?php esc_html_e( 'Create New Theme from Current Brand Profile', 'grayfox' ); ?>
				</div>

				<div id="grayfox-tb-s3-colors" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
					<?php foreach ( array( 'primary', 'secondary', 'accent', 'background', 'text', 'muted' ) as $key ) : ?>
					<div style="text-align:center;">
						<div class="grayfox-tb-s3-dot"
							 data-color-key="<?php echo esc_attr( $key ); ?>"
							 style="width:36px;height:36px;border-radius:6px;border:1px solid rgba(0,0,0,.1);background:<?php echo esc_attr( $profile_colors[ $key ] ?? '#ccc' ); ?>;"></div>
						<div style="font-size:10px;margin-top:3px;color:#666;"><?php echo esc_html( ucfirst( $key ) ); ?></div>
					</div>
					<?php endforeach; ?>
				</div>
				<p id="grayfox-tb-s3-meta" style="margin:0 0 14px;font-size:13px;color:#555;">
					<strong><?php esc_html_e( 'Style:', 'grayfox' ); ?></strong>
					<span id="grayfox-tb-s3-style"><?php echo esc_html( ucfirst( $profile_style ?: '—' ) ); ?></span>
					&nbsp;·&nbsp;
					<strong><?php esc_html_e( 'Heading:', 'grayfox' ); ?></strong>
					<span id="grayfox-tb-s3-heading-font"><?php echo esc_html( $profile_typo['heading_font'] ?? '—' ); ?></span>
					&nbsp;·&nbsp;
					<strong><?php esc_html_e( 'Body:', 'grayfox' ); ?></strong>
					<span id="grayfox-tb-s3-body-font"><?php echo esc_html( $profile_typo['body_font'] ?? '—' ); ?></span>
				</p>

				<?php if ( $elementor_active ) : ?>
				<label style="display:block;margin-bottom:14px;font-size:13px;">
					<input type="checkbox" id="grayfox-tb-apply-elementor" checked style="margin-right:6px;">
					<?php esc_html_e( 'Also update Elementor Global Colors and Typography', 'grayfox' ); ?>
				</label>
				<?php else : ?>
				<input type="hidden" id="grayfox-tb-apply-elementor" value="0">
				<?php endif; ?>

				<div style="display:flex;gap:12px;align-items:center;">
					<button type="button" id="grayfox-tb-apply-btn" class="button button-primary button-large" <?php disabled( ! $has_profile ); ?>>
						<?php esc_html_e( 'Create Theme →', 'grayfox' ); ?>
					</button>
					<span id="grayfox-tb-apply-status" style="display:none;">
						<span class="spinner is-active" style="float:none;vertical-align:middle;"></span>
						<em><?php esc_html_e( 'Generating theme files…', 'grayfox' ); ?></em>
					</span>
					<span id="grayfox-tb-apply-error" style="display:none;color:#d63638;font-size:13px;"></span>
				</div>
			</div>
		</div>

		<!-- ── Limit reached notice ── -->
		<div id="grayfox-tb-limit-notice" <?php echo $can_create ? 'style="display:none;"' : ''; ?>
			 class="notice notice-warning inline" style="max-width:720px;margin-top:20px;">
			<p>
				<strong><?php esc_html_e( 'Theme limit reached.', 'grayfox' ); ?></strong>
				<?php
				echo esc_html( sprintf(
					/* translators: %d: max themes */
					__( 'You have %d GrayFox themes. Delete one from the list above to generate a new one.', 'grayfox' ),
					GrayFox_ThemeBuilder::MAX_THEMES
				) );
				?>
			</p>
		</div>

	</div>

	<!-- ═══════════════════════════════════════════════════════════════════
	     STEP 4 — Confirmation
	     ═══════════════════════════════════════════════════════════════════ -->
	<div class="grayfox-step grayfox-tb-step" data-step="4" style="display:none;">
		<h2><?php esc_html_e( 'Theme Created!', 'grayfox' ); ?></h2>
		<div class="notice notice-success inline" style="margin:0 0 20px;">
			<p id="grayfox-tb-done-detail"><?php esc_html_e( 'Your new GrayFox theme is ready.', 'grayfox' ); ?></p>
		</div>

		<div style="padding:20px 24px;background:#f0f6ff;border:1px solid #c3d9f0;max-width:600px;border-radius:4px;margin-bottom:20px;">
			<p style="margin:0 0 10px;font-size:15px;font-weight:600;color:#23282d;"><?php esc_html_e( 'Next: activate your theme', 'grayfox' ); ?></p>
			<p style="margin:0 0 16px;color:#555;"><?php esc_html_e( 'The theme was added to your themes directory. Click below to go to Appearance → Themes and activate it.', 'grayfox' ); ?></p>
			<a id="grayfox-tb-activate-link" href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>" class="button button-primary button-large">
				<?php esc_html_e( 'Activate in Appearance → Themes', 'grayfox' ); ?>
			</a>
		</div>

		<div style="padding:14px 16px;background:#f8f9fa;border:1px solid #e0e0e0;max-width:600px;border-radius:4px;margin-bottom:20px;">
			<p style="margin:0;color:#555;font-size:13px;">
				<?php esc_html_e( 'Your brand profile is saved. The Site Builder will use these colors and style when generating pages — regardless of which theme is active.', 'grayfox' ); ?>
			</p>
		</div>

		<div style="display:flex;gap:12px;flex-wrap:wrap;">
			<button type="button" id="grayfox-tb-back-to-manager" class="button">
				<?php esc_html_e( '← Back to Theme Manager', 'grayfox' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-site-builder' ) ); ?>" class="button">
				<?php esc_html_e( 'Go to Site Builder', 'grayfox' ); ?>
			</a>
		</div>
	</div>

</div><!-- .grayfox-admin-wrap -->
