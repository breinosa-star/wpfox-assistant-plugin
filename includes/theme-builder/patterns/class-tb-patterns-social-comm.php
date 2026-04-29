<?php
/**
 * Social sharing & communication widget pattern renderers.
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
 * Class GrayFox_TB_Patterns_SocialComm
 */
class GrayFox_TB_Patterns_SocialComm {

	// -------------------------------------------------------------------------
	// social-share-bar — inline share bar with social network buttons + copy link
	// -------------------------------------------------------------------------

	public static function render_social_share_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-social-share-bar';

		$label      = esc_html( $copy['label']       ?? 'Share this article:' );
		$share_url  = esc_attr( $copy['share_url']   ?? 'https://example.com/post' );
		$share_text = esc_attr( $copy['share_text']  ?? 'Check this out!' );

		$twitter_url  = 'https://twitter.com/intent/tweet?url=' . $share_url . '&text=' . $share_text;
		$facebook_url = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url;
		$linkedin_url = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url;
		$whatsapp_url = 'whatsapp://send?text=' . $share_text . '%20' . $share_url;

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="wp-block-group <?php echo $css; ?>">
<!-- wp:html -->
<div class="<?php echo $css; ?>__inner">
  <span class="<?php echo $css; ?>__label"><?php echo $label; ?></span>
  <div class="<?php echo $css; ?>__buttons">
    <a href="<?php echo $twitter_url; ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Twitter / X" class="<?php echo $css; ?>__btn <?php echo $css; ?>__btn--twitter">&#120143; Twitter / X</a>
    <a href="<?php echo $facebook_url; ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook" class="<?php echo $css; ?>__btn <?php echo $css; ?>__btn--facebook">Facebook</a>
    <a href="<?php echo $linkedin_url; ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on LinkedIn" class="<?php echo $css; ?>__btn <?php echo $css; ?>__btn--linkedin">LinkedIn</a>
    <a href="<?php echo $whatsapp_url; ?>" target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp" class="<?php echo $css; ?>__btn <?php echo $css; ?>__btn--whatsapp">&#128602; WhatsApp</a>
    <button class="gf-share-copy <?php echo $css; ?>__btn <?php echo $css; ?>__btn--copy" data-url="<?php echo $share_url; ?>">&#128279; Copy Link</button>
  </div>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// floating-contact-group — fixed bottom-right contact action cluster
	// -------------------------------------------------------------------------

	public static function render_floating_contact_group( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-floating-contacts';

		$phone_number  = esc_html( $copy['phone_number']    ?? '+1 (555) 000-0000' );
		$email_address = esc_html( $copy['email_address']   ?? 'hello@example.com' );
		$whatsapp_num  = esc_attr( $copy['whatsapp_number'] ?? '15550000000' );
		$chat_label    = esc_html( $copy['chat_label']      ?? 'Live Chat' );

		$phone_href = 'tel:' . preg_replace( '/[^0-9+]/', '', $phone_number );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>"} -->
<div class="wp-block-group <?php echo $css; ?>">
<!-- wp:html -->
<div class="gf-floating-contacts" aria-label="Contact options">

  <div class="gf-floating-contacts__actions">

    <a href="<?php echo $phone_href; ?>" class="gf-floating-contacts__item gf-floating-contacts__item--phone" aria-label="Call us">
      <span class="gf-floating-contacts__icon gf-floating-contacts__icon--primary"><i class="bi bi-telephone-fill"></i></span>
      <?php echo $phone_number; ?>
    </a>

    <a href="https://wa.me/<?php echo $whatsapp_num; ?>" target="_blank" rel="noopener noreferrer" class="gf-floating-contacts__item gf-floating-contacts__item--whatsapp" aria-label="WhatsApp">
      <span class="gf-floating-contacts__icon gf-floating-contacts__icon--whatsapp"><i class="bi bi-whatsapp"></i></span>
      WhatsApp
    </a>

    <a href="mailto:<?php echo $email_address; ?>" class="gf-floating-contacts__item gf-floating-contacts__item--email" aria-label="Email us">
      <span class="gf-floating-contacts__icon gf-floating-contacts__icon--primary"><i class="bi bi-envelope-fill"></i></span>
      <?php echo $email_address; ?>
    </a>

    <button class="gf-floating-contacts__item gf-floating-contacts__item--chat gf-chatbot-trigger" aria-label="<?php echo $chat_label; ?>">
      <span class="gf-floating-contacts__icon gf-floating-contacts__icon--accent"><i class="bi bi-chat-dots-fill"></i></span>
      <?php echo $chat_label; ?>
    </button>

  </div>

  <button class="gf-floating-contacts__toggle" aria-label="Contact us" aria-expanded="false"><i class="bi bi-telephone-fill"></i></button>

</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// push-optin-banner — fixed top banner for push notification opt-in
	// -------------------------------------------------------------------------

	public static function render_push_optin_banner( array $spec, array $manifest ): string {
		$copy        = $spec['copy']        ?? [];
		$classes     = $spec['css_classes'] ?? [];
		$section_css = implode( ' ', $classes ) ?: 'gf-push-banner';

		$message     = esc_html( $copy['message']     ?? 'Get notified about new articles and updates.' );
		$cta_allow   = esc_html( $copy['cta_allow']   ?? 'Allow Notifications' );
		$cta_dismiss = esc_html( $copy['cta_dismiss'] ?? 'Not now' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_css; ?>","align":"full","backgroundColor":"primary"} -->
<section class="wp-block-group alignfull <?php echo $section_css; ?> has-primary-background-color has-background">
<!-- wp:html -->
<div class="gf-push-banner__inner" role="region" aria-label="Push notification opt-in">
  <span class="gf-push-banner__icon" aria-hidden="true"><i class="bi bi-bell-fill"></i></span>
  <p class="gf-push-banner__message"><?php echo $message; ?></p>
  <div class="gf-push-banner__actions">
    <button class="gf-push-banner__allow"><?php echo $cta_allow; ?></button>
    <button class="gf-push-banner__dismiss"><?php echo $cta_dismiss; ?></button>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'social-share-bar'       => [ GrayFox_TB_Patterns_SocialComm::class, 'render_social_share_bar' ],
	'floating-contact-group' => [ GrayFox_TB_Patterns_SocialComm::class, 'render_floating_contact_group' ],
	'push-optin-banner'      => [ GrayFox_TB_Patterns_SocialComm::class, 'render_push_optin_banner' ],
] );
