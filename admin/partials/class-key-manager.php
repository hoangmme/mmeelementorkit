<?php 
namespace Ems_Elements_Share\Admin;

/**
 * Class responsible for handling all keys.
 */
class Ems_Key_Manager {

    private static $instance = null;

    public function __construct() {
        // Hooks
        // Form handler hook
        add_action( 'admin_init', array( $this, 'handle_form' ) );

        // Ajax handler
        add_action( 'wp_ajax_mme_kit_add_new_key', array( $this, 'add_new_rule' ) );
        add_action( 'wp_ajax_mme_kit_delete_key', array( $this, 'delete_key' ) );
        add_action( 'wp_ajax_mme_kit_reset_key_usage', array( $this, 'reset_key_usage' ) );
    }

    /**
     * Verify nonce and capability of an admin ajax request.
     *
     * @return void
     */
    private function verify_ajax_request() {
        check_ajax_referer( 'ems_elements_share_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ), 403 );
        }
    }

    /**
     * Delete all data in database related to a key.
     *
     * @return void
     */
    public function delete_key() {
        $this->verify_ajax_request();
        if ( isset( $_POST['secret_key'] ) ) {
            $key = sanitize_text_field( $_POST['secret_key'] );
            delete_option( 'ems_elements_share_key_' . $key . '_site_used' );
        }
        wp_die();
    }

    /**
     * Add new rule via ajax.
     *
     * @return void
     */
    public function add_new_rule() {
        $this->verify_ajax_request();
        if ( isset( $_POST['new_id'] ) ) {
            $id = sanitize_text_field( $_POST['new_id'] );
            $all_rules = get_option( 'ems_elements_share_server_rules' );
            $elementor_posts = $this->get_elementor_template_posts();
            $elementor_categories = $this->get_elementor_template_categories();
            $this->single_rule_section( $id, $all_rules, $elementor_posts, $elementor_categories, true );
        }
        wp_die();
    }

    /**
     * Reset the connected site count of a key via ajax.
     *
     * @return void
     */
    public function reset_key_usage() {
        $this->verify_ajax_request();
        if ( isset( $_POST['secret_key'] ) ) {
            $key = sanitize_text_field( $_POST['secret_key'] );
            delete_option( 'ems_elements_share_key_' . $key . '_site_used' );
            wp_send_json_success( array( 'site_used' => 0 ) );
        }
        wp_send_json_error();
    }

    /**
     * Get the connected site count of a key.
     *
     * @param string $key
     * @return int
     */
    public function get_site_used( $key ) {
        return (int) get_option( 'ems_elements_share_key_' . $key . '_site_used', 0 );
    }

    /**
     * Summary numbers for the master dashboard.
     *
     * @return array
     */
    public function get_stats() {
        $all_rules = get_option( 'ems_elements_share_server_rules' );
        $all_rules = is_array( $all_rules ) ? $all_rules : array();

        $active_sites = 0;
        $max_sites    = 0;
        foreach ( $all_rules as $rule ) {
            $active_sites += $this->get_site_used( $rule['secret_key'] );
            $max_sites    += isset( $rule['max_site'] ) ? (int) $rule['max_site'] : 1;
        }

        $templates = 0;
        if ( post_type_exists( 'elementor_library' ) ) {
            $counts    = wp_count_posts( 'elementor_library' );
            $templates = isset( $counts->publish ) ? (int) $counts->publish : 0;
        }

        $categories = 0;
        if ( taxonomy_exists( 'elementor_library_category' ) ) {
            $categories = (int) wp_count_terms( array( 'taxonomy' => 'elementor_library_category', 'hide_empty' => false ) );
        }

        return array(
            'keys'         => count( $all_rules ),
            'active_sites' => $active_sites,
            'max_sites'    => $max_sites,
            'templates'    => $templates,
            'categories'   => $categories,
        );
    }

    /**
     * If a given key is valid.
     *
     * @param string $key
     * @return boolean
     */
    public function is_key_valid( $key ) {
        $is_valid = false;
        $all_rules = get_option( 'ems_elements_share_server_rules' );
        if ( is_array( $all_rules ) && count( $all_rules ) ) {
            foreach ($all_rules as $index => $rule) {
                if ( $rule['secret_key'] === $key ) {
                    $is_valid = true;
                    break;
                }
            }
        }
        return $is_valid;
    }

    /**
     * Check if a key has enough site limit to connect more.
     *
     * @param string $key
     * @return boolean
     */
    public function has_site_limit_to_connect( $key ) {
        $rule = $this->get_rule_from_key( $key );
        $has_limit = false;
        if ( is_array( $rule ) && count( $rule ) ) {
            $max_site = isset( $rule['max_site'] ) ? (int) $rule['max_site'] : 1;
            $site_used = (int) get_option( 'ems_elements_share_key_' . $key . '_site_used', 0 );
            if ( $max_site > $site_used ) {
                $has_limit = true;
            }
        }

        return $has_limit;
    }

    /**
     * Check if a key is valid in terms of site limit.
     *
     * @param string $key
     * @return boolean
     */
    public function has_site_limit( $key ) {
        $rule = $this->get_rule_from_key( $key );
        $has_limit = false;
        if ( is_array( $rule ) && count( $rule ) ) {
            $max_site = isset( $rule['max_site'] ) ? (int) $rule['max_site'] : 1;
            $site_used = (int) get_option( 'ems_elements_share_key_' . $key . '_site_used', 0 );
            if ( $max_site >= $site_used ) {
                $has_limit = true;
            }
        }

        return $has_limit;
    }

