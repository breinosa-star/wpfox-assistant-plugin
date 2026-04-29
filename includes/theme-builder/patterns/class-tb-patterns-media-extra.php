<?php
/**
 * Extended media renderers: video-embed-section, image-gallery-grid, before-after-slider,
 * document-preview, audio-player-section.
 *
 * Ported from wp-theme-builder/src/pattern_builder.py
 *
 * @package GrayFox
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// -- This file is a PHP code generator (ob_start/ob_get_clean). Its output is
// -- written to on-disk theme files, not echoed to the browser. Variables are
// -- pre-sanitized (esc_html/esc_attr/intval) at the point of user input.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_TB_Patterns_Media_Extra
 */
class GrayFox_TB_Patterns_Media_Extra {

	// -------------------------------------------------------------------------
	// video-embed-section — centered video embed placeholder
	// -------------------------------------------------------------------------

	public static function render_video_embed_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-video-embed';

		$heading   = esc_html( $copy['section_heading'] ?? '' );
		$caption   = esc_html( $copy['caption']         ?? '' );
		$cta_label = esc_html( $copy['cta_label']       ?? '' );

		if ( $heading ) {
			ob_start(); ?>
<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

			<?php $heading_block = ob_get_clean();
		} else {
			$heading_block = '';
		}

		if ( $caption ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $caption; ?></p>
<!-- /wp:paragraph -->
			<?php $caption_block = ob_get_clean();
		} else {
			$caption_block = '';
		}

