<?php
/**
 * LMS / education pattern renderers.
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
 * Class GrayFox_TB_Patterns_LMS
 */
class GrayFox_TB_Patterns_LMS {

	// -------------------------------------------------------------------------
	// course-catalog-grid — 3-column course card grid
	// -------------------------------------------------------------------------

	public static function render_course_catalog_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-course-catalog';

		$heading    = esc_html( $copy['section_heading']     ?? 'Browse Our Courses' );
		$cta_enroll = esc_html( $copy['cta_enroll']          ?? 'Enroll Now' );

		$c1_title = esc_html( $copy['course_1_title']        ?? 'Introduction to Python' );
		$c1_inst  = esc_html( $copy['course_1_instructor']   ?? 'Dr. Jane Smith' );
		$c1_dur   = esc_html( $copy['course_1_duration']     ?? '12 hours' );
		$c1_price = esc_html( $copy['course_1_price']        ?? '$49' );
		$c2_title = esc_html( $copy['course_2_title']        ?? 'Data Analysis with Pandas' );
		$c2_inst  = esc_html( $copy['course_2_instructor']   ?? 'Prof. Marcus Lee' );
		$c2_dur   = esc_html( $copy['course_2_duration']     ?? '8 hours' );
		$c2_price = esc_html( $copy['course_2_price']        ?? '$79' );
		$c3_title = esc_html( $copy['course_3_title']        ?? 'Machine Learning Fundamentals' );
		$c3_inst  = esc_html( $copy['course_3_instructor']   ?? 'Dr. Priya Nair' );
		$c3_dur   = esc_html( $copy['course_3_duration']     ?? '20 hours' );
		$c3_price = esc_html( $copy['course_3_price']        ?? '$129' );

		$card = function( string $title, string $inst, string $dur, string $price, string $badge = '' ) use ( $cta_enroll ): string {
			$badge_html = $badge ? "<span class=\"badge gf-accent-bg text-white position-absolute top-0 end-0 m-2\">{$badge}</span>" : '';
			ob_start(); ?>
<div class="card gf-course-card h-100 overflow-hidden position-relative">
  <?php echo $badge_html; ?>
  <img class="gf-course-thumb" src="" alt="<?php echo $title; ?>">
  <div class="card-body p-3 d-flex flex-column">
    <h3 class="h6 fw-bold text-primary mb-2"><?php echo $title; ?></h3>
    <p class="small text-muted mb-2 d-flex align-items-center gap-1"><i class="bi bi-person-fill"></i> <?php echo $inst; ?></p>
    <div class="d-flex align-items-center gap-3 mb-3 small text-muted">
      <span class="d-flex align-items-center gap-1"><i class="bi bi-clock"></i> <?php echo $dur; ?></span>
      <span class="text-warning"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span>
    </div>
    <div class="d-flex align-items-center justify-content-between mt-auto">
      <strong class="text-primary"><?php echo $price; ?></strong>
      <a href="#" class="btn btn-sm btn-primary fw-semibold"><?php echo $cta_enroll; ?></a>
    </div>
  </div>
</div>
			<?php return ob_get_clean();
		};

