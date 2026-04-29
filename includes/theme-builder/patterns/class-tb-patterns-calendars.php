<?php
/**
 * Calendars & scheduling pattern renderers.
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
 * Class GrayFox_TB_Patterns_Calendars
 */
class GrayFox_TB_Patterns_Calendars {

	// -------------------------------------------------------------------------
	// booking-calendar — date picker + time slots + booking form
	// -------------------------------------------------------------------------

	public static function render_booking_calendar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-booking-calendar';

		$heading      = esc_html( $copy['section_heading'] ?? 'Book an Appointment' );
		$subtext      = esc_html( $copy['subtext']         ?? 'Choose a date and time that works for you.' );
		$cta_confirm  = esc_html( $copy['cta_confirm']     ?? 'Confirm Booking' );
		$month_label  = esc_html( $copy['month_label']     ?? 'April 2026' );
		$form_heading = esc_html( $copy['form_heading']    ?? 'Your Details' );

		// Build day headers
		$days        = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
		$day_headers = '';
		foreach ( $days as $d ) {
			$day_headers .= "<div style=\"text-align:center;font-size:.75rem;font-weight:600;color:var(--wp--preset--color--muted,#9ca3af);padding:.25rem 0;\">{$d}</div>";
		}

		// Build calendar cells (April 2026: starts Wed = offset 2)
		$nums     = array_merge( [ '', '' ], range( 1, 5 ), range( 6, 12 ), range( 13, 19 ), range( 20, 26 ), range( 27, 30 ), [ '', '', '' ] );
		$unavail  = [ 6, 7, 13, 20, 21 ];
		$selected = 14;
		$cells    = '';
		foreach ( $nums as $n ) {
			if ( $n === '' ) {
				$cells .= '<div></div>';
			} elseif ( $n === $selected ) {
				$cells .= "<button aria-pressed=\"true\" aria-label=\"April {$n}\" style=\"width:100%;aspect-ratio:1/1;border-radius:50%;background:var(--gf-accent);color:#fff;border:none;font-weight:700;cursor:pointer;font-size:.875rem;\">{$n}</button>";
			} elseif ( in_array( $n, $unavail, true ) ) {
				$cells .= "<button disabled aria-label=\"April {$n} — unavailable\" style=\"width:100%;aspect-ratio:1/1;border-radius:50%;background:transparent;color:var(--wp--preset--color--muted,#d1d5db);border:none;cursor:not-allowed;font-size:.875rem;\">{$n}</button>";
			} else {
				$cells .= "<button aria-label=\"April {$n}\" style=\"width:100%;aspect-ratio:1/1;border-radius:50%;background:transparent;border:none;cursor:pointer;font-size:.875rem;font-weight:500;color:var(--wp--preset--color--text,#1e1e1e);\">{$n}</button>";
			}
		}

