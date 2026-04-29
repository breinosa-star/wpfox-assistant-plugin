<?php
/**
 * Gallery / video pattern renderers.
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
 * Class GrayFox_TB_Patterns_Galleries
 */
class GrayFox_TB_Patterns_Galleries {

	// -------------------------------------------------------------------------
	// masonry-gallery — CSS-columns masonry image grid with zoom overlay
	// -------------------------------------------------------------------------

	public static function render_masonry_gallery( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-masonry-gallery';

		$heading   = esc_html( $copy['section_heading'] ?? 'Our Gallery' );
		$columns   = intval( $copy['columns']           ?? 3 );
		$cta_label = esc_html( $copy['cta_label']       ?? 'View all photos' );
		$cta_url   = esc_attr( $copy['cta_url']         ?? '#' );

		$images = [];
		for ( $i = 1; $i <= 9; $i++ ) {
			$alt = esc_attr( $copy[ "image_{$i}_alt" ] ?? "Gallery image {$i}" );
			$images[] = $alt;
		}

		$items_html = '';
		foreach ( $images as $alt ) {
			ob_start(); ?>
<div class="gf-masonry-item">
  <img src="" alt="<?php echo $alt; ?>" class="gf-masonry-img"/>
  <div class="gf-masonry-overlay" aria-hidden="true">
    <span class="gf-masonry-zoom-icon"><i class="bi bi-zoom-in"></i></span>
  </div>
</div>

			<?php $items_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="gf-masonry-grid" style="columns:<?php echo $columns; ?>;column-gap:1rem;margin-top:2rem">
<?php echo $items_html; ?>
</div>
<!-- /wp:html -->

<!-- wp:buttons {"className":"justify-content-center mt-4"} -->
<div class="wp-block-buttons justify-content-center mt-4">
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo $cta_url; ?>"><?php echo $cta_label; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// slider-gallery — JS carousel with prev/next dots
	// -------------------------------------------------------------------------

	public static function render_slider_gallery( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-slider-gallery';

		$heading = esc_html( $copy['section_heading'] ?? 'Featured Photos' );

		$slides = [];
		for ( $i = 1; $i <= 7; $i++ ) {
			$alt     = $copy[ "slide_{$i}_alt" ]     ?? null;
			$caption = $copy[ "slide_{$i}_caption" ] ?? '';
			if ( $alt !== null ) {
				$slides[] = [ 'alt' => esc_attr( $alt ), 'caption' => esc_html( $caption ) ];
			}
		}
		if ( empty( $slides ) ) {
			$slides = [
				[ 'alt' => 'Slide 1', 'caption' => 'Our beautiful workspace' ],
				[ 'alt' => 'Slide 2', 'caption' => 'Team collaboration in action' ],
				[ 'alt' => 'Slide 3', 'caption' => 'Behind the scenes' ],
			];
		}

		$slides_html = '';
		foreach ( $slides as $idx => $slide ) {
			$active = ( $idx === 0 ) ? ' gf-slide--active' : '';
			ob_start(); ?>
<div class="gf-slide<?php echo $active; ?>">
  <img src="" alt="<?php echo $slide['alt']; ?>" class="gf-slide-img"/>
  <div class="gf-slide-caption"><?php echo $slide['caption']; ?></div>
</div>

			<?php $slides_html .= ob_get_clean();
		}

		$dots_html = '';
		foreach ( $slides as $idx => $slide ) {
			$active    = ( $idx === 0 ) ? ' gf-dot--active' : '';
			$dots_html .= "<button class=\"gf-slider-dot{$active}\" aria-label=\"Slide " . ( $idx + 1 ) . "\"></button>\n";
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="gf-slider" style="position:relative;overflow:hidden;margin-top:2rem">
  <div class="gf-slider-track">
<?php echo $slides_html; ?>
  </div>
  <button class="gf-slider-prev" aria-label="Previous slide" onclick="gfSliderPrev(this)">&#8249;</button>
  <button class="gf-slider-next" aria-label="Next slide" onclick="gfSliderNext(this)">&#8250;</button>
  <div class="gf-slider-dots">
<?php echo $dots_html; ?>
  </div>
</div>
<script>
(function(){
  function getSibling(btn,cls){return btn.closest('.gf-slider').querySelector(cls);}
  function getSlides(btn){return btn.closest('.gf-slider').querySelectorAll('.gf-slide');}
  function getDots(btn){return btn.closest('.gf-slider').querySelectorAll('.gf-slider-dot');}
  function goTo(btn,idx){
    var slides=getSlides(btn),dots=getDots(btn);
    slides.forEach(function(s,i){s.classList.toggle('gf-slide--active',i===idx);});
    dots.forEach(function(d,i){d.classList.toggle('gf-dot--active',i===idx);});
  }
  window.gfSliderPrev=function(btn){
    var slides=getSlides(btn),cur=Array.from(slides).findIndex(function(s){return s.classList.contains('gf-slide--active');});
    goTo(btn,(cur-1+slides.length)%slides.length);
  };
  window.gfSliderNext=function(btn){
    var slides=getSlides(btn),cur=Array.from(slides).findIndex(function(s){return s.classList.contains('gf-slide--active');});
    goTo(btn,(cur+1)%slides.length);
  };
})();
</script>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// lightbox-grid — CSS grid of 12 images with modal lightbox overlay
	// -------------------------------------------------------------------------

	public static function render_lightbox_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-lightbox-grid';

		$heading = esc_html( $copy['section_heading'] ?? 'Gallery' );
		$columns = intval( $copy['columns']           ?? 4 );

		$thumbs_html = '';
		for ( $i = 1; $i <= 12; $i++ ) {
			$alt          = esc_attr( $copy[ "image_{$i}_alt" ] ?? "Photo {$i}" );
			ob_start(); ?>
<button class="gf-lightbox-thumb" onclick="gfLightboxOpen(this,<?php echo $i; ?>)" aria-label="Open photo <?php echo $i; ?>">
  <img src="" alt="<?php echo $alt; ?>" class="gf-lightbox-thumb-img"/>
  <div class="gf-lightbox-thumb-overlay" aria-hidden="true"><i class="bi bi-zoom-in"></i></div>
</button>

			<?php $thumbs_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="gf-lightbox-grid-wrap" style="display:grid;grid-template-columns:repeat(<?php echo $columns; ?>,1fr);gap:0.5rem;margin-top:2rem">
<?php echo $thumbs_html; ?>
</div>

<div id="gf-lightbox-modal" class="gf-lightbox-modal" style="display:none;" aria-modal="true" role="dialog">
  <button class="gf-lightbox-close" onclick="gfLightboxClose()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
  <button class="gf-lightbox-prev" onclick="gfLightboxPrev()" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
  <img id="gf-lightbox-img" src="" alt="" class="gf-lightbox-img"/>
  <button class="gf-lightbox-next" onclick="gfLightboxNext()" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// album-index — 3-column grid of album tiles with cover image and count
	// -------------------------------------------------------------------------

	public static function render_album_index( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-album-index';

		$heading = esc_html( $copy['section_heading'] ?? 'Photo Albums' );
		$subtext = esc_html( $copy['subtext']         ?? '' );

		$albums = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$title = $copy[ "album_{$i}_title" ] ?? null;
			if ( $title !== null ) {
				$albums[] = [
					'title' => esc_html( $title ),
					'count' => esc_html( $copy[ "album_{$i}_count" ] ?? '' ),
					'url'   => esc_attr( $copy[ "album_{$i}_url" ]   ?? '#' ),
				];
			}
		}
		if ( empty( $albums ) ) {
			$albums = [
				[ 'title' => 'Summer 2024',       'count' => '42 photos', 'url' => '#' ],
				[ 'title' => 'Product Launch',    'count' => '28 photos', 'url' => '#' ],
				[ 'title' => 'Team Offsite',      'count' => '67 photos', 'url' => '#' ],
				[ 'title' => 'Conference 2024',   'count' => '35 photos', 'url' => '#' ],
				[ 'title' => 'Behind the Scenes', 'count' => '19 photos', 'url' => '#' ],
				[ 'title' => 'Client Events',     'count' => '54 photos', 'url' => '#' ],
			];
		}

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		$cards_html = '';
		foreach ( $albums as $album ) {
			$count_html   = $album['count'] ? '<span class="gf-album-count">' . $album['count'] . '</span>' : '';
			ob_start(); ?>
<div class="col-sm-6 col-md-4">
<a href="<?php echo $album['url']; ?>" class="gf-album-tile d-block h-100">
  <div class="gf-album-cover">
    <img src="" alt="<?php echo $album['title']; ?>" class="gf-album-img"/>
    <div class="gf-album-overlay"></div>
  </div>
  <div class="gf-album-meta">
    <span class="gf-album-title"><?php echo $album['title']; ?></span>
    <?php echo $count_html; ?>
  </div>
</a>
</div>

			<?php $cards_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<?php echo $subtext_block; ?>

<!-- wp:html -->
<div class="row g-4 mt-3">
<?php echo $cards_html; ?>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// video-grid — 3-column video card grid with play button and duration
	// -------------------------------------------------------------------------

	public static function render_video_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-video-grid';

		$heading   = esc_html( $copy['section_heading'] ?? 'Watch and Learn' );
		$subtext   = esc_html( $copy['subtext']         ?? '' );
		$cta_label = esc_html( $copy['cta_label']       ?? 'View all videos' );
		$cta_url   = esc_attr( $copy['cta_url']         ?? '#' );

		$videos = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$title = $copy[ "video_{$i}_title" ] ?? null;
			if ( $title !== null ) {
				$videos[] = [
					'title'    => esc_html( $title ),
					'duration' => esc_html( $copy[ "video_{$i}_duration" ] ?? '' ),
					'url'      => esc_attr( $copy[ "video_{$i}_url" ]      ?? '#' ),
				];
			}
		}
		if ( empty( $videos ) ) {
			$videos = [
				[ 'title' => 'Getting started in 5 minutes', 'duration' => '5:12',  'url' => '#' ],
				[ 'title' => 'Advanced configuration',        'duration' => '12:34', 'url' => '#' ],
				[ 'title' => 'Team collaboration features',   'duration' => '8:47',  'url' => '#' ],
				[ 'title' => 'Integrations walkthrough',      'duration' => '7:20',  'url' => '#' ],
				[ 'title' => 'Reporting and analytics',       'duration' => '9:05',  'url' => '#' ],
				[ 'title' => 'Tips and best practices',       'duration' => '6:33',  'url' => '#' ],
			];
		}

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		$cards_html = '';
		foreach ( $videos as $video ) {
			$duration_html = $video['duration'] ? '<span class="gf-video-duration">' . $video['duration'] . '</span>' : '';
			ob_start(); ?>
<div class="col-md-4">
<div class="gf-video-card">
  <a href="<?php echo $video['url']; ?>" class="gf-video-thumb-link">
    <div class="gf-video-thumb">
      <img src="" alt="<?php echo $video['title']; ?>" class="gf-video-thumb-img"/>
      <div class="gf-video-play-btn" aria-label="Play video">&#9654;</div>
      <?php echo $duration_html; ?>
    </div>
  </a>
  <div class="gf-video-card-body">
    <h3 class="gf-video-card-title"><a href="<?php echo $video['url']; ?>"><?php echo $video['title']; ?></a></h3>
  </div>
</div>
</div>

			<?php $cards_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<?php echo $subtext_block; ?>

<!-- wp:html -->
<div class="row g-4 mt-3">
<?php echo $cards_html; ?>
</div>
<!-- /wp:html -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2rem"}}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo $cta_url; ?>"><?php echo $cta_label; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// video-playlist — featured player + episode list sidebar
	// -------------------------------------------------------------------------

	public static function render_video_playlist( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-video-playlist';

		$heading        = esc_html( $copy['section_heading'] ?? 'Video Series' );
		$subtext        = esc_html( $copy['subtext']         ?? '' );
		$featured_title = esc_html( $copy['featured_title']  ?? 'Episode 1: Getting started' );
		$featured_url   = esc_attr( $copy['featured_url']    ?? '#' );
		$featured_desc  = esc_html( $copy['featured_desc']   ?? 'Learn the fundamentals and set up your workspace in under 10 minutes.' );

		$episodes = [];
		for ( $i = 1; $i <= 8; $i++ ) {
			$title = $copy[ "ep_{$i}_title" ] ?? null;
			if ( $title !== null ) {
				$episodes[] = [
					'num'      => $i,
					'title'    => esc_html( $title ),
					'duration' => esc_html( $copy[ "ep_{$i}_duration" ] ?? '' ),
					'url'      => esc_attr( $copy[ "ep_{$i}_url" ]      ?? '#' ),
				];
			}
		}
		if ( empty( $episodes ) ) {
			$episodes = [
				[ 'num' => 1, 'title' => 'Getting started',     'duration' => '5:12',  'url' => '#' ],
				[ 'num' => 2, 'title' => 'Core concepts',        'duration' => '8:34',  'url' => '#' ],
				[ 'num' => 3, 'title' => 'Advanced features',    'duration' => '12:05', 'url' => '#' ],
				[ 'num' => 4, 'title' => 'Integrations',         'duration' => '7:47',  'url' => '#' ],
				[ 'num' => 5, 'title' => 'Team workflows',       'duration' => '9:20',  'url' => '#' ],
				[ 'num' => 6, 'title' => 'Reporting deep-dive',  'duration' => '11:33', 'url' => '#' ],
			];
		}

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		$ep_items = '';
		foreach ( $episodes as $idx => $ep ) {
			$active        = ( $idx === 0 ) ? ' gf-playlist-item--active' : '';
			$duration_html = $ep['duration'] ? '<span class="gf-playlist-duration">' . $ep['duration'] . '</span>' : '';
			$num           = str_pad( (string) $ep['num'], 2, '0', STR_PAD_LEFT );
			ob_start(); ?>
<a href="<?php echo $ep['url']; ?>" class="gf-playlist-item<?php echo $active; ?>">
  <span class="gf-playlist-num"><?php echo $num; ?></span>
  <span class="gf-playlist-title"><?php echo $ep['title']; ?></span>
  <?php echo $duration_html; ?>
</a>

			<?php $ep_items .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<?php echo $subtext_block; ?>

<!-- wp:columns {"style":{"spacing":{"blockGap":"3rem","margin":{"top":"2rem"}}}} -->
<div class="wp-block-columns">

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:html -->
<div class="gf-video-featured">
  <div class="gf-video-player-wrap">
    <a href="<?php echo $featured_url; ?>" class="gf-video-featured-link">
      <img src="" alt="<?php echo $featured_title; ?>" class="gf-video-featured-thumb"/>
      <div class="gf-video-play-btn gf-video-play-btn--large" aria-label="Play video">&#9654;</div>
    </a>
  </div>
  <h3 class="gf-video-featured-title"><?php echo $featured_title; ?></h3>
  <p class="gf-video-featured-desc"><?php echo $featured_desc; ?></p>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:html -->
<div class="gf-playlist-list">
<?php echo $ep_items; ?>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// video-channel-hero — YouTube-style channel page hero with banner + info bar
	// -------------------------------------------------------------------------

	public static function render_video_channel_hero( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-video-channel';

		$channel_name     = esc_html( $copy['channel_name']     ?? 'Our Video Channel' );
		$channel_handle   = esc_html( $copy['channel_handle']   ?? '@yourchannel' );
		$channel_desc     = esc_html( $copy['channel_desc']     ?? 'Weekly tutorials, product walkthroughs, and behind-the-scenes content.' );
		$subscriber_count = esc_html( $copy['subscriber_count'] ?? '12.4K subscribers' );
		$video_count      = esc_html( $copy['video_count']      ?? '48 videos' );
		$cta_subscribe    = esc_html( $copy['cta_subscribe']    ?? 'Subscribe' );
		$cta_url          = esc_attr( $copy['cta_url']          ?? '#' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>","layout":{"type":"full"}} -->
<div class="wp-block-group <?php echo $css; ?>">

<!-- wp:html -->
<div class="gf-channel-banner" style="background:linear-gradient(135deg,var(--gf-primary) 0%,var(--gf-accent) 100%);height:200px;width:100%;position:relative">
  <img src="" alt="<?php echo $channel_name; ?> banner" class="gf-channel-banner-img" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;opacity:0.3"/>
</div>

<div class="gf-channel-info" style="max-width:1280px;margin:0 auto;padding:0 2rem">
  <div class="gf-channel-avatar-wrap">
    <img src="" alt="<?php echo $channel_name; ?> avatar" class="gf-channel-avatar"/>
  </div>
  <div class="gf-channel-meta">
    <h1 class="gf-channel-name"><?php echo $channel_name; ?></h1>
    <p class="gf-channel-handle"><?php echo $channel_handle; ?> &nbsp;&middot;&nbsp; <?php echo $subscriber_count; ?> &nbsp;&middot;&nbsp; <?php echo $video_count; ?></p>
    <p class="gf-channel-desc"><?php echo $channel_desc; ?></p>
  </div>
  <div class="gf-channel-actions">
    <a href="<?php echo $cta_url; ?>" class="gf-channel-subscribe-btn gf-accent-bg"><?php echo $cta_subscribe; ?></a>
  </div>
</div>
<!-- /wp:html -->

</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// video-autoplay-hero — full-screen autoplay background video hero
	// -------------------------------------------------------------------------

	public static function render_video_autoplay_hero( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-video-autoplay-hero';

		$headline        = esc_html( $copy['headline']        ?? 'The future of your industry starts here' );
		$subtext         = esc_html( $copy['subtext']         ?? 'See how leading teams use our platform to move faster.' );
		$cta_primary     = esc_html( $copy['cta_primary']     ?? 'Get started free' );
		$cta_url         = esc_attr( $copy['cta_url']         ?? '#' );
		$video_url       = esc_attr( $copy['video_url']       ?? '' );
		$poster_url      = esc_attr( $copy['poster_url']      ?? '' );
		$overlay_opacity = esc_attr( $copy['overlay_opacity'] ?? '0.55' );

		$poster_attr = $poster_url ? " poster=\"{$poster_url}\"" : '';
		$src_tag     = $video_url ? "<source src=\"{$video_url}\" type=\"video/mp4\"/>" : '<!-- Add your video source here -->';

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="wp-block-group <?php echo $css; ?>">
<!-- wp:html -->
<div class="gf-autoplay-wrapper">
  <video class="gf-autoplay-bg-video" autoplay muted loop playsinline<?php echo $poster_attr; ?>>
    <?php echo $src_tag; ?>
  </video>
  <div class="gf-autoplay-overlay" style="opacity:<?php echo $overlay_opacity; ?>"></div>
  <div class="gf-autoplay-content">
    <h1 class="gf-autoplay-headline"><?php echo $headline; ?></h1>
    <p class="gf-autoplay-subtext"><?php echo $subtext; ?></p>
    <a href="<?php echo $cta_url; ?>" class="btn btn-lg gf-btn-primary fw-bold px-5"><?php echo $cta_primary; ?></a>
  </div>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'masonry-gallery'      => [ GrayFox_TB_Patterns_Galleries::class, 'render_masonry_gallery' ],
	'slider-gallery'       => [ GrayFox_TB_Patterns_Galleries::class, 'render_slider_gallery' ],
	'lightbox-grid'        => [ GrayFox_TB_Patterns_Galleries::class, 'render_lightbox_grid' ],
	'album-index'          => [ GrayFox_TB_Patterns_Galleries::class, 'render_album_index' ],
	'video-grid'           => [ GrayFox_TB_Patterns_Galleries::class, 'render_video_grid' ],
	'video-playlist'       => [ GrayFox_TB_Patterns_Galleries::class, 'render_video_playlist' ],
	'video-channel-hero'   => [ GrayFox_TB_Patterns_Galleries::class, 'render_video_channel_hero' ],
	'video-autoplay-hero'  => [ GrayFox_TB_Patterns_Galleries::class, 'render_video_autoplay_hero' ],
] );
