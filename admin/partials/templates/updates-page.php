<?php
/**
 * Updates page: compare the installed version with the release branch on GitHub.
 *
 * @var Ems_Elements_Share\Admin\Ems_Updater $updater
 */

$info       = $updater->get_remote_info();
$has_update = $updater->has_update();
$latest     = ! empty( $info['version'] ) ? $info['version'] : '—';
?>
<div class="wrap ems-app ems-app--updates">
    <?php require __DIR__ . '/page-header.php'; ?>

    <section class="ems-status <?php echo $has_update ? 'is-update' : ( empty( $info['error'] ) ? 'is-connected' : 'is-disconnected' ); ?>">
        <span class="ems-status__icon dashicons <?php echo $has_update ? 'dashicons-update' : ( empty( $info['error'] ) ? 'dashicons-yes-alt' : 'dashicons-warning' ); ?>"></span>
        <div class="ems-status__body">
            <span class="ems-status__label">
                <?php
                if ( $has_update ) {
                    echo esc_html__( 'Update available', 'mme-elementor-kit' );
                } elseif ( empty( $info['error'] ) ) {
                    echo esc_html__( 'Up to date', 'mme-elementor-kit' );
                } else {
                    echo esc_html__( 'Could not check', 'mme-elementor-kit' );
                }
                ?>
            </span>
            <h2 class="ems-status__title">
                <?php
                /* translators: 1: installed version, 2: latest version */
                printf( esc_html__( 'Installed %1$s · Latest on GitHub %2$s', 'mme-elementor-kit' ), '<strong>' . esc_html( EMS_ELEMENTS_SHARE_VERSION ) . '</strong>', '<strong>' . esc_html( $latest ) . '</strong>' );
                ?>
            </h2>
            <?php if ( ! empty( $info['checked'] ) ) : ?>
                <span class="ems-status__meta">
                    <?php
                    /* translators: %s: time ago */
                    printf( esc_html__( 'Checked %s ago', 'mme-elementor-kit' ), esc_html( human_time_diff( (int) $info['checked'] ) ) );
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="ems-status__actions">
            <form action="" method="POST">
                <?php wp_nonce_field( 'mme_kit_check_updates', '_wp_nonce_check_updates' ); ?>
                <input type="hidden" name="action" value="mme_kit_check_updates">
                <button type="submit" class="ems-btn ems-btn--light">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <?php echo esc_html__( 'Check for updates', 'mme-elementor-kit' ); ?>
                </button>
            </form>
            <?php if ( $has_update ) : ?>
                <a class="ems-btn ems-btn--primary" href="<?php echo esc_url( $updater->get_update_url() ); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php
                    /* translators: %s: version */
                    printf( esc_html__( 'Update to %s', 'mme-elementor-kit' ), esc_html( $latest ) );
                    ?>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <div class="ems-layout">
        <main class="ems-layout__main">
            <div class="ems-card">
                <?php do_action( 'ems_elements_share_updates_notice' ); ?>
                <?php if ( ! empty( $info['error'] ) ) : ?>
                    <div class="ems-alert ems-alert--error">
                        <span class="dashicons dashicons-warning"></span>
                        <?php echo esc_html( $info['error'] ); ?>
                    </div>
                <?php endif; ?>

                <h2 class="ems-card__title"><?php echo esc_html__( 'Changelog', 'mme-elementor-kit' ); ?></h2>
                <div class="ems-changelog">
                    <?php
                    if ( ! empty( $info['changelog'] ) ) {
                        echo wp_kses_post( $info['changelog'] );
                    } else {
                        echo '<p class="ems-card__desc">' . esc_html__( 'No changelog available.', 'mme-elementor-kit' ) . '</p>';
                    }
                    ?>
                </div>
            </div>
        </main>

        <aside class="ems-layout__aside">
            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Update source', 'mme-elementor-kit' ); ?></h2>
                <ul class="ems-meta">
                    <li><span><?php echo esc_html__( 'Repository', 'mme-elementor-kit' ); ?></span><strong><a class="ems-link" href="<?php echo esc_url( $updater->get_repo_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( Ems_Elements_Share\Admin\Ems_Updater::REPO ); ?></a></strong></li>
                    <li><span><?php echo esc_html__( 'Branch', 'mme-elementor-kit' ); ?></span><strong><?php echo esc_html( Ems_Elements_Share\Admin\Ems_Updater::BRANCH ); ?></strong></li>
                </ul>
                <p class="ems-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php echo esc_html__( 'WordPress also checks automatically. You can turn on auto-updates for MMe Elementor Kit on the Plugins page.', 'mme-elementor-kit' ); ?>
                </p>
            </div>

            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Publishing an update', 'mme-elementor-kit' ); ?></h2>
                <ol class="ems-steps">
                    <li><?php echo esc_html__( 'Raise "Version" and EMS_ELEMENTS_SHARE_VERSION in mme-elementor-kit.php.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'Add the changes under "== Changelog ==" in README.txt.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'Push to the main branch. Sites see the update within a few hours, or right away with "Check for updates".', 'mme-elementor-kit' ); ?></li>
                </ol>
            </div>
        </aside>
    </div>
</div>
