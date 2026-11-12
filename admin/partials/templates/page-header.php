<?php
/**
 * Shared header with the section switcher (Master / Client / Widgets / Updates / MCP).
 *
 * @var string $active_page Slug of the page being rendered.
 */

$ems_tabs = array(
    Ems_Elements_Share_Menu_Handler::MASTER_SLUG => array(
        'label' => __( 'Master (Server)', 'mme-elementor-kit' ),
        'icon'  => Ems_Elements_Share\Admin\Ems_Master_License::get_instance()->is_active() ? 'dashicons-cloud-upload' : 'dashicons-lock',
    ),
    Ems_Elements_Share_Menu_Handler::CLIENT_SLUG => array(
        'label' => __( 'Client', 'mme-elementor-kit' ),
        'icon'  => 'dashicons-cloud-saved',
    ),
    Ems_Elements_Share_Menu_Handler::WIDGETS_SLUG => array(
        'label' => __( 'Widgets', 'mme-elementor-kit' ),
        'icon'  => 'dashicons-screenoptions',
    ),
);
if ( current_user_can( 'update_plugins' ) ) {
    $ems_tabs[ Ems_Elements_Share_Menu_Handler::UPDATES_SLUG ] = array(
        'label' => __( 'Updates', 'mme-elementor-kit' ),
        'icon'  => 'dashicons-update',
        'badge' => Ems_Elements_Share\Admin\Ems_Updater::get_instance()->has_update( false ),
    );
}
$ems_tabs[ Ems_Elements_Share_Menu_Handler::MCP_SLUG ] = array(
    'label' => __( 'MCP & Logs', 'mme-elementor-kit' ),
    'icon'  => 'dashicons-rest-api',
);
?>
<header class="ems-header">
    <div class="ems-header__brand">
        <span class="ems-header__logo dashicons dashicons-share"></span>
        <div>
            <h1 class="ems-header__title"><?php echo esc_html__( 'MMe Elementor Kit', 'mme-elementor-kit' ); ?></h1>
            <p class="ems-header__subtitle"><?php echo esc_html__( 'Template sharing and Elementor widgets by MMe.vn.', 'mme-elementor-kit' ); ?></p>
        </div>
    </div>
    <nav class="ems-switcher" aria-label="<?php echo esc_attr__( 'Sections', 'mme-elementor-kit' ); ?>">
        <?php foreach ( $ems_tabs as $slug => $tab ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="ems-switcher__item <?php echo $slug === $active_page ? 'is-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                <?php echo esc_html( $tab['label'] ); ?>
                <?php if ( ! empty( $tab['badge'] ) ) : ?>
                    <span class="ems-dot" title="<?php echo esc_attr__( 'Update available', 'mme-elementor-kit' ); ?>"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
<hr class="wp-header-end">
