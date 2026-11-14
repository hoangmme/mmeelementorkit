<?php
/**
 * MCP page: connect an AI client (Claude Code, ...) to this site through the
 * MCP Adapter plugin, and the MMe Kit error log.
 */

use Ems_Elements_Share\Mcp\Ems_Debug_Log;
use Ems_Elements_Share\Mcp\Ems_Mcp_Abilities;

$abilities   = Ems_Mcp_Abilities::get_instance();
$log         = Ems_Debug_Log::get_instance();
$user        = wp_get_current_user();
$server_name = 'wp-' . trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ), '-' );

// MCP Adapter: its own plugin, or the copy bundled in Elementor 4.x. What counts is whether the endpoint answers.
$adapter        = Ems_Mcp_Abilities::adapter_state();
$adapter_notice = Ems_Mcp_Abilities::adapter_notice();
if ( $adapter['active'] ) {
    /* translators: %s: plugin version */
    $adapter_value = sprintf( __( 'Plugin active (v%s)', 'mme-elementor-kit' ), $adapter['version'] );
} elseif ( $adapter['bundled_by'] && $adapter['endpoint'] ) {
    /* translators: %s: plugin that bundles the MCP Adapter, e.g. "Elementor 4.3.5" */
    $adapter_value = sprintf( __( 'Built into %s', 'mme-elementor-kit' ), $adapter['bundled_by'] );
} elseif ( $adapter['installed'] ) {
    $adapter_value = __( 'Installed, not active', 'mme-elementor-kit' );
} else {
    $adapter_value = __( 'Not installed', 'mme-elementor-kit' );
}
$adapter_button = '';
if ( ! $adapter['active'] && current_user_can( $adapter['installed'] ? 'activate_plugins' : 'install_plugins' ) ) {
    $adapter_button = $adapter['installed'] ? __( 'Activate plugin', 'mme-elementor-kit' ) : __( 'Install & activate plugin', 'mme-elementor-kit' );
}

$requirements = array(
    array(
        'label' => __( 'WordPress 6.9+ (Abilities API)', 'mme-elementor-kit' ),
        'ok'    => Ems_Mcp_Abilities::abilities_api_available(),
        'value' => get_bloginfo( 'version' ),
    ),
    array(
        'label' => __( 'PHP 7.4+ (MCP Adapter)', 'mme-elementor-kit' ),
        'ok'    => version_compare( PHP_VERSION, '7.4', '>=' ),
        'value' => PHP_VERSION,
    ),
    'adapter' => array(
        'label' => __( 'MCP Adapter', 'mme-elementor-kit' ),
        'ok'    => $adapter['endpoint'],
        'value' => $adapter_value,
    ),
    array(
        'label' => __( 'Application passwords', 'mme-elementor-kit' ),
        'ok'    => function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available(),
        'value' => function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available() ? __( 'Available', 'mme-elementor-kit' ) : __( 'Off (needs HTTPS)', 'mme-elementor-kit' ),
    ),
);
$ready = ! in_array( false, wp_list_pluck( $requirements, 'ok' ), true );

// {auth} is filled in the browser from the application password (base64 of "login:password"); nothing is sent to the server.
$command = sprintf(
    'claude mcp add --transport http --scope user %1$s "%2$s" --header "Authorization: Basic {auth}"',
    $server_name,
    Ems_Mcp_Abilities::endpoint_url()
);