		// Build time slot buttons
		$slots       = [ '9:00 AM', '9:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '2:00 PM', '2:30 PM', '3:00 PM' ];
		$slot_html   = '';
		foreach ( $slots as $idx => $s ) {
			if ( $idx === 2 ) {
				$style = 'padding:.4rem .75rem;border:2px solid var(--gf-accent);background:var(--gf-accent);color:#fff;border-radius:4px;font-size:.8rem;cursor:pointer;';
			} else {
				$style = 'padding:.4rem .75rem;border:2px solid var(--wp--preset--color--muted,#d1d5db);background:transparent;border-radius:4px;font-size:.8rem;cursor:pointer;';
			}
			$slot_html .= "<button style=\"{$style}\">{$s}</button>\n";
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div style="max-width:1000px;margin:0 auto;">

    <div style="text-align:center;margin-bottom:2.5rem;">
      <h2 style="color:var(--gf-primary);margin:0 0 .5rem;"><?php echo $heading; ?></h2>
      <p style="color:var(--wp--preset--color--muted,#6b7280);margin:0;"><?php echo $subtext; ?></p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2.5rem;align-items:start;">

      <div class="gf-calendar-panel" style="border:1px solid var(--wp--preset--color--muted,#e5e7eb);border-radius:10px;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.875rem 1rem;background:var(--gf-primary);color:#fff;">
          <button aria-label="Previous month" style="background:transparent;border:none;color:#fff;font-size:1.1rem;cursor:pointer;">&#8592;</button>
          <strong><?php echo $month_label; ?></strong>
          <button aria-label="Next month" style="background:transparent;border:none;color:#fff;font-size:1.1rem;cursor:pointer;">&#8594;</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);padding:.5rem .75rem 0;">
          <?php echo $day_headers; ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;padding:.25rem .75rem .75rem;">
          <?php echo $cells; ?>
        </div>
      </div>

      <div>
        <h3 style="color:var(--gf-primary);margin:0 0 .75rem;font-size:1rem;">Available times — Tue, Apr 14</h3>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem;">
          <?php echo $slot_html; ?>
        </div>

        <h3 style="color:var(--gf-primary);margin:0 0 1rem;font-size:1rem;"><?php echo $form_heading; ?></h3>
        <form action="#" method="post" style="display:flex;flex-direction:column;gap:.75rem;">
          <input type="text" placeholder="Your name" aria-label="Name" style="padding:.625rem .875rem;border:1px solid var(--wp--preset--color--muted,#d1d5db);border-radius:6px;font-size:.9rem;font-family:inherit;" />
          <input type="email" placeholder="Email address" aria-label="Email" style="padding:.625rem .875rem;border:1px solid var(--wp--preset--color--muted,#d1d5db);border-radius:6px;font-size:.9rem;font-family:inherit;" />
          <input type="tel" placeholder="Phone (optional)" aria-label="Phone" style="padding:.625rem .875rem;border:1px solid var(--wp--preset--color--muted,#d1d5db);border-radius:6px;font-size:.9rem;font-family:inherit;" />
          <button type="submit" class="btn btn-primary w-100 fw-bold" style="font-family:inherit;"><?php echo $cta_confirm; ?></button>
        </form>
      </div>

    </div>

  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// event-calendar-grid — full-month grid calendar with event chips
	// -------------------------------------------------------------------------

	public static function render_event_calendar_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-event-calendar';

		$heading     = esc_html( $copy['section_heading'] ?? 'Upcoming Events' );
		$month_label = esc_html( $copy['month_label']     ?? 'April 2026' );

		$days        = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
		$day_headers = '';
		foreach ( $days as $d ) {
			$day_headers .= "<div class=\"gf-cal-day-header\">{$d}</div>";
		}

		// April 2026: offset 2, days 1–30
		$events = [
			3  => [ 'Workshop',   'accent'   ],
			8  => [ 'Webinar',    'secondary' ],
			14 => [ 'Conference', 'primary'  ],
			21 => [ 'Launch',     'primary'  ],
			25 => [ 'Meetup',     'secondary' ],
		];
		$today  = 13;
		$nums   = array_merge( [ '', '' ], range( 1, 30 ), [ '', '', '' ] );
		$cells  = '';
		foreach ( $nums as $n ) {
			if ( $n === '' ) {
				$cells .= '<div class="gf-cal-cell-empty"></div>';
			} else {
				$num_class = ( $n === $today ) ? 'gf-cal-day-num--today' : 'gf-cal-day-num';
				$chips     = '';
				if ( isset( $events[ $n ] ) ) {
					[ $ev, $variant ] = $events[ $n ];
					$chips = "<div class=\"gf-cal-chip gf-cal-chip--{$variant}\">{$ev}</div>";
				}
				$cells .= "<div class=\"gf-cal-cell\"><span class=\"{$num_class}\">{$n}</span>{$chips}</div>";
			}
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="gf-cal-container">

  <h2 class="gf-cal-heading"><?php echo $heading; ?></h2>

  <div class="gf-cal-nav">
    <button class="gf-cal-nav-btn" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
    <h3 class="gf-cal-month"><?php echo $month_label; ?></h3>
    <button class="gf-cal-nav-btn" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
    <button class="btn btn-primary btn-sm">Today</button>
  </div>

  <div class="gf-cal-wrap">
    <div class="gf-cal-header">
      <?php echo $day_headers; ?>
    </div>
    <div class="gf-cal-body">
      <?php echo $cells; ?>
    </div>
  </div>

  <div class="gf-cal-legend">
    <span class="gf-cal-legend-item"><span class="gf-cal-legend-dot gf-cal-legend-dot--primary"></span>Conference</span>
    <span class="gf-cal-legend-item"><span class="gf-cal-legend-dot gf-cal-legend-dot--secondary"></span>Webinar</span>
    <span class="gf-cal-legend-item"><span class="gf-cal-legend-dot gf-cal-legend-dot--accent"></span>Workshop</span>
  </div>

</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'booking-calendar'     => [ GrayFox_TB_Patterns_Calendars::class, 'render_booking_calendar' ],
	'event-calendar-grid'  => [ GrayFox_TB_Patterns_Calendars::class, 'render_event_calendar_grid' ],
] );
