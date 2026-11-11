<?php
/**
 * Widgets page: every widget shipped with the plugin, with an on / off switch.
 */

use Ems_Elements_Share\Widgets\Ems_Widgets_Manager;

$widgets = Ems_Widgets_Manager::get_widgets();
$enabled = 0;
foreach ( array_keys( $widgets ) as $widget_key ) {
    $enabled += Ems_Widgets_Manager::is_enabled( $widget_key ) ? 1 : 0;
}

$environment = array(
    array(
        'label'  => __( 'Elementor', 'mme-elementor-kit' ),
        'ok'     => defined( 'ELEMENTOR_VERSION' ),
        'value'  => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : __( 'Not active — widgets are not available', 'mme-elementor-kit' ),
    ),
    array(
        'label'  => __( 'Elementor Pro', 'mme-elementor-kit' ),
        'ok'     => defined( 'ELEMENTOR_PRO_VERSION' ),
        'value'  => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : __( 'Optional — needed for dynamic tags', 'mme-elementor-kit' ),
    ),
    array(
        'label'  => __( 'ACF / SCF', 'mme-elementor-kit' ),
        'ok'     => class_exists( 'ACF' ),
        'value'  => class_exists( 'ACF' ) ? ( defined( 'ACF_VERSION' ) ? ACF_VERSION : __( 'Active', 'mme-elementor-kit' ) ) : __( 'Optional — without it fields are read as post meta', 'mme-elementor-kit' ),
    ),
);
?>
<div class="wrap ems-app ems-app--widgets">
    <?php require __DIR__ . '/page-header.php'; ?>

    <div class="ems-layout">
        <main class="ems-layout__main">
            <form action="" method="POST" class="ems-card">
                <div class="ems-card__header">
                    <div>
                        <h2 class="ems-card__title"><?php echo esc_html__( 'MMe Widgets', 'mme-elementor-kit' ); ?></h2>
                        <p class="ems-card__desc">
                            <?php
                            /* translators: 1: enabled widgets, 2: all widgets */
                            printf( esc_html__( '%1$d of %2$d widgets enabled. Enabled widgets appear in the "MMe Widgets" category of the Elementor panel.', 'mme-elementor-kit' ), (int) $enabled, count( $widgets ) );
                            ?>
                        </p>
                    </div>
                </div>

                <?php do_action( 'ems_elements_share_widgets_notice' ); ?>

                <div class="ems-widget-grid">
                    <?php foreach ( $widgets as $widget_key => $widget ) : ?>
                        <?php $is_enabled = Ems_Widgets_Manager::is_enabled( $widget_key ); ?>
                        <div class="ems-widget <?php echo $is_enabled ? '' : 'is-disabled'; ?>" id="widget-<?php echo esc_attr( $widget_key ); ?>">
                            <div class="ems-widget__head">
                                <span class="ems-widget__icon dashicons <?php echo esc_attr( $widget['icon'] ); ?>"></span>
                                <label class="ems-switch" title="<?php echo esc_attr__( 'Enable / disable', 'mme-elementor-kit' ); ?>">
                                    <input type="checkbox" name="mme_kit_widgets[]" value="<?php echo esc_attr( $widget_key ); ?>" <?php checked( $is_enabled ); ?>>
                                    <span class="ems-switch__track"></span>
                                </label>
                            </div>
                            <h3 class="ems-widget__title"><?php echo esc_html( $widget['title'] ); ?></h3>
                            <p class="ems-widget__desc"><?php echo esc_html( $widget['description'] ); ?></p>
                            <div class="ems-widget__tags">
                                <?php foreach ( $widget['tags'] as $tag ) : ?>
                                    <span class="ems-badge"><?php echo esc_html( $tag ); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php if ( ! empty( $widget['summary'] ) ) : ?>
                                <p class="ems-widget__summary"><?php echo esc_html( call_user_func( $widget['summary'] ) ); ?></p>
                            <?php endif; ?>
                            <p class="ems-note">
                                <span class="dashicons dashicons-lightbulb"></span>
                                <?php echo esc_html( $widget['usage'] ); ?>
                            </p>
                            <?php if ( ! empty( $widget['settings'] ) ) : ?>
                                <button type="button" class="ems-btn ems-btn--ghost ems-btn--block ems-field" data-ems-dialog="ems-settings-<?php echo esc_attr( $widget_key ); ?>">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <?php echo esc_html__( 'Settings', 'mme-elementor-kit' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php wp_nonce_field( 'mme_kit_save_widgets', '_wp_nonce_widgets' ); ?>
                <input type="hidden" name="action" value="mme_kit_save_widgets">
                <div class="ems-form-actions">
                    <button type="submit" class="ems-btn ems-btn--primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo esc_html__( 'Save Changes', 'mme-elementor-kit' ); ?>
                    </button>
                </div>
            </form>

            <?php // Settings dialogs live outside the on / off form: forms cannot be nested. ?>
            <?php foreach ( $widgets as $widget_key => $widget ) : ?>
                <?php if ( empty( $widget['settings'] ) ) { continue; } ?>
                <dialog class="ems-dialog" id="ems-settings-<?php echo esc_attr( $widget_key ); ?>">
                    <form action="" method="POST" class="ems-dialog__form">
                        <div class="ems-dialog__header">
                            <h2 class="ems-card__title">
                                <?php
                                /* translators: %s: widget name */
                                printf( esc_html__( '%s settings', 'mme-elementor-kit' ), esc_html( $widget['title'] ) );
                                ?>
                            </h2>
                            <button type="button" class="ems-icon-btn" data-ems-dialog-close title="<?php echo esc_attr__( 'Close', 'mme-elementor-kit' ); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="ems-dialog__body">
                            <?php call_user_func( $widget['settings'] ); ?>
                        </div>
                        <div class="ems-dialog__footer">
                            <button type="button" class="ems-btn ems-btn--ghost" data-ems-dialog-close><?php echo esc_html__( 'Cancel', 'mme-elementor-kit' ); ?></button>
                            <button type="submit" class="ems-btn ems-btn--primary">
                                <span class="dashicons dashicons-saved"></span>
                                <?php echo esc_html__( 'Save settings', 'mme-elementor-kit' ); ?>
                            </button>
                        </div>
                    </form>
                </dialog>
            <?php endforeach; ?>
        </main>

        <aside class="ems-layout__aside">
            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Environment', 'mme-elementor-kit' ); ?></h2>
                <ul class="ems-meta">
                    <?php foreach ( $environment as $item ) : ?>
                        <li>
                            <span>
                                <span class="dashicons <?php echo $item['ok'] ? 'dashicons-yes-alt ems-text-green' : 'dashicons-minus ems-text-muted'; ?>"></span>
                                <?php echo esc_html( $item['label'] ); ?>
                            </span>
                            <strong><?php echo esc_html( $item['value'] ); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="ems-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php echo esc_html__( 'Widgets work on any site with this plugin and Elementor, no master connection needed.', 'mme-elementor-kit' ); ?>
                </p>
            </div>
        </aside>
    </div>
</div>