$entries       = $log->read( array( 'limit' => 15 ) );
$capture_until = $log->capture_until();
?>
<div class="wrap ems-app ems-app--mcp">
    <?php require __DIR__ . '/page-header.php'; ?>

    <section class="ems-status <?php echo $ready ? 'is-connected' : 'is-disconnected'; ?>">
        <span class="ems-status__icon dashicons <?php echo $ready ? 'dashicons-yes-alt' : 'dashicons-admin-plugins'; ?>"></span>
        <div class="ems-status__body">
            <span class="ems-status__label"><?php echo $ready ? esc_html__( 'Ready', 'mme-elementor-kit' ) : esc_html__( 'Setup needed', 'mme-elementor-kit' ); ?></span>
            <h2 class="ems-status__title"><?php echo esc_html__( 'Let an AI assistant check and update this site', 'mme-elementor-kit' ); ?></h2>
            <span class="ems-status__meta"><?php echo esc_html__( 'MCP connection for Claude Code and other MCP clients. It acts as the WordPress user whose application password it uses.', 'mme-elementor-kit' ); ?></span>
        </div>
    </section>

    <div class="ems-layout">
        <main class="ems-layout__main">
            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'Connect', 'mme-elementor-kit' ); ?></h2>
                <ol class="ems-steps">
                    <li>
                        <?php echo esc_html__( 'Check the requirements below. The connection uses the official "MCP Adapter" by WordPress.org: its own plugin, or the copy built into Elementor 4.x.', 'mme-elementor-kit' ); ?>
                        <?php if ( $adapter_notice ) : ?>
                            <div class="ems-alert ems-alert--<?php echo esc_attr( $adapter_notice[0] ); ?> ems-field">
                                <span class="dashicons <?php echo 'success' === $adapter_notice[0] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <?php echo esc_html( $adapter_notice[1] ); ?>
                            </div>
                        <?php endif; ?>
                        <ul class="ems-meta ems-field">
                            <?php foreach ( $requirements as $key => $item ) : ?>
                                <li>
                                    <span>
                                        <span class="dashicons <?php echo $item['ok'] ? 'dashicons-yes-alt ems-text-green' : 'dashicons-warning ems-text-muted'; ?>"></span>
                                        <?php echo esc_html( $item['label'] ); ?>
                                    </span>
                                    <strong><?php echo esc_html( $item['value'] ); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ( $adapter_button ) : ?>
                            <form method="POST" action="" class="ems-form-actions ems-field">
                                <?php wp_nonce_field( 'mme_kit_mcp_adapter', '_wp_nonce_mcp_adapter' ); ?>
                                <input type="hidden" name="action" value="mme_kit_mcp_adapter">
                                <button type="submit" class="ems-btn <?php echo $adapter['endpoint'] ? 'ems-btn--secondary' : 'ems-btn--primary'; ?>">
                                    <span class="dashicons dashicons-admin-plugins"></span>
                                    <?php echo esc_html( $adapter_button ); ?>
                                </button>
                                <?php if ( ! $adapter['installed'] ) : ?>
                                    <a class="ems-link" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . Ems_Mcp_Abilities::ADAPTER_SLUG ), 'install-plugin_' . Ems_Mcp_Abilities::ADAPTER_SLUG ) ); ?>"><?php echo esc_html__( 'Install from Plugins ↗', 'mme-elementor-kit' ); ?></a>
                                <?php endif; ?>
                            </form>
                            <?php if ( $adapter['endpoint'] && $adapter['bundled_by'] ) : ?>
                                <p class="ems-note">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <?php
                                    /* translators: %s: plugin that bundles the MCP Adapter */
                                    echo esc_html( sprintf( __( 'Optional: MCP already works through %s. The separate plugin keeps the connection working if that plugin is turned off or stops bundling it.', 'mme-elementor-kit' ), $adapter['bundled_by'] ) );
                                    ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php echo esc_html__( 'Create an application password for an administrator (a separate admin account just for the AI is safest).', 'mme-elementor-kit' ); ?>
                        <a class="ems-link" href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"><?php echo esc_html__( 'Profile → Application Passwords ↗', 'mme-elementor-kit' ); ?></a>
                        <span class="ems-hint ems-field"><?php echo esc_html__( 'The password is shown once, in a green box above the list. If nothing appears after clicking "Add New Application Password", a plugin that hides admin notices is hiding it: turn that off for a moment (MMe Core 1.1.1+ no longer hides it).', 'mme-elementor-kit' ); ?></span>
                    </li>
                    <li>
                        <?php echo esc_html__( 'Paste the application password here to build the command (it stays in this browser), then run the command in a terminal on your computer:', 'mme-elementor-kit' ); ?>
                        <div class="ems-field">
                            <input type="password" class="ems-input ems-input--mono" id="ems-mcp-password" autocomplete="off" spellcheck="false" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" data-login="<?php echo esc_attr( $user->user_login ); ?>">
                        </div>
                        <div class="ems-input-group ems-field">
                            <textarea class="ems-input ems-input--mono" id="ems-mcp-command" rows="4" readonly data-template="<?php echo esc_attr( $command ); ?>"><?php echo esc_textarea( str_replace( '{auth}', '<' . __( 'paste the application password above', 'mme-elementor-kit' ) . '>', $command ) ); ?></textarea>
                            <button type="button" class="ems-btn ems-btn--ghost" data-ems-copy="#ems-mcp-command">
                                <span class="dashicons dashicons-admin-page"></span>
                                <span class="ems-copy-label"><?php echo esc_html__( 'Copy', 'mme-elementor-kit' ); ?></span>
                            </button>
                        </div>
                    </li>
                    <li><?php
                        /* translators: %s: application password user */
                        printf( esc_html__( 'Restart Claude Code and ask it to run "mme-kit/site-info" to test the connection. The command uses your login (%s); for another account, open this page as that account.', 'mme-elementor-kit' ), '<code>' . esc_html( $user->user_login ) . '</code>' );
                    ?></li>
                </ol>
                <p class="ems-note">
                    <span class="dashicons dashicons-shield"></span>
                    <?php echo esc_html__( 'Anyone holding the application password can do what that user can. Revoke it from the profile page at any time; the AI loses access immediately.', 'mme-elementor-kit' ); ?>
                </p>
            </div>

            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'MMe Kit tools available to the AI', 'mme-elementor-kit' ); ?></h2>
                <ul class="ems-meta">
                    <?php foreach ( $abilities->get_definitions() as $name => $definition ) : ?>
                        <li>
                            <span><code><?php echo esc_html( $name ); ?></code></span>
                            <strong title="<?php echo esc_attr( $definition['description'] ); ?>"><?php echo esc_html( $definition['label'] ); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="ems-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php echo esc_html__( 'The MCP Adapter also lists the abilities of WordPress core and other plugins. MMe Kit tools only read data, except capture mode, clearing the Elementor cache, editing an element (update-element, which keeps a revision) and installing MMe Kit updates.', 'mme-elementor-kit' ); ?>
                </p>
            </div>
        </main>

        <aside class="ems-layout__aside">
            <div class="ems-card">
                <?php do_action( 'ems_elements_share_mcp_notice' ); ?>
                <h2 class="ems-card__title"><?php echo esc_html__( 'Error log', 'mme-elementor-kit' ); ?></h2>
                <p class="ems-card__desc">
                    <?php
                    if ( $capture_until ) {
                        /* translators: %s: time left */
                        printf( esc_html__( 'Capture mode on for %s more: warnings, notices and Elementor editor JavaScript errors are recorded.', 'mme-elementor-kit' ), esc_html( human_time_diff( $capture_until ) ) );
                    } else {
                        echo esc_html__( 'Fatal errors are always recorded. Turn on capture mode while reproducing a problem to also record warnings and editor JavaScript errors. No wp-config.php change needed.', 'mme-elementor-kit' );
                    }
                    ?>
                </p>
                <form action="" method="POST" class="ems-form-actions">
                    <?php wp_nonce_field( 'mme_kit_debug_log', '_wp_nonce_debug_log' ); ?>
                    <input type="hidden" name="action" value="mme_kit_debug_log">
                    <?php if ( $capture_until ) : ?>
                        <button type="submit" name="do" value="stop" class="ems-btn ems-btn--secondary"><?php echo esc_html__( 'Stop capture', 'mme-elementor-kit' ); ?></button>
                    <?php else : ?>
                        <button type="submit" name="do" value="start" class="ems-btn ems-btn--primary">
                            <span class="dashicons dashicons-controls-record"></span>
                            <?php echo esc_html__( 'Capture for 60 min', 'mme-elementor-kit' ); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ( $entries ) : ?>
                        <button type="submit" name="do" value="clear" class="ems-btn ems-btn--ghost"><?php echo esc_html__( 'Clear', 'mme-elementor-kit' ); ?></button>
                    <?php endif; ?>
                </form>

                <?php if ( $entries ) : ?>
                    <ul class="ems-log">
                        <?php foreach ( $entries as $entry ) : ?>
                            <li class="ems-log__item">
                                <span class="ems-log__meta">
                                    <span class="ems-badge"><?php echo esc_html( strtoupper( $entry['source'] ) . ' · ' . $entry['type'] ); ?></span>
                                    <?php
                                    /* translators: %s: time ago */
                                    printf( esc_html__( '%s ago', 'mme-elementor-kit' ), esc_html( human_time_diff( strtotime( $entry['time'] ) ) ) );
                                    ?>
                                </span>
                                <span class="ems-log__message"><?php echo esc_html( wp_trim_words( $entry['message'], 40 ) ); ?></span>
                                <?php if ( ! empty( $entry['file'] ) ) : ?>
                                    <code class="ems-log__file"><?php echo esc_html( str_replace( ABSPATH, '', $entry['file'] ) . ':' . $entry['line'] ); ?></code>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="ems-note">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html__( 'No errors recorded.', 'mme-elementor-kit' ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
