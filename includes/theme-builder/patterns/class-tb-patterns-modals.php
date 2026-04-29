<?php
/**
 * Modal / popup / widget pattern renderers.
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
 * Class GrayFox_TB_Patterns_Modals
 */
class GrayFox_TB_Patterns_Modals {

	// -------------------------------------------------------------------------
	// cookie-consent-bar — fixed bottom consent bar
	// -------------------------------------------------------------------------

	public static function render_cookie_consent_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-cookie-bar';

		$message     = esc_html( $copy['message']          ?? 'We use cookies to improve your experience and analyze site traffic.' );
		$cta_accept  = esc_html( $copy['cta_accept']       ?? 'Accept All' );
		$cta_decline = esc_html( $copy['cta_decline']      ?? 'Decline' );
		$policy_link = esc_html( $copy['policy_link_text'] ?? 'Privacy Policy' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="<?php echo $css; ?> position-fixed bottom-0 start-0 end-0 bg-primary text-white p-3 d-flex align-items-center gap-3 flex-wrap shadow" style="z-index:9000;" role="region" aria-label="Cookie consent">
  <p class="flex-grow-1 small mb-0">
    <?php echo $message; ?>
    <a href="/privacy-policy" class="text-white opacity-75 text-decoration-underline ms-1"><?php echo $policy_link; ?></a>
  </p>
  <div class="d-flex gap-2 flex-shrink-0">
    <button class="gf-cookie-accept btn btn-sm gf-accent-bg text-white border-0 fw-semibold"><?php echo $cta_accept; ?></button>
    <button class="gf-cookie-decline btn btn-sm btn-outline-light"><?php echo $cta_decline; ?></button>
  </div>
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// cookie-consent-modal — cookie preference modal with toggles
	// -------------------------------------------------------------------------

	public static function render_cookie_consent_modal( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-cookie-modal';

		$heading    = esc_html( $copy['heading']         ?? 'Cookie Preferences' );
		$intro      = esc_html( $copy['intro']           ?? 'We use cookies to personalize content, analyze traffic, and improve your experience. Choose which categories you allow.' );
		$cta_accept = esc_html( $copy['cta_accept_all']  ?? 'Accept All' );
		$cta_save   = esc_html( $copy['cta_save']        ?? 'Save Preferences' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="<?php echo $css; ?> position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center p-3" style="z-index:9500;" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title">
  <div class="<?php echo $css; ?>__backdrop position-absolute top-0 start-0 end-0 bottom-0 bg-dark bg-opacity-50" style="backdrop-filter:blur(2px);"></div>

  <div class="<?php echo $css; ?>__panel card shadow-lg position-relative p-4" style="max-width:520px;width:100%;">

    <h2 id="cookie-modal-title" class="h5 text-primary mb-2"><?php echo $heading; ?></h2>
    <p class="small text-muted lh-lg mb-4"><?php echo $intro; ?></p>

    <div class="d-flex flex-column gap-3 mb-4">

      <div class="d-flex justify-content-between align-items-center p-3 border rounded">
        <div>
          <p class="fw-semibold small mb-0">Necessary</p>
          <p class="small text-muted mb-0">Required for the site to function</p>
        </div>
        <span class="badge bg-success rounded-pill">Always on</span>
      </div>

      <label class="d-flex justify-content-between align-items-center p-3 border rounded" style="cursor:pointer;">
        <div>
          <p class="fw-semibold small mb-0">Analytics</p>
          <p class="small text-muted mb-0">Help us understand how visitors use the site</p>
        </div>
        <input type="checkbox" class="gf-cookie-toggle form-check-input" />
      </label>

      <label class="d-flex justify-content-between align-items-center p-3 border rounded" style="cursor:pointer;">
        <div>
          <p class="fw-semibold small mb-0">Marketing</p>
          <p class="small text-muted mb-0">Used to show relevant ads and track campaigns</p>
        </div>
        <input type="checkbox" class="gf-cookie-toggle form-check-input" />
      </label>

    </div>

    <div class="d-flex gap-2 justify-content-end">
      <button class="gf-cookie-save btn btn-outline-primary fw-semibold"><?php echo $cta_save; ?></button>
      <button class="gf-cookie-accept-all btn btn-primary fw-semibold"><?php echo $cta_accept; ?></button>
    </div>

  </div>
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// opt-in-popup — email opt-in modal with gradient accent strip
	// -------------------------------------------------------------------------

	public static function render_opt_in_popup( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-opt-in-popup';

		$heading     = esc_html( $copy['heading']       ?? 'Get exclusive tips in your inbox' );
		$subtext     = esc_html( $copy['subtext']       ?? 'Join 10,000+ subscribers. No spam, unsubscribe anytime.' );
		$placeholder = esc_attr( $copy['placeholder']   ?? 'Your email address' );
		$cta         = esc_html( $copy['cta']           ?? 'Subscribe Now' );
		$dismiss     = esc_html( $copy['dismiss_label'] ?? 'No thanks' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="<?php echo $css; ?> position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center p-3" style="z-index:9500;" role="dialog" aria-modal="true" aria-labelledby="optin-title">
  <div class="<?php echo $css; ?>__backdrop position-absolute top-0 start-0 end-0 bottom-0 bg-dark bg-opacity-50" style="backdrop-filter:blur(3px);"></div>

  <div class="<?php echo $css; ?>__panel card shadow-lg position-relative overflow-hidden" style="max-width:460px;width:100%;">

    <div style="height:6px;background:linear-gradient(90deg,var(--gf-primary),var(--gf-accent));"></div>

    <button class="<?php echo $css; ?>__close position-absolute top-0 end-0 m-3 btn-close" aria-label="Close"></button>

    <div class="card-body p-4">
      <h2 id="optin-title" class="text-primary mb-2"><?php echo $heading; ?></h2>
      <p class="text-muted small lh-lg mb-4"><?php echo $subtext; ?></p>

      <form action="#" method="post" class="d-flex flex-column gap-3">
        <input type="email" name="email" placeholder="<?php echo $placeholder; ?>" required aria-label="Email address" class="form-control" />
        <button type="submit" class="btn btn-primary w-100 fw-bold py-3"><?php echo $cta; ?></button>
      </form>

      <p class="text-center mt-3 mb-0">
        <button class="<?php echo $css; ?>__dismiss btn btn-link text-muted small p-0 text-decoration-underline"><?php echo $dismiss; ?></button>
      </p>
    </div>

  </div>
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// announcement-modal — image + headline modal with dismiss
	// -------------------------------------------------------------------------

	public static function render_announcement_modal( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-announcement-modal';

		$heading = esc_html( $copy['heading']       ?? 'Big News: We Just Launched!' );
		$subtext = esc_html( $copy['subtext']       ?? "We've redesigned everything from the ground up. Explore the new experience today." );
		$cta     = esc_html( $copy['cta']           ?? "See What's New" );
		$dismiss = esc_html( $copy['dismiss_label'] ?? 'Maybe later' );
		$img_alt = esc_attr( $copy['img_alt']       ?? 'Announcement image' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="<?php echo $css; ?> position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center p-3" style="z-index:9500;" role="dialog" aria-modal="true" aria-labelledby="announce-title">
  <div class="<?php echo $css; ?>__backdrop position-absolute top-0 start-0 end-0 bottom-0 bg-dark bg-opacity-50" style="backdrop-filter:blur(4px);"></div>

  <div class="<?php echo $css; ?>__panel card shadow-lg position-relative overflow-hidden" style="max-width:560px;width:100%;">

    <div class="<?php echo $css; ?>__image d-flex align-items-center justify-content-center" role="img" aria-label="<?php echo $img_alt; ?>"
      style="height:200px;background:linear-gradient(135deg,var(--gf-primary) 0%,var(--gf-accent) 100%);">
      <span class="fs-1" aria-hidden="true">&#127881;</span>
    </div>

    <button class="<?php echo $css; ?>__close position-absolute btn-close btn-close-white" style="top:.75rem;right:.75rem;" aria-label="Close announcement"></button>

    <div class="card-body p-4">
      <h2 id="announce-title" class="text-primary mb-2"><?php echo $heading; ?></h2>
      <p class="text-muted lh-lg mb-4"><?php echo $subtext; ?></p>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <a href="#" class="btn btn-primary fw-bold"><?php echo $cta; ?></a>
        <button class="<?php echo $css; ?>__dismiss btn btn-link text-muted small p-0 text-decoration-underline"><?php echo $dismiss; ?></button>
      </div>
    </div>

  </div>
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// age-gate — full-screen age verification gate
	// -------------------------------------------------------------------------

	public static function render_age_gate( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-age-gate';

		$heading      = esc_html( $copy['heading']      ?? 'Are you of legal age?' );
		$subtext      = esc_html( $copy['subtext']      ?? 'You must be 21 or older to enter this site.' );
		$cta_yes      = esc_html( $copy['cta_yes']      ?? "Yes, I'm 21+" );
		$cta_no       = esc_html( $copy['cta_no']       ?? 'No, take me back' );
		$legal_notice = esc_html( $copy['legal_notice'] ?? 'By entering you agree to our Terms of Service and confirm you are of legal drinking age in your country.' );
		$site_name    = esc_html( $copy['site_name']    ?? ( $manifest['theme']['name'] ?? 'Our Site' ) );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="<?php echo $css; ?> position-fixed top-0 start-0 end-0 bottom-0 bg-primary d-flex flex-column align-items-center justify-content-center p-4 text-center" style="z-index:9500;" role="dialog" aria-modal="true" aria-labelledby="age-gate-title">

  <div class="<?php echo $css; ?>__logo mb-4">
    <p class="text-white fw-bold fs-4 mb-0"><?php echo $site_name; ?></p>
  </div>

  <div class="<?php echo $css; ?>__card border rounded-3 p-5" style="border-color:rgba(255,255,255,.2)!important;background:rgba(255,255,255,.06);max-width:420px;width:100%;">

    <div class="fs-1 mb-3" aria-hidden="true">&#127870;</div>

    <h1 id="age-gate-title" class="text-white h3 mb-3"><?php echo $heading; ?></h1>
    <p class="text-white opacity-75 mb-4 lh-lg"><?php echo $subtext; ?></p>

    <div class="d-flex flex-column gap-2">
      <button class="<?php echo $css; ?>__yes btn gf-accent-bg text-white border-0 w-100 fw-bold py-3"><?php echo $cta_yes; ?></button>
      <button class="<?php echo $css; ?>__no btn btn-outline-light w-100 py-3"><?php echo $cta_no; ?></button>
    </div>

  </div>

  <p class="text-white opacity-50 small mt-4 lh-lg" style="max-width:400px;"><?php echo $legal_notice; ?></p>

</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// chatbot-widget — fixed launcher button + chat panel
	// -------------------------------------------------------------------------

	public static function render_chatbot_widget( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-chatbot';

		$panel_heading  = esc_html( $copy['panel_heading']  ?? 'Chat with us' );
		$response_time  = esc_html( $copy['response_time']  ?? 'Typically replies in minutes' );
		$welcome_msg    = esc_html( $copy['welcome_msg']    ?? 'Hi there! How can I help you today?' );
		$user_msg       = esc_html( $copy['user_msg']       ?? 'I have a question about pricing.' );
		$placeholder    = esc_attr( $copy['placeholder']    ?? 'Type a message…' );
		$cta_send       = esc_attr( $copy['cta_send']       ?? 'Send' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="wp-block-group <?php echo $css; ?>">
<!-- wp:html -->

<button class="<?php echo $css; ?>__launcher" aria-label="Open chat" aria-expanded="false" aria-controls="<?php echo $css; ?>-panel">
  <i class="<?php echo $css; ?>__launcher-icon bi bi-chat-dots-fill"></i>
  <i class="<?php echo $css; ?>__launcher-icon-close bi bi-x-lg" hidden></i>
  <span class="<?php echo $css; ?>__badge" hidden>1</span>
</button>

<div id="<?php echo $css; ?>-panel" class="<?php echo $css; ?>__panel" role="dialog" aria-label="Chat window" hidden>

  <div class="<?php echo $css; ?>__header">
    <div>
      <p class="<?php echo $css; ?>__header-title"><?php echo $panel_heading; ?></p>
      <p class="<?php echo $css; ?>__header-sub"><?php echo $response_time; ?></p>
    </div>
    <button class="<?php echo $css; ?>__close" aria-label="Close chat"><i class="bi bi-x-lg"></i></button>
  </div>

  <div class="<?php echo $css; ?>__messages" role="log" aria-live="polite">
    <div class="<?php echo $css; ?>__message <?php echo $css; ?>__message--bot">
      <div class="<?php echo $css; ?>__bubble"><?php echo $welcome_msg; ?></div>
    </div>
    <div class="<?php echo $css; ?>__message <?php echo $css; ?>__message--user">
      <div class="<?php echo $css; ?>__bubble"><?php echo $user_msg; ?></div>
    </div>
    <div class="<?php echo $css; ?>__message <?php echo $css; ?>__message--bot <?php echo $css; ?>__typing">
      <div class="<?php echo $css; ?>__bubble">
        <span class="<?php echo $css; ?>__dot"></span>
        <span class="<?php echo $css; ?>__dot"></span>
        <span class="<?php echo $css; ?>__dot"></span>
      </div>
    </div>
  </div>

  <div class="<?php echo $css; ?>__input-area">
    <textarea class="<?php echo $css; ?>__input" placeholder="<?php echo $placeholder; ?>" aria-label="<?php echo $placeholder; ?>" rows="1"></textarea>
    <button class="<?php echo $css; ?>__send" type="button" aria-label="<?php echo $cta_send; ?>">
      <i class="bi bi-send-fill"></i>
    </button>
  </div>

</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'cookie-consent-bar'   => [ GrayFox_TB_Patterns_Modals::class, 'render_cookie_consent_bar' ],
	'cookie-consent-modal' => [ GrayFox_TB_Patterns_Modals::class, 'render_cookie_consent_modal' ],
	'opt-in-popup'         => [ GrayFox_TB_Patterns_Modals::class, 'render_opt_in_popup' ],
	'announcement-modal'   => [ GrayFox_TB_Patterns_Modals::class, 'render_announcement_modal' ],
	'age-gate'             => [ GrayFox_TB_Patterns_Modals::class, 'render_age_gate' ],
	'chatbot-widget'       => [ GrayFox_TB_Patterns_Modals::class, 'render_chatbot_widget' ],
] );
