<?php
/**
 * Master (server) page: issue secret keys and decide which templates each key can browse.
 *
 * @var Ems_Elements_Share\Admin\Ems_Key_Manager $key_manager
 */

$stats = $key_manager->get_stats();
$usage = $stats['max_sites'] ? min( 100, round( $stats['active_sites'] / $stats['max_sites'] * 100 ) ) : 0;
?>
<div class="wrap ems-app ems-app--master">
    <?php require __DIR__ . '/page-header.php'; ?>

    <section class="ems-stats">
        <div class="ems-stat">
            <span class="ems-stat__icon dashicons dashicons-admin-network"></span>
            <div>
                <span class="ems-stat__value"><?php echo esc_html( $stats['keys'] ); ?></span>
                <span class="ems-stat__label"><?php echo esc_html__( 'Secret keys', 'mme-elementor-kit' ); ?></span>
            </div>
        </div>
        <div class="ems-stat">
            <span class="ems-stat__icon ems-stat__icon--green dashicons dashicons-networking"></span>
            <div>
                <span class="ems-stat__value"><?php echo esc_html( $stats['active_sites'] ); ?><small> / <?php echo esc_html( $stats['max_sites'] ); ?></small></span>
                <span class="ems-stat__label"><?php echo esc_html__( 'Connected client sites', 'mme-elementor-kit' ); ?></span>
                <span class="ems-progress ems-progress--sm"><span style="width: <?php echo esc_attr( $usage ); ?>%"></span></span>
            </div>
        </div>
        <div class="ems-stat">
            <span class="ems-stat__icon ems-stat__icon--violet dashicons dashicons-layout"></span>
            <div>
                <span class="ems-stat__value"><?php echo esc_html( $stats['templates'] ); ?></span>
                <span class="ems-stat__label"><?php echo esc_html__( 'Elementor templates', 'mme-elementor-kit' ); ?></span>
            </div>
        </div>
        <div class="ems-stat">
            <span class="ems-stat__icon ems-stat__icon--amber dashicons dashicons-category"></span>
            <div>
                <span class="ems-stat__value"><?php echo esc_html( $stats['categories'] ); ?></span>
                <span class="ems-stat__label"><?php echo esc_html__( 'Template categories', 'mme-elementor-kit' ); ?></span>
            </div>
        </div>
    </section>

    <div class="ems-layout">
        <main class="ems-layout__main">
            <form action="" method="POST" id="ems-master-form" class="ems-card">
                <div class="ems-card__header">
                    <div>
                        <h2 class="ems-card__title"><?php echo esc_html__( 'Secret keys', 'mme-elementor-kit' ); ?></h2>
                        <p class="ems-card__desc"><?php echo esc_html__( 'Each key decides which templates a client site can browse and how many sites can use it.', 'mme-elementor-kit' ); ?></p>
                    </div>
                    <button type="button" class="ems-btn ems-btn--secondary addnew-key-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php echo esc_html__( 'Add New Key', 'mme-elementor-kit' ); ?>
                    </button>
                </div>

                <?php do_action( 'ems_elements_share_server_form_notice' ); ?>

                <?php $key_manager->all_rules_section(); ?>

                <?php wp_nonce_field( 'save_server_rules', '_wp_nonce_server_rules' ); ?>
                <input type="hidden" name="action" value="save_server_rules">

                <div class="ems-savebar">
                    <span class="ems-savebar__hint"><?php echo esc_html__( 'You have unsaved changes.', 'mme-elementor-kit' ); ?></span>
                    <button class="ems-btn ems-btn--primary save-btn" type="submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo esc_html__( 'Save Changes', 'mme-elementor-kit' ); ?>
                    </button>
                </div>
            </form>
        </main>

        <aside class="ems-layout__aside">
            <?php
            $license_data    = $license->get_status();
            $license_expires = ! empty( $license_data['expires'] ) ? date_i18n( get_option( 'date_format' ), (int) $license_data['expires'] ) : __( 'Never', 'mme-elementor-kit' );
            ?>
            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Master license', 'mme-elementor-kit' ); ?></h2>
                <?php do_action( 'ems_elements_share_license_notice' ); ?>
                <ul class="ems-meta">
                    <li><span><?php echo esc_html__( 'Status', 'mme-elementor-kit' ); ?></span><span class="ems-badge ems-badge--success"><?php echo esc_html__( 'Active', 'mme-elementor-kit' ); ?></span></li>
                    <li><span><?php echo esc_html__( 'Domain', 'mme-elementor-kit' ); ?></span><strong><?php echo esc_html( $license_data['domain'] ); ?></strong></li>
                    <li><span><?php echo esc_html__( 'Expires', 'mme-elementor-kit' ); ?></span><strong><?php echo esc_html( $license_expires ); ?></strong></li>
                    <?php if ( ! empty( $license_data['note'] ) ) : ?>
                        <li><span><?php echo esc_html__( 'Note', 'mme-elementor-kit' ); ?></span><strong><?php echo esc_html( $license_data['note'] ); ?></strong></li>
                    <?php endif; ?>
                </ul>
                <form action="" method="POST" class="ems-deactivate-master-form">
                    <?php wp_nonce_field( 'mme_kit_master_license', '_wp_nonce_master_license' ); ?>
                    <input type="hidden" name="action" value="mme_kit_deactivate_master">
                    <button type="submit" class="ems-btn ems-btn--ghost ems-btn--block ems-field">
                        <span class="dashicons dashicons-lock"></span>
                        <?php echo esc_html__( 'Deactivate Master mode', 'mme-elementor-kit' ); ?>
                    </button>
                </form>
            </div>

            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Master site URL', 'mme-elementor-kit' ); ?></h2>
                <p class="ems-card__desc"><?php echo esc_html__( 'Give this URL and a secret key to the client site.', 'mme-elementor-kit' ); ?></p>
                <div class="ems-input-group">
                    <input type="text" class="ems-input" id="ems-master-url" value="<?php echo esc_url( home_url( '/' ) ); ?>" readonly>
                    <button type="button" class="ems-btn ems-btn--ghost" data-ems-copy="#ems-master-url">
                        <span class="dashicons dashicons-admin-page"></span>
                        <span class="ems-copy-label"><?php echo esc_html__( 'Copy', 'mme-elementor-kit' ); ?></span>
                    </button>
                </div>
            </div>

            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Backup & migrate', 'mme-elementor-kit' ); ?></h2>
                <p class="ems-card__desc"><?php echo esc_html__( 'Export the keys to a file, then import it on the new master site. Templates and categories are matched by slug or title.', 'mme-elementor-kit' ); ?></p>
                <a class="ems-btn ems-btn--ghost ems-btn--block ems-field" href="<?php echo esc_url( $keys_transfer->get_export_url() ); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php echo esc_html__( 'Export keys', 'mme-elementor-kit' ); ?>
                </a>

                <form action="" method="POST" enctype="multipart/form-data" class="ems-import-form">
                    <div class="ems-field">
                        <label class="ems-label" for="ems-import-file"><?php echo esc_html__( 'Import file (.json)', 'mme-elementor-kit' ); ?></label>
                        <input class="ems-file" id="ems-import-file" type="file" name="mme_kit_import_file" accept=".json,application/json" required>
                    </div>
                    <div class="ems-field ems-radio-group">
                        <label><input type="radio" name="mme_kit_import_mode" value="merge" checked> <?php echo esc_html__( 'Merge with existing keys', 'mme-elementor-kit' ); ?></label>
                        <label><input type="radio" name="mme_kit_import_mode" value="replace"> <?php echo esc_html__( 'Replace all keys', 'mme-elementor-kit' ); ?></label>
                    </div>
                    <?php wp_nonce_field( 'mme_kit_import_keys', '_wp_nonce_import_keys' ); ?>
                    <input type="hidden" name="action" value="mme_kit_import_keys">
                    <button type="submit" class="ems-btn ems-btn--secondary ems-btn--block ems-field">
                        <span class="dashicons dashicons-upload"></span>
                        <?php echo esc_html__( 'Import keys', 'mme-elementor-kit' ); ?>
                    </button>
                </form>
            </div>

            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'How it works', 'mme-elementor-kit' ); ?></h2>
                <ol class="ems-steps">
                    <li><?php echo esc_html__( 'Create a key and choose which templates or categories it shares.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'Set how many client sites may connect with that key, then save.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'On the client site, open MMe Kit → Client and paste the URL and key.', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'The client inserts templates from the MMe Library tab of the Elementor library.', 'mme-elementor-kit' ); ?></li>
                </ol>
                <a class="ems-link" href="<?php echo esc_url( admin_url( 'edit.php?post_type=elementor_library' ) ); ?>">
                    <?php echo esc_html__( 'Manage Elementor templates', 'mme-elementor-kit' ); ?> →
                </a>
            </div>
        </aside>
    </div>
</div>
