<?php
/**
 * MCP, AI & Logs page: MMe API key and the AI layout generator.
 * The AI runs on the MMe service; this site only holds the MMe API key.
 */

use Ems_Elements_Share\Ai\Ems_Ai_Client;
use Ems_Elements_Share\Ai\Ems_Ai_Layout;

$ai_data    = Ems_Ai_Client::data();
$ai_has_key = Ems_Ai_Client::has_key();
$ai_legacy  = Ems_Ai_Client::has_legacy_key();
$ai_usage   = isset( $ai_data['usage'] ) && is_array( $ai_data['usage'] ) ? $ai_data['usage'] : array();
$ai_ready   = $ai_has_key && '' !== Ems_Ai_Client::get_key();
$ai_notice  = Ems_Ai_Layout::key_notice();
$ai_status  = isset( $ai_data['status'] ) ? $ai_data['status'] : '';

?>
<div class="ems-card" id="mme-ai">
    <h2 class="ems-card__title"><?php echo esc_html__( 'AI layout generator', 'mme-elementor-kit' ); ?></h2>
    <p class="ems-card__desc"><?php echo esc_html__( 'Connect this site to MMe AI, then create sections inside the Elementor editor with the "MMe AI" button: editable Elementor containers using your global colors, or a free-design HTML block. The AI uses your business information from MMe; images are placeholders to replace.', 'mme-elementor-kit' ); ?></p>

    <?php if ( $ai_legacy ) : ?>
        <div class="ems-alert ems-alert--warning ems-field">
            <span class="dashicons dashicons-info"></span>
            <?php echo esc_html__( 'AI now runs through your MMe account: the AI key saved on this site before is no longer used. Paste the MMe API key below to replace it.', 'mme-elementor-kit' ); ?>
        </div>
    <?php endif; ?>

    <?php if ( $ai_notice ) : ?>
        <div class="ems-alert ems-alert--<?php echo esc_attr( $ai_notice[0] ); ?> ems-field">
            <span class="dashicons <?php echo 'success' === $ai_notice[0] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
            <?php echo esc_html( $ai_notice[1] ); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="ems-ai-key ems-field">
        <?php wp_nonce_field( 'mme_kit_ai_key', '_wp_nonce_ai_key' ); ?>
        <input type="hidden" name="action" value="mme_kit_ai_key">

        <?php if ( $ai_has_key ) : ?>
            <ul class="ems-meta">
                <li>
                    <span>
                        <span class="dashicons <?php echo $ai_ready && in_array( $ai_status, array( '', 'ok' ), true ) ? 'dashicons-yes-alt ems-text-green' : 'dashicons-warning ems-text-muted'; ?>"></span>
                        <?php echo esc_html__( 'MMe API key', 'mme-elementor-kit' ); ?>
                    </span>
                    <strong>
                        <code>••••<?php echo esc_html( Ems_Ai_Client::last4() ); ?></code>
                        <?php
                        if ( ! $ai_ready ) {
                            echo ' · ' . esc_html__( 'unreadable, enter it again', 'mme-elementor-kit' );
                        } elseif ( 'ok' === $ai_status ) {
                            echo ' · ' . esc_html__( 'Connected', 'mme-elementor-kit' );
                            if ( ! empty( $ai_data['account'] ) ) {
                                echo ' · ' . esc_html( $ai_data['account'] );
                            }
                            if ( isset( $ai_usage['quota'] ) ) {
                                /* translators: 1: pages generated this month, 2: monthly limit */
                                echo ' · ' . esc_html( sprintf( __( '%1$d / %2$d pages this month', 'mme-elementor-kit' ), $ai_usage['used'], $ai_usage['quota'] ) );
                            }
                        } elseif ( $ai_status ) {
                            echo ' · ' . esc_html__( 'check failed', 'mme-elementor-kit' );
                        }
                        ?>
                    </strong>
                </li>
            </ul>
            <div class="ems-form-actions">
                <button type="submit" name="do" value="test" class="ems-btn ems-btn--secondary"><?php echo esc_html__( 'Test', 'mme-elementor-kit' ); ?></button>
                <button type="button" class="ems-btn ems-btn--ghost" data-ems-ai-replace><?php echo esc_html__( 'Replace key', 'mme-elementor-kit' ); ?></button>
                <button type="submit" name="do" value="remove" class="ems-btn ems-btn--ghost" onclick="return confirm('<?php echo esc_js( __( 'Remove the MMe API key from this site?', 'mme-elementor-kit' ) ); ?>');"><?php echo esc_html__( 'Remove', 'mme-elementor-kit' ); ?></button>
            </div>
        <?php endif; ?>

        <div class="ems-ai-key__new"<?php echo $ai_has_key && $ai_ready ? ' hidden' : ''; ?>>
            <div class="ems-input-group ems-field">
                <input type="password" name="ai_key" class="ems-input ems-input--mono" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr__( 'Paste the MMe API key (mme_…)', 'mme-elementor-kit' ); ?>">
                <button type="submit" name="do" value="save" class="ems-btn ems-btn--primary"><?php echo esc_html__( 'Save key', 'mme-elementor-kit' ); ?></button>
            </div>
            <span class="ems-hint ems-field"><?php echo esc_html__( 'MMe gives you this key. It is encrypted on this site with its security keys, never shown again and never sent to the browser. No AI account of your own is needed; usage counts against your MMe monthly limit.', 'mme-elementor-kit' ); ?></span>
        </div>
    </form>

    <?php if ( $ai_ready ) : ?>
        <div class="ems-alert ems-alert--success ems-field">
            <span class="dashicons dashicons-admin-customizer"></span>
            <?php echo esc_html__( 'Ready. Open a page with Elementor and click the purple "MMe AI" button in any "add section" area: choose Elementor or HTML, describe the section, and it is added right there (Ctrl+Z to undo).', 'mme-elementor-kit' ); ?>
        </div>
    <?php endif; ?>
</div>