		if ( $cta_label ) {
			ob_start(); ?>
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_label; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
			<?php $cta_block = ob_get_clean();
		} else {
			$cta_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background","layout":{"type":"constrained","contentSize":"960px"}} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<?php echo $heading_block; ?><!-- wp:embed {"url":"","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
<div class="wp-block-embed__wrapper">
<!-- Replace this comment with your YouTube or Vimeo URL -->
</div>
</figure>
<!-- /wp:embed -->

<?php echo $caption_block; ?>
<?php echo $cta_block; ?>

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// image-gallery-grid — uniform image grid
	// -------------------------------------------------------------------------

	public static function render_image_gallery_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-gallery-grid';

		$heading     = esc_html( $copy['section_heading'] ?? '' );
		$columns     = max( 2, min( 4, (int) ( $copy['columns'] ?? 3 ) ) );
		$image_count = max( 2, min( 12, (int) ( $copy['image_count'] ?? 6 ) ) );

		if ( $heading ) {
			ob_start(); ?>
<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

			<?php $heading_block = ob_get_clean();
		} else {
			$heading_block = '';
		}

		$images = '';
		for ( $i = 1; $i <= $image_count; $i++ ) {
			$alt = esc_attr( $copy[ "image_{$i}_alt" ] ?? "Gallery image {$i}" );
			ob_start(); ?>
<!-- wp:image {"sizeSlug":"large","className":"gf-gallery-img"} -->
<figure class="wp-block-image size-large gf-gallery-img"><img src="" alt="<?php echo $alt; ?>"/></figure>
<!-- /wp:image -->
			<?php $images .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<?php echo $heading_block; ?><!-- wp:gallery {"columns":<?php echo $columns; ?>,"linkTo":"none","sizeSlug":"large","style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<figure class="wp-block-gallery has-nested-images columns-<?php echo $columns; ?> is-cropped">
<?php echo $images; ?>
</figure>
<!-- /wp:gallery -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// before-after-slider — CSS split-view comparison
	// -------------------------------------------------------------------------

	public static function render_before_after_slider( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-before-after';

		$heading      = esc_html( $copy['section_heading'] ?? 'See the Difference' );
		$before_label = esc_html( $copy['before_label']    ?? 'Before' );
		$after_label  = esc_html( $copy['after_label']     ?? 'After' );
		$before_alt   = esc_attr( $copy['before_alt']      ?? 'Before state' );
		$after_alt    = esc_attr( $copy['after_alt']       ?? 'After state' );
		$caption      = esc_html( $copy['caption']         ?? '' );

		$caption_html = $caption ? '<p style="text-align:center;color:var(--wp--preset--color--muted);margin-top:1rem;font-size:0.9rem">' . $caption . '</p>' : '';

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full","backgroundColor":"background","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"900px"}} -->
<section class="wp-block-group <?php echo $css; ?> alignfull has-background-background-color has-background py-5">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<style>
.gf-ba-wrap { position:relative;overflow:hidden;border-radius:12px;aspect-ratio:16/9;background:var(--wp--preset--color--muted,#e5e7eb);margin-top:2rem }
.gf-ba-before,.gf-ba-after { position:absolute;inset:0;width:100%;height:100%;object-fit:cover }
.gf-ba-after { clip-path:inset(0 50% 0 0); }
.gf-ba-label { position:absolute;top:1rem;padding:0.3rem 0.75rem;border-radius:999px;font-size:0.8rem;font-weight:700;color:#fff }
.gf-ba-label-before { left:1rem;background:rgba(0,0,0,0.5) }
.gf-ba-label-after  { right:1rem;background:var(--wp--preset--color--accent) }
.gf-ba-divider { position:absolute;top:0;bottom:0;left:50%;width:3px;background:#fff;cursor:ew-resize }
</style>
<div class="gf-ba-wrap" title="Drag to compare">
  <img class="gf-ba-before" src="" alt="<?php echo $before_alt; ?>"/>
  <img class="gf-ba-after"  src="" alt="<?php echo $after_alt; ?>"/>
  <span class="gf-ba-label gf-ba-label-before"><?php echo $before_label; ?></span>
  <span class="gf-ba-label gf-ba-label-after"><?php echo $after_label; ?></span>
  <div class="gf-ba-divider"></div>
</div>
<?php echo $caption_html; ?>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// document-preview — PDF/document placeholder + download CTA
	// -------------------------------------------------------------------------

	public static function render_document_preview( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-doc-preview';

		$heading     = esc_html( $copy['section_heading'] ?? 'Download Our Guide' );
		$doc_title   = esc_html( $copy['doc_title']       ?? 'The Complete Finance Team Playbook' );
		$doc_meta    = esc_html( $copy['doc_meta']        ?? 'PDF · 24 pages · Free' );
		$description = esc_html( $copy['description']     ?? 'Everything your finance team needs to close faster, reduce errors, and operate at scale.' );
		$cta_label   = esc_html( $copy['cta_label']       ?? 'Download Free' );
		$trust_note  = esc_html( $copy['trust_note']      ?? 'No email required' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center","className":"container"} -->
<div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center container">

<!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%">
<!-- wp:html -->
<div style="background:var(--wp--preset--color--muted,#f3f4f6);border-radius:12px;aspect-ratio:3/4;display:flex;align-items:center;justify-content:center;border:1px solid rgba(0,0,0,0.08)">
  <div style="text-align:center;color:var(--wp--preset--color--muted)">
    <i class="bi bi-file-earmark-pdf-fill" style="font-size:4rem;color:var(--wp--preset--color--accent)"></i>
    <p style="margin:0.5rem 0 0;font-weight:600;color:var(--wp--preset--color--primary)"><?php echo $doc_title; ?></p>
    <p style="font-size:0.8rem;margin:0.25rem 0 0"><?php echo $doc_meta; ?></p>
  </div>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%">
<!-- wp:heading {"level":3,"textColor":"primary"} -->
<h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $doc_title; ?></h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $description; ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"className":"mt-4"} -->
<div class="wp-block-buttons mt-4">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_label; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
<!-- wp:paragraph {"textColor":"muted","className":"small mt-2"} -->
<p class="has-muted-color has-text-color small mt-2"><?php echo $trust_note; ?></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// audio-player-section — podcast episode list
	// -------------------------------------------------------------------------

	public static function render_audio_player_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-audio-section';

		$heading  = esc_html( $copy['section_heading'] ?? 'Listen to Our Podcast' );
		$subtext  = esc_html( $copy['subtext']         ?? 'Subscribe on your favourite platform.' );

		$episodes = [
			[
				'num'   => 'EP 1',
				'title' => esc_html( $copy['ep1_title'] ?? 'Episode 1: Getting Started' ),
				'desc'  => esc_html( $copy['ep1_desc']  ?? 'An introduction to the show and what to expect.' ),
				'dur'   => esc_html( $copy['ep1_dur']   ?? '24:00' ),
				'wave'  => '4,32 8,18 12,38 16,22 20,40 24,14 28,36 32,20 36,34 40,10 44,28 48,40 52,16 56,32 60,8 64,38 68,24 72,40 76,18 80,30 84,12 88,36 92,22 96,40 100,16 104,28 108,10 112,34 116,20 120,38',
			],
			[
				'num'   => 'EP 2',
				'title' => esc_html( $copy['ep2_title'] ?? 'Episode 2: Deep Dive' ),
				'desc'  => esc_html( $copy['ep2_desc']  ?? 'We go deep on the most important topics.' ),
				'dur'   => esc_html( $copy['ep2_dur']   ?? '31:15' ),
				'wave'  => '4,20 8,36 12,14 16,40 20,28 24,10 28,38 32,24 36,16 40,36 44,8 48,32 52,40 56,18 60,30 64,12 68,40 72,22 76,36 80,16 84,28 88,40 92,10 96,34 100,20 104,38 108,14 112,30 116,8 120,36',
			],
			[
				'num'   => 'EP 3',
				'title' => esc_html( $copy['ep3_title'] ?? 'Episode 3: Expert Interview' ),
				'desc'  => esc_html( $copy['ep3_desc']  ?? 'Insights from a leading industry expert.' ),
				'dur'   => esc_html( $copy['ep3_dur']   ?? '41:38' ),
				'wave'  => '4,38 8,12 12,30 16,40 20,18 24,36 28,8 32,40 36,26 40,14 44,38 48,20 52,36 56,10 60,40 64,24 68,16 72,38 76,12 80,34 84,22 88,40 92,18 96,30 100,8 104,36 108,24 112,40 116,14 120,32',
			],
		];

		$rows = '';
		foreach ( $episodes as $ep ) {
			$bars = '';
			$pts  = explode( ' ', $ep['wave'] );
			foreach ( $pts as $pt ) {
				[ $x, $h ] = explode( ',', $pt );
				$y = 40 - $h;
				$bars .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"3\" height=\"{$h}\" rx=\"1.5\" fill=\"var(--gf-primary)\" opacity=\"0.25\"/>";
			}
			ob_start(); ?>
<div class="gf-audio-item">
  <button class="gf-audio-play-btn" type="button" aria-label="Play <?php echo $ep['title']; ?>">
    <i class="bi bi-play-circle-fill"></i>
  </button>
  <div class="gf-audio-waveform" aria-hidden="true">
    <svg viewBox="0 0 124 40" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><?php echo $bars; ?></svg>
  </div>
  <div class="gf-audio-info">
    <div class="gf-audio-title"><?php echo $ep['title']; ?></div>
    <div class="gf-audio-desc"><?php echo $ep['desc']; ?></div>
  </div>
  <div class="gf-audio-meta">
    <span class="gf-audio-ep-badge"><?php echo $ep['num']; ?></span>
    <span class="gf-audio-dur"><i class="bi bi-clock me-1"></i><?php echo $ep['dur']; ?></span>
  </div>
</div>
			<?php $rows .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="container mt-4">
  <div class="gf-audio-list">
<?php echo $rows; ?>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'video-embed-section'  => [ GrayFox_TB_Patterns_Media_Extra::class, 'render_video_embed_section' ],
	'image-gallery-grid'   => [ GrayFox_TB_Patterns_Media_Extra::class, 'render_image_gallery_grid' ],
	'before-after-slider'  => [ GrayFox_TB_Patterns_Media_Extra::class, 'render_before_after_slider' ],
	'document-preview'     => [ GrayFox_TB_Patterns_Media_Extra::class, 'render_document_preview' ],
	'audio-player-section' => [ GrayFox_TB_Patterns_Media_Extra::class, 'render_audio_player_section' ],
] );