    /**
     * Increment the site usage count for a key.
     *
     * @param string $key
     * @return void
     */
    public function increment_site_count( $key ) {
        if ( $this->has_site_limit_to_connect( $key ) ) {
            $site_used = (int) get_option( 'ems_elements_share_key_' . $key . '_site_used', 0 );
            $site_used += 1;
            update_option( 'ems_elements_share_key_' . $key . '_site_used', $site_used );
        }
    }

    /**
     * Decrement the site usage count for a key.
     *
     * @param string $key
     * @return void
     */
    public function decrement_site_count( $key ) {
        $site_used = (int) get_option( 'ems_elements_share_key_' . $key . '_site_used', 0 );
        $site_used -= 1;
        if ( $site_used >= 0 ) {
            update_option( 'ems_elements_share_key_' . $key . '_site_used', $site_used );
        }
    }

    /**
     * Get rule using a secret key.
     *
     * @param string $key
     * @return array
     */
    public function get_rule_from_key( $key ) {
        $rule = array();
        $all_rules = get_option( 'ems_elements_share_server_rules' );
        if ( is_array( $all_rules ) && count( $all_rules ) ) {
            foreach ($all_rules as $index => $rule) {
                if ( $rule['secret_key'] === $key ) {
                    return $rule;
                }
            }
        }
        return $rule;
    }

    /**
     * Save form submit.
     *
     * @return void
     */
    public function handle_form() {
        if ( ! ( isset( $_POST['action'] ) && $_POST['action'] === 'save_server_rules' && isset( $_POST['_wp_nonce_server_rules'] ) ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __('Are you cheating?', 'mme-elementor-kit') );
        }

        if ( ! wp_verify_nonce( $_POST['_wp_nonce_server_rules'], 'save_server_rules' ) ) {
            wp_die( __('Are you cheating?', 'mme-elementor-kit') );
        }

        if ( isset( $_POST['elements_share_rules'] ) ) {
            // Sanitize all the values
            if ( is_array( $_POST['elements_share_rules'] ) ) {
                $all_rules = array();
                foreach ( $_POST['elements_share_rules'] as $rule ) {
                    $single_rule        = array();
                    $secret_key         = isset( $rule['secret_key'] ) ? sanitize_text_field( $rule['secret_key'] ) : '';
                    $template_select_by = isset( $rule['template_select_by'] ) ? sanitize_text_field( $rule['template_select_by'] ) : 'all';
                    $categories         = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? $this->sanitize_array( $rule['categories'] ) : array();
                    $templates          = isset( $rule['templates'] ) && is_array( $rule['templates'] ) ? $this->sanitize_array( $rule['templates'] ) : array();
                    $reference          = isset( $rule['reference'] ) ? sanitize_text_field( $rule['reference'] ) : '';
                    $max_site           = isset( $rule['max_site'] ) ? sanitize_text_field( $rule['max_site'] ) : 1;

                    $single_rule['secret_key']         = $secret_key;
                    $single_rule['template_select_by'] = $template_select_by;
                    $single_rule['categories']         = $categories;
                    $single_rule['templates']          = $templates;
                    $single_rule['reference']          = $reference;
                    $single_rule['max_site']           = $max_site;
                    $all_rules[]                       = $single_rule;
                }

                // Save the sanitized version
                update_option( 'ems_elements_share_server_rules', $all_rules );
            }
        }
        else {
            update_option( 'ems_elements_share_server_rules', array() );
        }

        // Add notice
        add_action( 'ems_elements_share_server_form_notice', function(){
            ?> 
            <div class="ems-alert ems-alert--success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html__( 'Settings Saved Successfully!', 'mme-elementor-kit' ); ?>
            </div>
            <?php
        });
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

    /**
     * Outputs all key rules section.
     *
     * @return void
     */
    public function all_rules_section() {
        $all_rules = get_option( 'ems_elements_share_server_rules' );
        $elementor_posts = $this->get_elementor_template_posts();
        $elementor_categories = $this->get_elementor_template_categories();
        require __DIR__ . '/templates/all-rules.php';
    }

    /**
     * Outputs single key rule section.
     *
     * @return void
     */
    public function single_rule_section( $rule_id, $all_rules, $elementor_posts, $elementor_categories, $force_generate_key = false ) {
        require __DIR__ . '/templates/single-rule.php';
    }

    /**
     * Get elementor templates as array of post objects.
     *
     * @return array
     */
    public function get_elementor_template_posts() {
        // Get posts
		$args = array(
			'post_type' => 'elementor_library',
			'posts_per_page' => -1
		);
		$query = new \WP_Query( $args );
		$posts = $query->get_posts();
        if ( is_array( $posts ) ) {
            return $posts;
        }
        return array();
    }

    /**
     * Get elementor template categories as array.
     *
     * @return array
     */
    public function get_elementor_template_categories() {
        $args = array(
            'taxonomy' => 'elementor_library_category',
            'orderby' => 'name',
            'order'   => 'DESC'
        );

        $cats = get_categories($args);
        return $cats;
    }
}


?>