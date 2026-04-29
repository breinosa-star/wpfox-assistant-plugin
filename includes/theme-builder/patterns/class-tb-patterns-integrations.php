<?php
/**
 * Integrations / trust pattern renderers.
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
 * Class GrayFox_TB_Patterns_Integrations
 */
class GrayFox_TB_Patterns_Integrations {

	// -------------------------------------------------------------------------
	// integration-tiles — 6 integration cards in 2 rows of 3
	// -------------------------------------------------------------------------

	public static function render_integration_tiles( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-integrations gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Integrates With Your Stack' );
		$subtext = esc_html( $copy['subtext']         ?? 'Connect the tools you already use in minutes.' );
		$i1      = esc_attr( $copy['int1_name']        ?? 'Slack' );
		$i1d     = esc_html( $copy['int1_desc']        ?? 'Send alerts and notifications to any channel.' );
		$i2      = esc_attr( $copy['int2_name']        ?? 'Salesforce' );
		$i2d     = esc_html( $copy['int2_desc']        ?? 'Sync customer data bidirectionally.' );
		$i3      = esc_attr( $copy['int3_name']        ?? 'HubSpot' );
		$i3d     = esc_html( $copy['int3_desc']        ?? 'Keep marketing and sales aligned.' );
		$i4      = esc_attr( $copy['int4_name']        ?? 'Stripe' );
		$i4d     = esc_html( $copy['int4_desc']        ?? 'Handle payments and subscriptions seamlessly.' );
		$i5      = esc_attr( $copy['int5_name']        ?? 'Zapier' );
		$i5d     = esc_html( $copy['int5_desc']        ?? 'Automate workflows with 5,000+ apps.' );
		$i6      = esc_attr( $copy['int6_name']        ?? 'GitHub' );
		$i6d     = esc_html( $copy['int6_desc']        ?? 'Link issues to code changes automatically.' );

		$tile = function( string $name, string $desc, string $icon ) {
			ob_start(); ?>
<!-- wp:group {"className":"gf-integration-tile","backgroundColor":"background"} -->
<div class="wp-block-group gf-integration-tile has-background-background-color has-background">
<!-- wp:group {"className":"d-flex align-items-center gap-3 mb-2"} -->
<div class="wp-block-group d-flex align-items-center gap-3 mb-2">
<!-- wp:group {"className":"gf-integration-icon","backgroundColor":"primary"} -->
<div class="wp-block-group gf-integration-icon has-primary-background-color has-background">
<!-- wp:html --><i class="bi <?php echo $icon; ?>"></i><!-- /wp:html -->
</div>
<!-- /wp:group -->
<!-- wp:heading {"level":4,"textColor":"foreground"} --><h4 class="wp-block-heading has-foreground-color has-text-color"><?php echo $name; ?></h4><!-- /wp:heading -->
</div>
<!-- /wp:group -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $desc; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$t1 = $tile( $i1, $i1d, 'bi-slack'                );
		$t2 = $tile( $i2, $i2d, 'bi-cloud-fill'            );
		$t3 = $tile( $i3, $i3d, 'bi-megaphone-fill'        );
		$t4 = $tile( $i4, $i4d, 'bi-credit-card-fill'      );
		$t5 = $tile( $i5, $i5d, 'bi-lightning-charge-fill' );
		$t6 = $tile( $i6, $i6d, 'bi-github'                );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"style":{"spacing":{"blockGap":"1.25rem","margin":{"top":"2.5rem"}}}} -->
<div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><?php echo $t1; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t2; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t3; ?></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->
<!-- wp:columns {"style":{"spacing":{"blockGap":"1.25rem","margin":{"top":"1.25rem"}}}} -->
<div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><?php echo $t4; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t5; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t6; ?></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// partner-logo-grid — gold/silver tiered partner logos
	// -------------------------------------------------------------------------

	public static function render_partner_logo_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-partner-logos';

		$heading      = esc_html( $copy['section_heading'] ?? 'Our Partner Network' );
		$subtext      = esc_html( $copy['subtext']         ?? 'Trusted by industry-leading organizations worldwide.' );
		$gold_label   = esc_html( $copy['gold_label']      ?? 'Gold Partners' );
		$silver_label = esc_html( $copy['silver_label']    ?? 'Silver Partners' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<p class="gf-partner-tier-label"><i class="bi bi-trophy-fill me-1" aria-hidden="true"></i> <?php echo $gold_label; ?></p>
<div class="gf-partner-logo-row">
  <figure class="gf-partner-logo"><img src="" alt="Gold Partner 1"/></figure>
  <figure class="gf-partner-logo"><img src="" alt="Gold Partner 2"/></figure>
  <figure class="gf-partner-logo"><img src="" alt="Gold Partner 3"/></figure>
</div>
<!-- /wp:html -->

<!-- wp:separator {"backgroundColor":"muted"} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->

<!-- wp:html -->
<p class="gf-partner-tier-label mt-4"><i class="bi bi-award me-1" aria-hidden="true"></i> <?php echo $silver_label; ?></p>
<div class="gf-partner-logo-row">
  <figure class="gf-partner-logo"><img src="" alt="Silver Partner 1"/></figure>
  <figure class="gf-partner-logo"><img src="" alt="Silver Partner 2"/></figure>
  <figure class="gf-partner-logo"><img src="" alt="Silver Partner 3"/></figure>
  <figure class="gf-partner-logo"><img src="" alt="Silver Partner 4"/></figure>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// ecosystem-diagram — hub-and-spoke diagram with center + 6 nodes
	// -------------------------------------------------------------------------

	public static function render_ecosystem_diagram( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-ecosystem';

		$heading = esc_html( $copy['section_heading'] ?? 'The Platform Ecosystem' );
		$subtext = esc_html( $copy['subtext']         ?? 'Everything connects through a single powerful platform.' );
		$center  = esc_html( $copy['center_label']    ?? 'Your Platform' );
		$n1      = esc_html( $copy['node1']           ?? 'Analytics' );
		$n2      = esc_html( $copy['node2']           ?? 'CRM' );
		$n3      = esc_html( $copy['node3']           ?? 'Finance' );
		$n4      = esc_html( $copy['node4']           ?? 'Support' );
		$n5      = esc_html( $copy['node5']           ?? 'Marketing' );
		$n6      = esc_html( $copy['node6']           ?? 'DevOps' );

		$nd = function( string $icon, string $label ): string {
			ob_start(); ?>
    <div class="gf-ecosystem-node">
      <i class="bi <?php echo $icon; ?>"></i>
      <span><?php echo $label; ?></span>
    </div>
			<?php return ob_get_clean();
		};

		$nd1 = $nd( 'bi-bar-chart-fill', $n1 );
		$nd2 = $nd( 'bi-people-fill',    $n2 );
		$nd3 = $nd( 'bi-cash-stack',     $n3 );
		$nd4 = $nd( 'bi-headset',        $n4 );
		$nd5 = $nd( 'bi-megaphone-fill', $n5 );
		$nd6 = $nd( 'bi-gear-fill',      $n6 );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="container py-3">
  <div class="gf-ecosystem-hub">
    <?php echo $nd1; ?>
    <?php echo $nd2; ?>
    <?php echo $nd3; ?>
    <div class="gf-ecosystem-center">
      <span><?php echo $center; ?></span>
    </div>
    <?php echo $nd4; ?>
    <?php echo $nd5; ?>
    <?php echo $nd6; ?>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// certifications-row — 4 compliance/certification badges
	// -------------------------------------------------------------------------

	public static function render_certifications_row( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-certifications gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Security & Compliance' );
		$subtext = esc_html( $copy['subtext']         ?? 'We take data protection seriously.' );
		$c1      = esc_html( $copy['cert1_name']      ?? 'SOC 2 Type II' );
		$c1d     = esc_html( $copy['cert1_desc']      ?? 'Audited annually for security, availability, and confidentiality controls.' );
		$c2      = esc_html( $copy['cert2_name']      ?? 'ISO 27001' );
		$c2d     = esc_html( $copy['cert2_desc']      ?? 'Internationally recognized information security management standard.' );
		$c3      = esc_html( $copy['cert3_name']      ?? 'GDPR' );
		$c3d     = esc_html( $copy['cert3_desc']      ?? 'Fully compliant with EU data protection regulations.' );
		$c4      = esc_html( $copy['cert4_name']      ?? 'HIPAA' );
		$c4d     = esc_html( $copy['cert4_desc']      ?? 'Healthcare data handled with required safeguards in place.' );

		$certs = [
			[ 'name' => $c1, 'desc' => $c1d, 'icon' => 'bi-shield-check' ],
			[ 'name' => $c2, 'desc' => $c2d, 'icon' => 'bi-award'        ],
			[ 'name' => $c3, 'desc' => $c3d, 'icon' => 'bi-file-earmark-lock' ],
			[ 'name' => $c4, 'desc' => $c4d, 'icon' => 'bi-heart-pulse'  ],
		];

		$cards = '';
		foreach ( $certs as $cert ) {
			ob_start(); ?>
      <div class="col-sm-6 col-lg-3">
        <div class="gf-cert-badge h-100">
          <i class="bi <?php echo $cert['icon']; ?> gf-cert-badge__icon"></i>
          <h4 class="gf-cert-badge__name"><?php echo $cert['name']; ?></h4>
          <p class="gf-cert-badge__desc"><?php echo $cert['desc']; ?></p>
        </div>
      </div>
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container">
  <h2 class="text-center gf-section-heading" style="color:var(--gf-primary)"><?php echo $heading; ?></h2>
  <p class="text-center gf-section-subtext" style="color:var(--gf-muted)"><?php echo $subtext; ?></p>
  <div class="row g-4 mt-2">
<?php echo $cards; ?>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// security-feature-list — image + 6 security feature rows
	// -------------------------------------------------------------------------

	public static function render_security_feature_list( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-security-list';

		$heading = esc_html( $copy['section_heading'] ?? 'Built for Security' );
		$subtext = esc_html( $copy['subtext']         ?? 'Every layer of our platform is designed with protection in mind.' );
		$f1      = esc_html( $copy['f1_title']        ?? 'End-to-End Encryption' );
		$f1d     = esc_html( $copy['f1_desc']         ?? 'All data encrypted at rest and in transit using AES-256 and TLS 1.3.' );
		$f2      = esc_html( $copy['f2_title']        ?? 'Role-Based Access Control' );
		$f2d     = esc_html( $copy['f2_desc']         ?? 'Granular permissions ensure users only see what they need to.' );
		$f3      = esc_html( $copy['f3_title']        ?? 'Single Sign-On (SSO)' );
		$f3d     = esc_html( $copy['f3_desc']         ?? 'SAML 2.0 and OIDC support for seamless enterprise auth.' );
		$f4      = esc_html( $copy['f4_title']        ?? 'Audit Logs' );
		$f4d     = esc_html( $copy['f4_desc']         ?? 'Immutable event logs capture every action across your workspace.' );
		$f5      = esc_html( $copy['f5_title']        ?? 'Penetration Testing' );
		$f5d     = esc_html( $copy['f5_desc']         ?? 'Third-party security audits conducted annually and on major releases.' );
		$f6      = esc_html( $copy['f6_title']        ?? '99.99% Uptime SLA' );
		$f6d     = esc_html( $copy['f6_desc']         ?? 'Redundant infrastructure across multiple availability zones.' );

		$sec_row = function( $icon, $title, $desc ) {
			ob_start(); ?>
<!-- wp:group {"style":{"spacing":{"blockGap":"1rem"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.5rem"}}} --><p style="min-width:2rem"><?php echo $icon; ?></p><!-- /wp:paragraph -->
<!-- wp:group {"style":{"spacing":{"blockGap":"0.25rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":4,"style":{"typography":{"fontSize":"1rem"}},"textColor":"foreground"} --><h4 class="wp-block-heading has-foreground-color has-text-color"><?php echo $title; ?></h4><!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted","style":{"typography":{"fontSize":"0.9rem"}}} --><p class="has-muted-color has-text-color"><?php echo $desc; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$r1 = $sec_row( '&#128272;', $f1, $f1d );
		$r2 = $sec_row( '&#128100;', $f2, $f2d );
		$r3 = $sec_row( '&#128273;', $f3, $f3d );
		$r4 = $sec_row( '&#128203;', $f4, $f4d );
		$r5 = $sec_row( '&#128737;&#65039;', $f5, $f5d );
		$r6 = $sec_row( '&#9989;', $f6, $f6d );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:columns {"style":{"spacing":{"blockGap":"4rem"}},"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center">

<!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%">
<!-- wp:heading {"textColor":"primary"} -->
<h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
<!-- wp:image {"sizeSlug":"full","className":"gf-security-img","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-full gf-security-img"><img src="" alt="Security illustration"/></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%">
<!-- wp:group {"style":{"spacing":{"blockGap":"2rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group">
<?php echo $r1; ?>
<?php echo $r2; ?>
<?php echo $r3; ?>
<?php echo $r4; ?>
<?php echo $r5; ?>
<?php echo $r6; ?>
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'integration-tiles'     => [ GrayFox_TB_Patterns_Integrations::class, 'render_integration_tiles' ],
	'partner-logo-grid'     => [ GrayFox_TB_Patterns_Integrations::class, 'render_partner_logo_grid' ],
	'ecosystem-diagram'     => [ GrayFox_TB_Patterns_Integrations::class, 'render_ecosystem_diagram' ],
	'certifications-row'    => [ GrayFox_TB_Patterns_Integrations::class, 'render_certifications_row' ],
	'security-feature-list' => [ GrayFox_TB_Patterns_Integrations::class, 'render_security_feature_list' ],
] );
