<?php
/**
 * Tabbed Property Meta Box Wrapper.
 *
 * Renders the tab navigation and includes each panel template.
 *
 * @package OVR
 *
 * @var WP_Post $post
 * @var array   $meta
 * @var array   $tabs          Tab key => [ 'label' => string, 'icon' => string ].
 * @var array   $seasonal      Existing seasonal pricing rows.
 * @var array   $availability  Existing availability blocks.
 * @var array   $gallery_ids   Attachment IDs.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use OVR\Core\TemplateLoader;

$first_key = array_key_first( $tabs );
?>
<div class="ovr-meta-tabs" role="tablist">

    <!-- Tab Nav -->
    <div class="ovr-meta-tabs__nav" role="tablist">
        <?php foreach ( $tabs as $key => $tab ) : ?>
            <button type="button"
                    class="ovr-meta-tabs__btn <?php echo $key === $first_key ? 'is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $key === $first_key ? 'true' : 'false'; ?>"
                    aria-controls="ovr-tab-panel-<?php echo esc_attr( $key ); ?>"
                    data-tab="<?php echo esc_attr( $key ); ?>">
                <span class="material-symbols-outlined" aria-hidden="true">
                    <?php echo esc_html( $tab['icon'] ); ?>
                </span>
                <span><?php echo esc_html( $tab['label'] ); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Tab Panels -->
    <?php foreach ( $tabs as $key => $tab ) : ?>
        <div class="ovr-meta-tabs__panel <?php echo $key === $first_key ? 'is-active' : ''; ?>"
             id="ovr-tab-panel-<?php echo esc_attr( $key ); ?>"
             role="tabpanel"
             aria-labelledby="ovr-tab-btn-<?php echo esc_attr( $key ); ?>"
             data-tab="<?php echo esc_attr( $key ); ?>">
            <?php
            $args = [
                'post'         => $post,
                'meta'         => $meta,
                'seasonal'     => $seasonal,
                'availability' => $availability,
                'gallery_ids'  => $gallery_ids,
            ];

            switch ( $key ) {
                case 'general':
                    TemplateLoader::render( 'admin/property-tabs/general.php', $args );
                    break;
                case 'pricing':
                    TemplateLoader::render( 'admin/property-tabs/pricing.php', $args );
                    break;
                case 'location':
                    TemplateLoader::render( 'admin/property-tabs/location.php', $args );
                    break;
                case 'media':
                    TemplateLoader::render( 'admin/property-tabs/media.php', $args );
                    break;
                case 'seasonal':
                    TemplateLoader::render( 'admin/property-tabs/seasonal.php', $args );
                    break;
                case 'availability':
                    TemplateLoader::render( 'admin/property-tabs/availability.php', $args );
                    break;
            }
            ?>
        </div>
    <?php endforeach; ?>
</div>
