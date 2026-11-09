<?php 
namespace Ems_Elements_Share\Admin;

/**
 * Class responsible for handling the receive site settings.
 */
class Ems_Receiver_Manager {

    private static $instance = null;


    public $error_message = null;
    public $saving_success = null;

    public function __construct() {
        // Hooks
        // Form handler hook
        add_action( 'admin_init', array( $this, 'handle_form' ) );

        add_action( 'ems_elements_share_receiver_form_notice', array( $this, 'add_notice' ) );

        // Ajax handler
        add_action( 'wp_ajax_mme_kit_fetch_remote_templates', array( $this, 'fetch_remote_templates' ) );
    }

    /**
     * Saved master site url.
     *
     * @return string
     */
    public function get_server_url() {
        return get_option( 'ems_elements_share_server_url', '' );
    }

    /**
     * Saved secret key.
     *
     * @return string
     */
    public function get_server_key() {
        return get_option( 'ems_elements_share_server_key', '' );
    }

    /**
     * Whether this site is connected to a master site.
     *
     * @return boolean
     */
    public function is_connected() {
        return strlen( $this->get_server_url() ) && strlen( $this->get_server_key() );
    }

    /**
     * Fetch the templates the master site shares with this key, via ajax.
     * Doubles as a connection test for the client page.
     *
     * @return void
     */
    public function fetch_remote_templates() {
        check_ajax_referer( 'ems_elements_share_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Are you cheating?', 'mme-elementor-kit' ) ), 403 );
        }

        if ( ! $this->is_connected() ) {
            wp_send_json_error( array( 'message' => __( 'This site is not connected to a master site.', 'mme-elementor-kit' ) ) );
        }

        // The secret key is injected by Ems_Rest_Api::modify_request().
        $request = wp_remote_get( trailingslashit( $this->get_server_url() ) . 'wp-json/mme-kit/v1/templates/ems_elementor_sections', array( 'timeout' => 30 ) );

        if ( is_wp_error( $request ) ) {
            wp_send_json_error( array( 'message' => $request->get_error_message() ) );
        }

        $code     = (int) wp_remote_retrieve_response_code( $request );
        $messages = array(
            401 => __( 'The master site did not receive the secret key.', 'mme-elementor-kit' ),
            402 => __( 'Site limit reached for this key on the master site.', 'mme-elementor-kit' ),
            429 => __( 'The secret key is no longer valid on the master site.', 'mme-elementor-kit' ),
        );
        if ( isset( $messages[ $code ] ) ) {
            wp_send_json_error( array( 'message' => $messages[ $code ] ) );
        }

        $response = json_decode( wp_remote_retrieve_body( $request ), true );
        if ( ! is_array( $response ) || empty( $response['success'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Error making connection to the server site!', 'mme-elementor-kit' ) ) );
        }

        $templates = array();
        if ( isset( $response['templates'] ) && is_array( $response['templates'] ) ) {
            foreach ( $response['templates'] as $template ) {
                $templates[] = array(
                    'id'         => isset( $template['template_id'] ) ? absint( $template['template_id'] ) : 0,
                    'title'      => isset( $template['title'] ) ? sanitize_text_field( $template['title'] ) : '',
                    'thumbnail'  => isset( $template['thumbnail'] ) ? esc_url_raw( $template['thumbnail'] ) : '',
                    'categories' => isset( $template['categories'] ) && is_array( $template['categories'] ) ? $this->sanitize_array( $template['categories'] ) : array(),
                );
            }
        }

        wp_send_json_success( array(
            'templates'  => $templates,
            'checked_at' => date_i18n( get_option( 'time_format' ) ),
        ) );
    }


    public function handle_form() {
        if ( ! ( isset( $_POST['action'] ) && isset( $_POST['_wp_nonce_receiver_rules'] ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __('Are you cheating?', 'mme-elementor-kit') );
        }

        if ( ! wp_verify_nonce( $_POST['_wp_nonce_receiver_rules'], 'save_receiver_rules' ) ) {
            wp_die( __('Are you cheating?', 'mme-elementor-kit') );
        }

        $url = null;
        $key = null;
        if ( isset( $_POST['ems_elements_share_server_url'] ) ) {
            $url = esc_url_raw( $_POST['ems_elements_share_server_url'] );
        }
        if ( isset( $_POST['ems_elements_share_server_key'] ) ) {
            $key = sanitize_text_field( $_POST['ems_elements_share_server_key'] );
        }

        if ( $url === null || $key === null ) {
            return;
        }

        if ( $_POST['action'] === 'save_receiver_rules' ) {
            // Make connection with the server
            $request = wp_remote_get( trailingslashit( $url ) . 'wp-json/mme-kit/v1/connect-server/' . $key );
            $body = wp_remote_retrieve_body( $request );
            
            if ( strlen($body) == 0 ) {
                // Request was not made successfully
                // Add notice
                $this->error_message = __( 'Error making request to the server site!', 'mme-elementor-kit' );
                return;
            }
            else {
                $response = json_decode( $body, true );
                if ( !isset( $response['success'] ) ) {
                    $this->error_message = __( 'Error making connection to the server site!', 'mme-elementor-kit' );
                    return;
                }

                if ( $response['success'] !== true ) {
                    $this->error_message = $response['message'];
                    return;
                }
                else if ( $response['success'] === true ) {
                    update_option( 'ems_elements_share_server_url', $url );
                    update_option( 'ems_elements_share_server_key', $key );

                    // Add notice
                    $this->error_message = null;
                    $this->saving_success = true;
                }
                else {
                    // Add notice
                    $this->error_message = __( 'Unknown error occured. Try again later.', 'mme-elementor-kit' );
                    return;
                }
            }
        }
        else if ( $_POST['action'] === 'disconnect_receiver_rules' ) {
            // Disconnect connection with the server
            $request = wp_remote_get( trailingslashit( $url ) . 'wp-json/mme-kit/v1/disconnect-server/' . $key );
            $body = wp_remote_retrieve_body( $request );
            
            if ( strlen($body) == 0 ) {
                // Request was not made successfully
                // Add notice
                $this->error_message = __( 'Error making request to the server site!', 'mme-elementor-kit' );
                return;
            }
            else {
                $response = json_decode( $body, true );
                if ( !isset( $response['success'] ) ) {
                    $this->error_message = __( 'Error making connection to the server site!', 'mme-elementor-kit' );
                    return;
                }

                if ( $response['success'] !== true ) {
                    $this->error_message = $response['message'];
                    return;
                }
                else if ( $response['success'] === true ) {
                    delete_option( 'ems_elements_share_server_url' );
                    delete_option( 'ems_elements_share_server_key' );

                    // Add notice
                    $this->error_message = null;
                }
                else {
                    // Add notice
                    $this->error_message = __( 'Unknown error occured. Try again later.', 'mme-elementor-kit' );
                    return;
                }
            }
        }
    }

    /**
     * Display notice based on current situation.
     * Current situation means current value of $this->error_message
     * and $this->saving_success
     *
     * @return void
     */
    public function add_notice() {
        if ( $this->error_message !== null ) {
            ?> 
            <div class="ems-alert ems-alert--error">
                <span class="dashicons dashicons-warning"></span>
                <?php echo esc_html( $this->error_message ); ?>
            </div>
            <?php
        }
        else if ( $this->saving_success ) {
            ?> 
            <div class="ems-alert ems-alert--success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__( 'Connected Successfully!', 'mme-elementor-kit' ); ?>
            </div>
            <?php
        }
    }


    /**
     * Sanitize a one dimensional array.
     *
     * @param array $array
     * @return array
     */
    public function sanitize_array( $array ) {
        foreach ($array as $key => $value) {
            $array[$key] = sanitize_text_field( $value );
        }
        return $array;
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}


?>