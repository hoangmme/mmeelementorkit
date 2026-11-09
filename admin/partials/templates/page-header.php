<?php
/**
 * Shared header with the Master / Client switcher.
 *
 * @var string $active_page Slug of the page being rendered.
 */

$ems_tabs = array(
    Ems_Elements_Share_Menu_Handler::MASTER_SLUG => array(
        'label' => __( 'Master (Server)', 'mme-elementor-kit' ),
        'icon'  => 'dashicons-cloud-upload',
    ),
    Ems_Elements_Share_Menu_Handler::CLIENT_SLUG => array(
        'label' => __( 'Client', 'mme-elementor-kit' ),
        'icon'  => 'dashicons-cloud-saved',
    ),
);
?>
<header class="ems-header">
    <div class="ems-header__brand">
        <span class="ems-header__logo dashicons dashicons-share"></span>
        <div>
            <h1 class="ems-header__title"><?php echo esc_html__( 'MMe Elementor Kit', 'mme-elementor-kit' ); ?></h1>
            <p class="ems-header__subtitle"><?php echo esc_html__( 'Share Elementor templates from a master site to client sites.', 'mme-elementor-kit' ); ?></p>
        </div>
    </div>
    <nav class="ems-switcher" aria-label="<?php echo esc_attr__( 'Site role', 'mme-elementor-kit' ); ?>">
        <?php foreach ( $ems_tabs as $slug => $tab ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="ems-switcher__item <?php echo $slug === $active_page ? 'is-active' : ''; ?>">
                <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                <?php echo esc_html( $tab['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
<hr class="wp-header-end">
