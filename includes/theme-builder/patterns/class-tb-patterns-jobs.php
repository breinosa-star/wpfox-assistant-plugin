<?php
/**
 * Jobs / recruitment pattern renderers.
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
 * Class GrayFox_TB_Patterns_Jobs
 */
class GrayFox_TB_Patterns_Jobs {

	// -------------------------------------------------------------------------
	// job-board — filterable job listing table with 4 rows
	// -------------------------------------------------------------------------

	public static function render_job_board( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-job-board';

		$heading   = esc_html( $copy['section_heading'] ?? 'Open Positions' );
		$subtext   = esc_html( $copy['subtext']         ?? 'Join our team and help build something great.' );
		$cta_apply = esc_html( $copy['cta_apply']       ?? 'Apply' );

		$job_defaults = [
			[ 'title' => 'Senior Product Designer',    'type' => 'Full-time', 'location' => 'Remote',           'department' => 'Design'      ],
			[ 'title' => 'Backend Engineer (Python)',   'type' => 'Full-time', 'location' => 'New York, NY',     'department' => 'Engineering' ],
			[ 'title' => 'Growth Marketing Manager',    'type' => 'Full-time', 'location' => 'Hybrid — Austin',  'department' => 'Marketing'   ],
			[ 'title' => 'Customer Success Specialist', 'type' => 'Part-time', 'location' => 'Remote',           'department' => 'Support'     ],
		];

		$type_badge = function( string $t ): string {
			$cls_map = [
				'Full-time' => 'gf-job-type-badge--fulltime',
				'Part-time' => 'gf-job-type-badge--parttime',
				'Contract'  => 'gf-job-type-badge--contract',
			];
			$cls = $cls_map[ $t ] ?? 'gf-job-type-badge--fulltime';
			return "<span class=\"gf-job-type-badge {$cls}\">{$t}</span>";
		};

		$job_row = function( string $title, string $jtype, string $loc, string $dept ) use ( $cta_apply, $type_badge ): string {
			ob_start(); ?>
<div class="gf-job-row">
  <div class="flex-grow-1 min-w-0">
    <p class="gf-job-row__title"><?php echo $title; ?></p>
    <p class="gf-job-row__meta"><i class="bi bi-building"></i> <?php echo $dept; ?> &nbsp;&middot;&nbsp; <i class="bi bi-geo-alt"></i> <?php echo $loc; ?></p>
  </div>
  <?php echo $type_badge($jtype); ?>
  <a href="#" class="btn btn-sm btn-primary fw-semibold"><?php echo $cta_apply; ?></a>
</div>
			<?php return ob_get_clean();
		};

		$rows = '';
		for ( $i = 1; $i <= 8; $i++ ) {
			$d     = $job_defaults[ $i - 1 ] ?? null;
			$title = esc_html( $copy[ "job_{$i}_title" ] ?? ( $d['title'] ?? '' ) );
			if ( ! $title ) {
				break;
			}
			$type  = esc_html( $copy[ "job_{$i}_type"       ] ?? ( $d['type']       ?? 'Full-time' ) );
			$loc   = esc_html( $copy[ "job_{$i}_location"   ] ?? ( $d['location']   ?? '' ) );
			$dept  = esc_html( $copy[ "job_{$i}_department" ] ?? ( $d['department'] ?? '' ) );
			$rows .= $job_row( $title, $type, $loc, $dept );
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:900px;">
  <div class="text-center mb-5">
    <h2 class="gf-section-heading"><?php echo $heading; ?></h2>
    <p class="text-muted mb-0"><?php echo $subtext; ?></p>
  </div>
  <div class="gf-job-filter-bar">
    <select aria-label="Filter by department"><option>All Departments</option><option>Engineering</option><option>Design</option><option>Marketing</option><option>Support</option></select>
    <select aria-label="Filter by type"><option>All Types</option><option>Full-time</option><option>Part-time</option><option>Contract</option></select>
    <select aria-label="Filter by location"><option>All Locations</option><option>Remote</option><option>New York</option><option>Austin</option></select>
  </div>
  <div class="border rounded overflow-hidden">
    <?php echo $rows; ?>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// job-detail-hero — single job header with apply/save buttons
	// -------------------------------------------------------------------------

	public static function render_job_detail_hero( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-job-detail-hero';

		$title       = esc_html( $copy['job_title']    ?? 'Senior Product Designer' );
		$company     = esc_html( $copy['company_name'] ?? 'Acme Corp' );
		$location    = esc_html( $copy['location']     ?? 'Remote' );
		$job_type    = esc_html( $copy['job_type']     ?? 'Full-time' );
		$department  = esc_html( $copy['department']   ?? 'Design' );
		$salary      = esc_html( $copy['salary_range'] ?? '$110,000 – $140,000' );
		$posted      = esc_html( $copy['posted_date']  ?? 'Posted 3 days ago' );
		$cta_apply   = esc_html( $copy['cta_apply']    ?? 'Apply Now' );
		$cta_save    = esc_html( $copy['cta_save']     ?? 'Save Job' );
		$description = esc_html( $copy['description']  ?? "We're looking for a talented Product Designer to join our growing team. You'll work closely with engineering and product to shape intuitive user experiences." );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div class="container" style="max-width:900px;">
    <div class="d-flex align-items-start gap-4 mb-4 flex-wrap">
      <div class="rounded bg-light border d-flex align-items-center justify-content-center fs-3 flex-shrink-0" style="width:72px;height:72px;"><i class="bi bi-building text-muted"></i></div>
      <div class="flex-grow-1">
        <h1 class="gf-section-heading text-primary mb-1"><?php echo $title; ?></h1>
        <p class="text-muted small mb-3"><?php echo $company; ?> &middot; <?php echo $department; ?></p>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge bg-info rounded-pill"><i class="bi bi-geo-alt"></i> <?php echo $location; ?></span>
          <span class="badge bg-success rounded-pill"><i class="bi bi-clock"></i> <?php echo $job_type; ?></span>
          <span class="badge bg-warning text-dark rounded-pill"><i class="bi bi-currency-dollar"></i> <?php echo $salary; ?></span>
        </div>
      </div>
      <div class="d-flex gap-2 align-items-center flex-shrink-0">
        <a href="#" class="btn btn-primary fw-bold"><?php echo $cta_apply; ?></a>
        <button class="btn btn-outline-secondary fw-semibold"><?php echo $cta_save; ?></button>
      </div>
    </div>

    <p class="text-muted small mb-4"><?php echo $posted; ?></p>

    <div class="card p-4">
      <h2 class="h5 text-primary mb-3">About This Role</h2>
      <p class="lh-lg mb-0"><?php echo $description; ?></p>
    </div>
  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// employer-profile — company header banner + about + jobs list + facts sidebar
	// -------------------------------------------------------------------------

	public static function render_employer_profile( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-employer-profile';

		$company   = esc_html( $copy['company_name']     ?? 'Acme Corp' );
		$tagline   = esc_html( $copy['tagline']          ?? 'Building the future of work.' );
		$about     = esc_html( $copy['about']            ?? 'Acme Corp is a fast-growing technology company on a mission to make work more human. We believe the best teams are built with trust, autonomy, and a shared purpose.' );
		$size      = esc_html( $copy['company_size']     ?? '201–500 employees' );
		$industry  = esc_html( $copy['industry']         ?? 'Technology' );
		$website   = esc_attr( $copy['website']          ?? 'acmecorp.com' );
		$open_jobs = esc_html( $copy['open_jobs_count']  ?? '8' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:900px;">
  <div class="d-flex align-items-center gap-4 p-4 bg-primary text-white rounded mb-4 flex-wrap">
    <div class="rounded d-flex align-items-center justify-content-center fs-2 flex-shrink-0" style="width:80px;height:80px;background:rgba(255,255,255,.15);"><i class="bi bi-building text-white"></i></div>
    <div class="flex-grow-1">
      <h1 class="text-white mb-1 h2"><?php echo $company; ?></h1>
      <p class="opacity-75 mb-0"><?php echo $tagline; ?></p>
    </div>
    <a href="#<?php echo $css; ?>-jobs" class="btn gf-accent-bg text-white border-0 fw-bold">View <?php echo $open_jobs; ?> Open Jobs</a>
  </div>

  <div class="row g-4 align-items-start">
    <div class="col-md-8">
      <h2 class="text-primary h4 mb-3">About <?php echo $company; ?></h2>
      <p class="lh-lg mb-4"><?php echo $about; ?></p>

      <h2 id="<?php echo $css; ?>-jobs" class="text-primary h4 mb-3">Open Positions</h2>
      <div class="border rounded overflow-hidden">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
          <div><p class="fw-semibold text-primary mb-0 small">Senior Product Designer</p><p class="small text-muted mb-0">Design &middot; Remote</p></div>
          <a href="#" class="small gf-accent-color fw-semibold text-decoration-none">Apply &rarr;</a>
        </div>
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
          <div><p class="fw-semibold text-primary mb-0 small">Backend Engineer</p><p class="small text-muted mb-0">Engineering &middot; New York</p></div>
          <a href="#" class="small gf-accent-color fw-semibold text-decoration-none">Apply &rarr;</a>
        </div>
        <div class="d-flex align-items-center justify-content-between p-3">
          <div><p class="fw-semibold text-primary mb-0 small">Growth Marketing Manager</p><p class="small text-muted mb-0">Marketing &middot; Hybrid</p></div>
          <a href="#" class="small gf-accent-color fw-semibold text-decoration-none">Apply &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3">
        <h3 class="gf-eyebrow mb-3">Company Info</h3>
        <div class="d-flex flex-column gap-3">
          <div class="small"><span class="text-muted d-block" style="font-size:.75rem;">Industry</span><strong><?php echo $industry; ?></strong></div>
          <div class="small"><span class="text-muted d-block" style="font-size:.75rem;">Company size</span><strong><?php echo $size; ?></strong></div>
          <div class="small"><span class="text-muted d-block" style="font-size:.75rem;">Website</span><a href="https://<?php echo $website; ?>" class="text-primary fw-semibold text-decoration-none"><?php echo $website; ?></a></div>
          <div class="small"><span class="text-muted d-block" style="font-size:.75rem;">Open jobs</span><strong><?php echo $open_jobs; ?> positions</strong></div>
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
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'job-board'        => [ GrayFox_TB_Patterns_Jobs::class, 'render_job_board' ],
	'job-detail-hero'  => [ GrayFox_TB_Patterns_Jobs::class, 'render_job_detail_hero' ],
	'employer-profile' => [ GrayFox_TB_Patterns_Jobs::class, 'render_employer_profile' ],
] );
