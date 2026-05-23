<?php
/**
 * Reusable Hero Section.
 *
 * Used by Featured Listings, Village landing, and other pages that need a
 * top-of-page hero. Pass variables via TemplateLoader::get_rendered().
 *
 * @package OVR
 *
 * @var string $title       Required. Main hero headline.
 * @var string $subtitle    Optional. Subheadline text.
 * @var string $bg_image    Optional. Hero background image URL. Falls back to placeholder.
 * @var string $eyebrow     Optional. Small uppercase label above title.
 * @var string $cta_text    Optional. Primary CTA button text.
 * @var string $cta_url     Optional. Primary CTA URL.
 * @var string $cta_secondary_text  Optional. Secondary CTA text.
 * @var string $cta_secondary_url   Optional. Secondary CTA URL.
 * @var string $align       Optional. 'center' (default) or 'left'.
 * @var string $min_height  Optional. CSS min-height. Default '500px'.
 * @var string $variant     Optional. 'default' or 'gradient' (no image, primary gradient bg).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$title              = $title ?? '';
$subtitle           = $subtitle ?? '';
$bg_image           = $bg_image ?? '';
$eyebrow            = $eyebrow ?? '';
$cta_text           = $cta_text ?? '';
$cta_url            = $cta_url ?? '';
$cta_secondary_text = $cta_secondary_text ?? '';
$cta_secondary_url  = $cta_secondary_url ?? '';
$align              = $align ?? 'center';
$min_height         = $min_height ?? '500px';
$variant            = $variant ?? 'default';

if ( empty( $bg_image ) && 'gradient' !== $variant ) {
    $bg_image = OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
}

$align_class = 'left' === $align ? 'ovr-hero-left' : '';
$content_style = 'left' === $align ? 'text-align:left;margin:0' : 'text-align:center;margin:0 auto';
?>
<section class="ovr-hero <?php echo esc_attr( $align_class ); ?>"
         style="min-height:<?php echo esc_attr( $min_height ); ?>">

    <?php if ( 'gradient' === $variant ) : ?>
        <div class="ovr-hero-bg" style="background:linear-gradient(135deg,var(--ovr-primary),var(--ovr-primary-dark));"></div>
    <?php else : ?>
        <div class="ovr-hero-bg">
            <img src="<?php echo esc_url( $bg_image ); ?>"
                 alt=""
                 loading="eager"
                 fetchpriority="high">
            <div class="ovr-hero-overlay"></div>
        </div>
    <?php endif; ?>

    <div class="ovr-hero-content" style="<?php echo esc_attr( $content_style ); ?>">

        <?php if ( $eyebrow ) : ?>
            <p class="ovr-label-caps"
               style="color:var(--ovr-tertiary-fixed);margin-bottom:12px;display:inline-block;background:rgba(0,0,0,0.25);padding:6px 14px;border-radius:9999px;backdrop-filter:blur(4px)">
                <?php echo esc_html( $eyebrow ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $title ) : ?>
            <h1 class="ovr-h1"><?php echo esc_html( $title ); ?></h1>
        <?php endif; ?>

        <?php if ( $subtitle ) : ?>
            <p style="max-width:600px;<?php echo 'left' === $align ? '' : 'margin-left:auto;margin-right:auto'; ?>">
                <?php echo esc_html( $subtitle ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $cta_text || $cta_secondary_text ) : ?>
            <div style="display:flex;gap:16px;justify-content:<?php echo 'left' === $align ? 'flex-start' : 'center'; ?>;flex-wrap:wrap;margin-top:32px">
                <?php if ( $cta_text && $cta_url ) : ?>
                    <a href="<?php echo esc_url( $cta_url ); ?>"
                       class="ovr-btn ovr-btn-primary ovr-btn-lg ovr-btn-pill">
                        <?php echo esc_html( $cta_text ); ?>
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                <?php endif; ?>

                <?php if ( $cta_secondary_text && $cta_secondary_url ) : ?>
                    <a href="<?php echo esc_url( $cta_secondary_url ); ?>"
                       class="ovr-btn ovr-btn-outline ovr-btn-lg ovr-btn-pill"
                       style="border-color:rgba(255,255,255,0.6);color:#fff">
                        <?php echo esc_html( $cta_secondary_text ); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
