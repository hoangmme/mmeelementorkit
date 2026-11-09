<?php
/**
 * Master page while Master mode is not activated.
 *
 * @var Ems_Elements_Share\Admin\Ems_Master_License $license
 */

$status = $license->get_status();
?>
<div class="wrap ems-app ems-app--master">
    <?php require __DIR__ . '/page-header.php'; ?>

    <div class="ems-lock">
        <div class="ems-card ems-lock__card">
            <span class="ems-lock__icon dashicons dashicons-lock"></span>
            <h2 class="ems-lock__title"><?php echo esc_html__( 'Master mode is locked', 'mme-elementor-kit' ); ?></h2>
            <p class="ems-card__desc">
                <?php echo esc_html__( 'Master mode lets this site share its Elementor templates with client sites. It needs an activation code issued by MMe.vn for this domain.', 'mme-elementor-kit' ); ?>
            </p>

            <?php do_action( 'ems_elements_share_license_notice' ); ?>
            <?php if ( 'missing' !== $status->get_error_code() ) : ?>
                <div class="ems-alert ems-alert--error">
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html( $status->get_error_message() ); ?>
                </div>
            <?php endif; ?>

            <div class="ems-field">
                <label class="ems-label" for="ems-site-domain"><?php echo esc_html__( 'This site\'s domain (send it to MMe.vn)', 'mme-elementor-kit' ); ?></label>
                <div class="ems-input-group">
                    <input type="text" class="ems-input ems-input--mono" id="ems-site-domain" value="<?php echo esc_attr( $license->get_site_domain() ); ?>" readonly>
                    <button type="button" class="ems-btn ems-btn--ghost" data-ems-copy="#ems-site-domain">
                        <span class="dashicons dashicons-admin-page"></span>
                        <span class="ems-copy-label"><?php echo esc_html__( 'Copy', 'mme-elementor-kit' ); ?></span>
                    </button>
                </div>
            </div>

            <form action="" method="POST">
                <div class="ems-field">
                    <label class="ems-label" for="ems-master-code"><?php echo esc_html__( 'Activation code', 'mme-elementor-kit' ); ?></label>
                    <textarea class="ems-input ems-input--mono" id="ems-master-code" name="mme_kit_master_code" rows="4" required></textarea>
                </div>
                <?php wp_nonce_field( 'mme_kit_master_license', '_wp_nonce_master_license' ); ?>
                <input type="hidden" name="action" value="mme_kit_activate_master">
                <div class="ems-form-actions">
                    <button type="submit" class="ems-btn ems-btn--primary">
                        <span class="dashicons dashicons-unlock"></span>
                        <?php echo esc_html__( 'Activate Master mode', 'mme-elementor-kit' ); ?>
                    </button>
                    <a class="ems-btn ems-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Ems_Elements_Share_Menu_Handler::CLIENT_SLUG ) ); ?>">
                        <?php echo esc_html__( 'Use this site as a client', 'mme-elementor-kit' ); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
