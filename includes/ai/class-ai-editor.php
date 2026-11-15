<?php
namespace Ems_Elements_Share\Ai;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * "MMe AI" inside the Elementor editor.
 *
 * A button next to "MMe Templates" in each "add section" area opens a dialog:
 * pick Elementor (editable containers + widgets) or HTML (one free-form HTML
 * widget), describe the section, and the result is inserted at that spot as an
 * unsaved change (one Undo step). Nothing is written to the database here.
 *
 * Generation is two short requests instead of one long one, so proxies with a
 * ~100 s limit are not a problem: mme_kit_ai_start starts a job on the MMe AI
 * service, mme_kit_ai_poll is called every few seconds until it is done and
 * then builds the Elementor elements on this site (with its global colors).
 */
class Ems_Ai_Editor {

    const NONCE = 'mme_kit_ai_editor';

    private static $instance = null;

    public function __construct() {
        add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor' ) );
        add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview' ) );
        add_action( 'wp_ajax_mme_kit_ai_start', array( $this, 'ajax_start' ) );
        add_action( 'wp_ajax_mme_kit_ai_poll', array( $this, 'ajax_poll' ) );
    }

    /**
     * The button is offered only when this site has an MMe key and the user can edit.
     */
    private function available() {
        return Ems_Ai_Client::has_key() && current_user_can( 'edit_posts' );
    }

    private function asset_url( $path ) {
        return EMS_ELEMENTS_SHARE_URL . $path;
    }