		$card1 = $card( $c1_title, $c1_inst, $c1_dur, $c1_price, 'Bestseller' );
		$card2 = $card( $c2_title, $c2_inst, $c2_dur, $c2_price );
		$card3 = $card( $c3_title, $c3_inst, $c3_dur, $c3_price, 'New' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:1100px;">
  <h2 class="gf-section-heading mb-4"><?php echo $heading; ?></h2>
  <div class="row g-4">
    <div class="col-md-4"><?php echo $card1; ?></div>
    <div class="col-md-4"><?php echo $card2; ?></div>
    <div class="col-md-4"><?php echo $card3; ?></div>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// course-hero — full dark hero with enroll card sidebar
	// -------------------------------------------------------------------------

	public static function render_course_hero( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-course-hero';

		$title       = esc_html( $copy['course_title']     ?? 'Machine Learning Fundamentals' );
		$tagline     = esc_html( $copy['tagline']          ?? 'Master the core concepts powering modern AI.' );
		$instructor  = esc_html( $copy['instructor_name']  ?? 'Dr. Priya Nair' );
		$inst_title  = esc_html( $copy['instructor_title'] ?? 'Senior AI Researcher' );
		$rating      = esc_html( $copy['rating']           ?? '4.9' );
		$reviews     = esc_html( $copy['reviews']          ?? '2,341 ratings' );
		$enrolled    = esc_html( $copy['enrolled']         ?? '18,500 students' );
		$duration    = esc_html( $copy['duration']         ?? '20 hours' );
		$price       = esc_html( $copy['price']            ?? '$129' );
		$cta         = esc_html( $copy['cta_enroll']       ?? 'Enroll Now' );
		$cta_preview = esc_html( $copy['cta_preview']      ?? 'Preview Course' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> bg-primary py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> bg-primary py-5">
<!-- wp:html -->
  <div class="container" style="max-width:1100px;">
    <div class="row g-5 align-items-start">

      <div class="col-lg-7">
        <p class="gf-eyebrow gf-accent-color mb-2">Online Course</p>
        <h1 class="text-white mb-3"><?php echo $title; ?></h1>
        <p class="text-white opacity-75 mb-4 lh-lg"><?php echo $tagline; ?></p>

        <div class="d-flex align-items-center gap-3 flex-wrap mb-4 small">
          <span class="fw-bold gf-accent-color d-flex align-items-center gap-1"><?php echo $rating; ?> <i class="bi bi-star-fill"></i></span>
          <span class="text-white opacity-50">(<?php echo $reviews; ?>)</span>
          <span class="text-white opacity-50 d-flex align-items-center gap-1"><i class="bi bi-people-fill"></i> <?php echo $enrolled; ?></span>
          <span class="text-white opacity-50 d-flex align-items-center gap-1"><i class="bi bi-clock"></i> <?php echo $duration; ?></span>
        </div>

        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle gf-accent-bg d-flex align-items-center justify-content-center fs-5 flex-shrink-0 text-white" style="width:40px;height:40px;"><i class="bi bi-person-fill"></i></div>
          <div>
            <p class="text-white fw-semibold small mb-0"><?php echo $instructor; ?></p>
            <p class="text-white opacity-50 small mb-0"><?php echo $inst_title; ?></p>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-lg">
          <img class="gf-course-thumb" src="" alt="<?php echo $title; ?>">
          <div class="card-body p-4">
            <p class="fs-3 fw-bold text-primary mb-3"><?php echo $price; ?></p>
            <a href="#" class="btn btn-primary w-100 fw-bold mb-2"><?php echo $cta; ?></a>
            <a href="#" class="btn btn-outline-primary w-100 fw-bold"><?php echo $cta_preview; ?></a>
            <p class="text-center text-muted small mt-3 mb-0">30-day money-back guarantee</p>
          </div>
        </div>
      </div>

    </div>
  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// lesson-player — video player + lesson sidebar
	// -------------------------------------------------------------------------

	public static function render_lesson_player( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-lesson-player';

		$course_title = esc_html( $copy['course_title'] ?? 'Machine Learning Fundamentals' );
		$lesson_title = esc_html( $copy['lesson_title'] ?? 'Lesson 3: Linear Regression' );

		$lessons = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$defaults = [ 1 => '1. Introduction', 2 => '2. Setting Up Python', 3 => '3. Linear Regression', 4 => '4. Decision Trees', 5 => '5. Neural Networks', 6 => '6. Model Evaluation' ];
			$lessons[] = esc_html( $copy[ "lesson_{$i}" ] ?? $defaults[ $i ] );
		}

		$sidebar_items = '';
		foreach ( $lessons as $i => $lesson ) {
			$is_active  = ( $i === 2 );
			$is_done    = ( $i < 2 );
			$icon       = $is_done ? 'bi-check-circle-fill' : ( $is_active ? 'bi-play-fill' : 'bi-circle' );
			$active_cls = $is_active ? 'gf-lesson-item--active' : '';
			$done_cls   = $is_done   ? 'gf-lesson-item--done'   : '';
			$sidebar_items .= "<div class=\"gf-lesson-item {$active_cls} {$done_cls}\"><span class=\"gf-lesson-icon\"><i class=\"bi {$icon}\"></i></span><span class=\"flex-grow-1\">{$lesson}</span><span class=\"gf-lesson-duration\">8:20</span></div>\n";
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> gf-lesson-layout","align":"full"} -->
<div class="wp-block-group alignfull <?php echo $css; ?> gf-lesson-layout">
<!-- wp:html -->
  <div class="gf-lesson-main">
    <div class="gf-lesson-video" style="aspect-ratio:16/9;">
      <button aria-label="Play lesson" class="gf-lesson-play-btn"><i class="bi bi-play-fill"></i></button>
    </div>
    <div class="gf-lesson-progress">
      <div class="gf-lesson-progress-fill" style="width:35%;"></div>
    </div>
    <div class="gf-lesson-controls">
      <button aria-label="Previous lesson" class="gf-lesson-ctrl-btn"><i class="bi bi-skip-backward-fill"></i></button>
      <button aria-label="Play/Pause" class="gf-lesson-ctrl-btn gf-lesson-ctrl-btn--lg"><i class="bi bi-play-fill"></i></button>
      <button aria-label="Next lesson" class="gf-lesson-ctrl-btn"><i class="bi bi-skip-forward-fill"></i></button>
      <span class="gf-lesson-time">2:52 / 8:20</span>
      <div style="flex:1;"></div>
      <button aria-label="Settings" class="gf-lesson-ctrl-btn"><i class="bi bi-gear-fill"></i></button>
    </div>
    <div class="gf-lesson-info">
      <p class="gf-lesson-course-label"><?php echo $course_title; ?></p>
      <h2 class="gf-lesson-title"><?php echo $lesson_title; ?></h2>
    </div>
  </div>

  <div class="gf-lesson-sidebar">
    <div class="gf-lesson-sidebar-header">Course Content</div>
    <div class="gf-lesson-list">
      <?php echo $sidebar_items; ?>
    </div>
  </div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// quiz-results — score circle, stats, and action buttons
	// -------------------------------------------------------------------------

	public static function render_quiz_results( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-quiz-results';

		$heading     = esc_html( $copy['heading']         ?? 'Quiz Complete!' );
		$score_label = esc_html( $copy['score_label']     ?? 'Your Score' );
		$score       = esc_html( $copy['score']           ?? '8 / 10' );
		$pct         = max( 0, min( 100, intval( $copy['percentage'] ?? 80 ) ) );
		$passed      = strtolower( $copy['passed'] ?? 'true' ) !== 'false';
		$pass_msg    = esc_html( $copy['pass_message']    ?? 'Great work — you passed!' );
		$fail_msg    = esc_html( $copy['fail_message']    ?? 'Keep studying and try again.' );
		$cta_next    = esc_html( $copy['cta_next_lesson']  ?? 'Continue to Next Lesson' );
		$cta_retry   = esc_html( $copy['cta_retry']        ?? 'Retake Quiz' );
		$cta_review  = esc_html( $copy['cta_review']       ?? 'Review Answers' );
		$time_taken  = esc_html( $copy['time_taken']       ?? '—' );

		$badge_cls  = $passed ? 'bg-success' : 'bg-danger';
		$badge_icon = $passed ? '<i class="bi bi-trophy-fill"></i>' : '<i class="bi bi-emoji-frown"></i>';
		$status_msg = $passed ? $pass_msg : $fail_msg;
		$total      = max( 1, intval( $copy['total_questions'] ?? 10 ) );
		$correct    = intval( $pct * $total / 100 );
		$incorrect  = $total - $correct;
		$anti_pct   = 100 - $pct;

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5 text-center","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 text-center">
<!-- wp:html -->
<div class="gf-quiz-results-inner">

  <div class="mb-4">
    <span class="badge <?php echo $badge_cls; ?> rounded-pill px-3 py-2"><?php echo $badge_icon; ?> <?php echo $status_msg; ?></span>
  </div>

  <h1 class="text-primary mb-1"><?php echo $heading; ?></h1>
  <p class="text-muted mb-4"><?php echo $score_label; ?></p>

  <div class="gf-score-circle mb-4">
    <svg class="gf-score-ring" viewBox="0 0 36 36">
      <circle class="gf-score-ring-track" cx="18" cy="18" r="15.9"/>
      <circle class="gf-score-ring-fill" cx="18" cy="18" r="15.9"
        stroke-dasharray="<?php echo $pct; ?> <?php echo $anti_pct; ?>"/>
    </svg>
    <div class="position-absolute top-0 start-0 end-0 bottom-0 d-flex flex-column align-items-center justify-content-center">
      <span class="fs-3 fw-bold text-primary"><?php echo $pct; ?>%</span>
      <span class="small text-muted"><?php echo $score; ?></span>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-4">
      <div class="p-3 gf-section-tint rounded">
        <p class="fs-5 fw-bold text-success mb-0"><i class="bi bi-check-lg"></i> <?php echo $correct; ?></p>
        <p class="small text-muted mb-0">Correct</p>
      </div>
    </div>
    <div class="col-4">
      <div class="p-3 gf-section-tint rounded">
        <p class="fs-5 fw-bold text-danger mb-0"><i class="bi bi-x-lg"></i> <?php echo $incorrect; ?></p>
        <p class="small text-muted mb-0">Incorrect</p>
      </div>
    </div>
    <div class="col-4">
      <div class="p-3 gf-section-tint rounded">
        <p class="fs-5 fw-bold text-primary mb-0"><?php echo $time_taken; ?></p>
        <p class="small text-muted mb-0">Time taken</p>
      </div>
    </div>
  </div>

  <div class="d-flex flex-column gap-2">
    <a href="#" class="btn btn-primary w-100 fw-bold py-3"><?php echo $cta_next; ?></a>
    <div class="d-flex gap-2">
      <a href="#" class="btn btn-outline-primary flex-fill fw-semibold"><?php echo $cta_retry; ?></a>
      <a href="#" class="btn btn-outline-secondary flex-fill fw-semibold"><?php echo $cta_review; ?></a>
    </div>
  </div>

</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'course-catalog-grid' => [ GrayFox_TB_Patterns_LMS::class, 'render_course_catalog_grid' ],
	'course-hero'         => [ GrayFox_TB_Patterns_LMS::class, 'render_course_hero' ],
	'lesson-player'       => [ GrayFox_TB_Patterns_LMS::class, 'render_lesson_player' ],
	'quiz-results'        => [ GrayFox_TB_Patterns_LMS::class, 'render_quiz_results' ],
] );
