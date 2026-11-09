<?php
/**
 * Client (receiver) page: connect to a master site and preview what it shares.
 *
 * @var Ems_Elements_Share\Admin\Ems_Receiver_Manager $receiver_manager
 */

$url       = $receiver_manager->get_server_url();
$key       = $receiver_manager->get_server_key();
$connected = $receiver_manager->is_connected();
$host      = $url ? wp_parse_url( $url, PHP_URL_HOST ) : '';
?>
<div class="wrap ems-app ems-app--client">
    <?php require __DIR__ . '/page-header.php'; ?>

    <section class="ems-status <?php echo $connected ? 'is-connected' : 'is-disconnected'; ?>">
        <span class="ems-status__icon dashicons <?php echo $connected ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
        <div class="ems-status__body">
            <span class="ems-status__label">
                <?php echo $connected ? esc_html__( 'Connected', 'mme-elementor-kit' ) : esc_html__( 'Not connected', 'mme-elementor-kit' ); ?>
            </span>
            <h2 class="ems-status__title">
                <?php
                if ( $connected ) {
                    /* translators: %s: master site host */
                    printf( esc_html__( 'Receiving templates from %s', 'mme-elementor-kit' ), '<strong>' . esc_html( $host ) . '</strong>' );
                } else {
                    echo esc_html__( 'Connect this site to a master site to browse its templates.', 'mme-elementor-kit' );
                }
                ?>
            </h2>
        </div>
        <?php if ( $connected ) : ?>
            <button type="button" class="ems-btn ems-btn--light ems-refresh-templates">
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__( 'Test connection', 'mme-elementor-kit' ); ?>
            </button>
        <?php endif; ?>
    </section>

    <div class="ems-layout">
        <main class="ems-layout__main">
            <form action="" method="POST" class="ems-card">
                <div class="ems-card__header">
                    <div>
                        <h2 class="ems-card__title"><?php echo esc_html__( 'Master site connection', 'mme-elementor-kit' ); ?></h2>
                        <p class="ems-card__desc"><?php echo esc_html__( 'Ask the master site owner for its URL and a secret key.', 'mme-elementor-kit' ); ?></p>
                    </div>
                </div>

                <?php do_action( 'ems_elements_share_receiver_form_notice' ); ?>

                <div class="ems-field">
                    <label class="ems-label" for="ems-server-url"><?php echo esc_html__( 'Website URL', 'mme-elementor-kit' ); ?></label>
                    <input class="ems-input" id="ems-server-url" type="url" placeholder="https://master-site.com" name="ems_elements_share_server_url" value="<?php echo esc_url( $url ); ?>" <?php echo $connected ? 'readonly' : 'required'; ?>>
                </div>
                <div class="ems-field">
                    <label class="ems-label" for="ems-server-key"><?php echo esc_html__( 'Secret Key', 'mme-elementor-kit' ); ?></label>
                    <div class="ems-input-group">
                        <input class="ems-input ems-input--mono" id="ems-server-key" type="<?php echo $connected ? 'password' : 'text'; ?>" name="ems_elements_share_server_key" value="<?php echo esc_attr( $key ); ?>" <?php echo $connected ? 'readonly' : 'required'; ?>>
                        <?php if ( $connected ) : ?>
                            <button type="button" class="ems-btn ems-btn--ghost ems-toggle-secret" data-target="#ems-server-key" title="<?php echo esc_attr__( 'Show / hide key', 'mme-elementor-kit' ); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php wp_nonce_field( 'save_receiver_rules', '_wp_nonce_receiver_rules' ); ?>

                <div class="ems-form-actions">
                    <?php if ( $connected ) : ?>
                        <input type="hidden" name="action" value="disconnect_receiver_rules">
                        <button class="ems-btn ems-btn--danger" type="submit">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php echo esc_html__( 'Disconnect', 'mme-elementor-kit' ); ?>
                        </button>
                    <?php else : ?>
                        <input type="hidden" name="action" value="save_receiver_rules">
                        <button class="ems-btn ems-btn--primary" type="submit">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php echo esc_html__( 'Connect', 'mme-elementor-kit' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ( $connected ) : ?>
                <div class="ems-card">
                    <div class="ems-card__header">
                        <div>
                            <h2 class="ems-card__title"><?php echo esc_html__( 'Shared templates', 'mme-elementor-kit' ); ?></h2>
                            <p class="ems-card__desc ems-remote-summary"><?php echo esc_html__( 'Templates the master site shares with this key.', 'mme-elementor-kit' ); ?></p>
                        </div>
                    </div>
                    <div id="ems-remote-templates" class="ems-template-grid" aria-live="polite"></div>
                </div>
            <?php endif; ?>
        </main>

        <aside class="ems-layout__aside">
            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Using shared templates', 'mme-elementor-kit' ); ?></h2>
                <ol class="ems-steps">
                    <li><?php echo esc_html__( 'Open any page with Elementor.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'Click the folder icon to open the template library.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'Switch to the MMe Library tab and insert a template.', 'mme-elementor-kit' ); ?></li>
                </ol>
                <p class="ems-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php echo esc_html__( 'Templates that use MMe widgets (e.g. Info Table) need this plugin active on the client site as well.', 'mme-elementor-kit' ); ?>
                </p>
            </div>
        </aside>
    </div>
</div>