    public function enqueue_editor() {
        if ( ! $this->available() ) {
            return;
        }
        $version = defined( 'EMS_ELEMENTS_SHARE_VERSION' ) ? EMS_ELEMENTS_SHARE_VERSION : '1';
        wp_enqueue_style( 'mme-kit-ai-editor', $this->asset_url( 'admin/css/mme-ai-editor.css' ), array(), $version );
        wp_enqueue_script( 'mme-kit-ai-editor', $this->asset_url( 'admin/js/mme-ai-editor.js' ), array( 'jquery', 'underscore', 'backbone' ), $version, true );

        $data  = Ems_Ai_Client::data();
        $usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();

        wp_localize_script( 'mme-kit-ai-editor', 'MmeKitAi', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( self::NONCE ),
            'postId'      => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
            'icon'        => $this->asset_url( 'admin/img/mme-ai-icon.svg' ),
            'logo'        => $this->asset_url( 'admin/img/mme-logo.webp' ),
            'settingsUrl' => admin_url( 'admin.php?page=mme-elementor-kit-mcp#mme-ai' ),
            'canHtml'     => current_user_can( 'unfiltered_html' ),
            'usage'       => $usage,
            'kinds'       => Ems_Ai_Layout::kinds(),
            'i18n'        => array(
                'button'        => __( 'MMe AI', 'mme-elementor-kit' ),
                'title'         => __( 'MMe AI', 'mme-elementor-kit' ),
                'close'         => __( 'Close', 'mme-elementor-kit' ),
                'modeElementor' => __( 'Elementor', 'mme-elementor-kit' ),
                'modeElementorHint' => __( 'Containers and widgets you can edit one by one.', 'mme-elementor-kit' ),
                'modeHtml'      => __( 'HTML', 'mme-elementor-kit' ),
                'modeHtmlHint'  => __( 'Free design in one HTML block; edit it as code.', 'mme-elementor-kit' ),
                'kind'          => __( 'What to build', 'mme-elementor-kit' ),
                'brief'         => __( 'Describe it', 'mme-elementor-kit' ),
                'briefPlaceholder' => __( 'e.g. Services section for a spa in District 1: massage, facial, body care. Warm and relaxing. Button to book an appointment.', 'mme-elementor-kit' ),
                'examples'      => array(
                    __( 'Landing page for our main service with pricing and FAQ', 'mme-elementor-kit' ),
                    __( 'Hero banner with a short promise and a "Book now" button', 'mme-elementor-kit' ),
                    __( '3-step process from first contact to result', 'mme-elementor-kit' ),
                    __( 'Why choose us: 6 benefits with icons', 'mme-elementor-kit' ),
                ),
                'useKnowledge'  => __( 'Use our business information from MMe (services, prices, contact)', 'mme-elementor-kit' ),
                'generate'      => __( 'Generate', 'mme-elementor-kit' ),
                'regenerate'    => __( 'Try again', 'mme-elementor-kit' ),
                'insert'        => __( 'Insert', 'mme-elementor-kit' ),
                'starting'      => __( 'Starting…', 'mme-elementor-kit' ),
                /* translators: %d: seconds */
                'writing'       => __( 'Writing… %ds (usually 20–90 s)', 'mme-elementor-kit' ),
                'building'      => __( 'Adding to the page…', 'mme-elementor-kit' ),
                'tooShort'      => __( 'Describe it in a sentence or more.', 'mme-elementor-kit' ),
                'failed'        => __( 'Something went wrong. Try again.', 'mme-elementor-kit' ),
                'timeout'       => __( 'This is taking too long. Try again with a shorter description.', 'mme-elementor-kit' ),
                /* translators: 1: number of sections, 2: title */
                'inserted'      => __( 'Added %1$d section(s): %2$s. Press Ctrl+Z to undo.', 'mme-elementor-kit' ),
                'previewHint'   => __( 'Preview. Insert it, or try again for another design.', 'mme-elementor-kit' ),
                /* translators: 1: used, 2: monthly limit */
                'usage'         => __( '%1$d / %2$d this month', 'mme-elementor-kit' ),
                'settings'      => __( 'MMe Kit settings', 'mme-elementor-kit' ),
            ),
        ) );
    }

    public function enqueue_preview() {
        if ( ! $this->available() ) {
            return;
        }
        $version = defined( 'EMS_ELEMENTS_SHARE_VERSION' ) ? EMS_ELEMENTS_SHARE_VERSION : '1';
        wp_enqueue_style( 'mme-kit-ai-preview', $this->asset_url( 'admin/css/mme-ai-preview.css' ), array(), $version );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX                                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Common checks. Returns the post id or sends a JSON error.
     */
    private function guard() {
        if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Session expired. Reload the editor.', 'mme-elementor-kit' ) ), 403 );
        }
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'You cannot edit this page.', 'mme-elementor-kit' ) ), 403 );
        }
        return $post_id;
    }

    private function mode() {
        $mode = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'elementor'; // phpcs:ignore WordPress.Security.NonceVerification
        if ( 'html' === $mode && ! current_user_can( 'unfiltered_html' ) ) {
            wp_send_json_error( array( 'message' => __( 'Your account cannot add HTML widgets. Use the Elementor mode.', 'mme-elementor-kit' ) ), 403 );
        }
        return 'html' === $mode ? 'html' : 'elementor';
    }

    public function ajax_start() {
        $this->guard();
        $mode  = $this->mode();
        $brief = isset( $_POST['brief'] ) ? sanitize_textarea_field( wp_unslash( $_POST['brief'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $kind  = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : 'auto'; // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! array_key_exists( $kind, Ems_Ai_Layout::kinds() ) ) {
            $kind = 'auto';
        }
        if ( mb_strlen( $brief ) < 10 ) {
            wp_send_json_error( array( 'message' => __( 'Describe it in a sentence or more.', 'mme-elementor-kit' ) ), 400 );
        }

        $job = Ems_Ai_Client::start_job( array(
            'brief'         => mb_substr( $brief, 0, 4000 ),
            'kind'          => $kind,
            'site_name'     => get_bloginfo( 'name' ),
            'output'        => 'html' === $mode ? 'html_block' : 'layout',
            'use_knowledge' => ! empty( $_POST['use_knowledge'] ), // phpcs:ignore WordPress.Security.NonceVerification
            'primary_color' => Ems_Ai_Layout::get_instance()->brand_color(),
        ) );
        if ( is_wp_error( $job ) ) {
            wp_send_json_error( array( 'message' => $job->get_error_message(), 'code' => $job->get_error_code() ), 400 );
        }
        wp_send_json_success( array( 'job' => $job['id'], 'usage' => $job['usage'] ) );
    }

    public function ajax_poll() {
        $this->guard();
        $mode = $this->mode();
        $id   = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $state = Ems_Ai_Client::job_state( $id );
        if ( is_wp_error( $state ) ) {
            // Network blips while polling are retried by the editor.
            $retry = in_array( $state->get_error_code(), array( 'mme_kit_ai_network', 'mme_kit_ai_service', 'mme_kit_ai_busy', 'mme_kit_ai_rate_limited' ), true );
            wp_send_json_error( array( 'message' => $state->get_error_message(), 'retry' => $retry ), 400 );
        }
        if ( 'done' !== $state['status'] ) {
            wp_send_json_success( array( 'status' => 'running' ) );
        }

        $result = $state['result'];
        $layout = Ems_Ai_Layout::get_instance();
        if ( 'html' === $mode ) {
            if ( empty( $result['html'] ) || ! is_string( $result['html'] ) ) {
                wp_send_json_error( array( 'message' => Ems_Ai_Client::error( 'no_sections' )->get_error_message() ), 400 );
            }
            wp_send_json_success( array(
                'status'   => 'done',
                'title'    => isset( $result['title'] ) ? sanitize_text_field( $result['title'] ) : '',
                'html'     => $result['html'],
                'elements' => $layout->html_elements( $result['html'] ),
            ) );
        }

        $elements = ! empty( $result['layout'] ) && is_array( $result['layout'] ) ? $layout->build( $result['layout'] ) : array();
        if ( ! $elements ) {
            wp_send_json_error( array( 'message' => Ems_Ai_Client::error( 'no_sections' )->get_error_message() ), 400 );
        }
        wp_send_json_success( array(
            'status'   => 'done',
            'title'    => isset( $result['title'] ) ? sanitize_text_field( $result['title'] ) : '',
            'elements' => $elements,
        ) );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
