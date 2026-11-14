<?php
/**
 * MCP, AI & Logs page: AI API key and the AI layout generator.
 * The AI service is never named here.
 */

use Ems_Elements_Share\Ai\Ems_Ai_Client;
use Ems_Elements_Share\Ai\Ems_Ai_Layout;

$ai_data    = Ems_Ai_Client::data();
$ai_has_key = Ems_Ai_Client::has_key();
$ai_ready   = $ai_has_key && '' !== Ems_Ai_Client::get_key();
$ai_notice  = Ems_Ai_Layout::key_notice();
$ai_status  = isset( $ai_data['status'] ) ? $ai_data['status'] : '';

$ai_pages = get_posts( array(
    'post_type'      => array( 'page', 'post' ),
    'post_status'    => array( 'publish', 'draft', 'private' ),
    'posts_per_page' => 100,
    'orderby'        => 'modified',
    'order'          => 'DESC',
    'meta_key'       => '_elementor_edit_mode', // phpcs:ignore WordPress.DB.SlowDBQuery
    'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery
) );
?>
<div class="ems-card" id="mme-ai">
    <h2 class="ems-card__title"><?php echo esc_html__( 'AI layout generator', 'mme-elementor-kit' ); ?></h2>
    <p class="ems-card__desc"><?php echo esc_html__( 'Describe a section or a page; the AI builds it with Elementor containers and core widgets, using your global colors. Images are placeholders to replace.', 'mme-elementor-kit' ); ?></p>

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
                        <?php echo esc_html__( 'AI API key', 'mme-elementor-kit' ); ?>
                    </span>
                    <strong>
                        <code>••••<?php echo esc_html( Ems_Ai_Client::last4() ); ?></code>
                        <?php
                        if ( ! $ai_ready ) {
                            echo ' · ' . esc_html__( 'unreadable, enter it again', 'mme-elementor-kit' );
                        } elseif ( 'ok' === $ai_status ) {
                            echo ' · ' . esc_html__( 'Connected', 'mme-elementor-kit' );
                            if ( ! empty( $ai_data['balance'] ) ) {
                                /* translators: %s: account balance */
                                echo ' · ' . esc_html( sprintf( __( 'balance %s', 'mme-elementor-kit' ), $ai_data['balance'] ) );
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
                <button type="submit" name="do" value="remove" class="ems-btn ems-btn--ghost" onclick="return confirm('<?php echo esc_js( __( 'Remove the AI API key from this site?', 'mme-elementor-kit' ) ); ?>');"><?php echo esc_html__( 'Remove', 'mme-elementor-kit' ); ?></button>
            </div>
        <?php endif; ?>

        <div class="ems-ai-key__new"<?php echo $ai_has_key && $ai_ready ? ' hidden' : ''; ?>>
            <div class="ems-input-group ems-field">
                <input type="password" name="ai_key" class="ems-input ems-input--mono" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr__( 'Paste the AI API key', 'mme-elementor-kit' ); ?>">
                <button type="submit" name="do" value="save" class="ems-btn ems-btn--primary"><?php echo esc_html__( 'Save key', 'mme-elementor-kit' ); ?></button>
            </div>
            <span class="ems-hint ems-field"><?php echo esc_html__( 'The key is encrypted on this site with its security keys, never shown again and never sent to the browser. Use a key just for this site, with a spending limit in your AI account.', 'mme-elementor-kit' ); ?></span>
        </div>
    </form>

    <?php if ( $ai_ready ) : ?>
        <form class="ems-ai-generate ems-field" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mme_kit_ai_generate' ) ); ?>">
            <label class="ems-label" for="ems-ai-brief"><?php echo esc_html__( 'What should it build?', 'mme-elementor-kit' ); ?></label>
            <textarea id="ems-ai-brief" class="ems-input" rows="5" maxlength="4000" placeholder="<?php echo esc_attr__( 'e.g. Landing page for an accounting service for small businesses in Hanoi: monthly bookkeeping, tax filing, payroll. Friendly, trustworthy. Button to book a free consultation.', 'mme-elementor-kit' ); ?>"></textarea>

            <div class="ems-ai-generate__row ems-field">
                <label>
                    <span class="ems-label"><?php echo esc_html__( 'Type', 'mme-elementor-kit' ); ?></span>
                    <select class="ems-input" name="kind">
                        <?php foreach ( Ems_Ai_Layout::kinds() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="ems-label"><?php echo esc_html__( 'Save to', 'mme-elementor-kit' ); ?></span>
                    <select class="ems-input" name="target">
                        <option value="0"><?php echo esc_html__( 'New template (Templates → Saved Templates)', 'mme-elementor-kit' ); ?></option>
                        <?php foreach ( $ai_pages as $ai_page ) : ?>
                            <option value="<?php echo esc_attr( $ai_page->ID ); ?>">
                                <?php
                                /* translators: %s: page title */
                                echo esc_html( sprintf( __( 'End of page: %s', 'mme-elementor-kit' ), $ai_page->post_title ? $ai_page->post_title : '#' . $ai_page->ID ) );
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="ems-form-actions">
                <button type="submit" class="ems-btn ems-btn--primary">
                    <span class="dashicons dashicons-admin-customizer"></span>
                    <?php echo esc_html__( 'Generate', 'mme-elementor-kit' ); ?>
                </button>
                <span class="ems-ai-status ems-hint" aria-live="polite"></span>
            </div>
            <div class="ems-ai-result" hidden></div>
            <span class="ems-hint ems-field"><?php echo esc_html__( 'Takes 20–90 seconds. Appending to a page keeps a revision, so it can be undone from Elementor History.', 'mme-elementor-kit' ); ?></span>
        </form>
    <?php endif; ?>
</div>
