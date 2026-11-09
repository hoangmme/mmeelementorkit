<?php
namespace Ems_Elements_Share\Admin;

/**
 * Class responsible for exporting / importing the master secret keys,
 * e.g. to move the master to another site.
 *
 * Template and category IDs differ between sites, so they are exported with
 * their slug and title and matched again on import.
 */
class Ems_Keys_Transfer {

    /**
     * Identifies an export file.
     */
    const FORMAT = 'mme-elementor-kit-keys';

    private static $instance = null;

    /**
     * Notice shown after an import: array( type, message ).
     *
     * @var array|null
     */
    private $notice = null;

    public function __construct() {
        // Hooks
        add_action( 'admin_post_mme_kit_export_keys', array( $this, 'export' ) );
        add_action( 'admin_init', array( $this, 'handle_import' ) );
        add_action( 'ems_elements_share_server_form_notice', array( $this, 'add_notice' ) );
    }

    /**
     * Url that downloads the export file.
     *
     * @return string
     */
    public function get_export_url() {
        return wp_nonce_url( admin_url( 'admin-post.php?action=mme_kit_export_keys' ), 'mme_kit_export_keys' );
    }

    /**
     * Download all keys as a JSON file.
     *
     * @return void
     */
    public function export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_export_keys' );

        $key_manager = Ems_Key_Manager::get_instance();
        $all_rules   = get_option( 'ems_elements_share_server_rules' );
        $all_rules   = is_array( $all_rules ) ? $all_rules : array();

        $keys = array();
        foreach ( $all_rules as $rule ) {
            $keys[] = array(
                'secret_key'         => $rule['secret_key'],
                'reference'          => isset( $rule['reference'] ) ? $rule['reference'] : '',
                'max_site'           => isset( $rule['max_site'] ) ? (int) $rule['max_site'] : 1,
                'template_select_by' => isset( $rule['template_select_by'] ) ? $rule['template_select_by'] : 'all',
                'categories'         => $this->export_categories( isset( $rule['categories'] ) ? $rule['categories'] : array() ),
                'templates'          => $this->export_templates( isset( $rule['templates'] ) ? $rule['templates'] : array() ),
                'site_used'          => $key_manager->get_site_used( $rule['secret_key'] ),
            );
        }

        $data = array(
            'format'         => self::FORMAT,
            'version'        => 1,
            'plugin_version' => EMS_ELEMENTS_SHARE_VERSION,
            'source'         => home_url( '/' ),
            'exported_at'    => gmdate( 'c' ),
            'keys'           => $keys,
        );

        $filename = 'mme-kit-keys-' . sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . gmdate( 'Ymd-His' ) . '.json';

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /**
     * Describe categories by id, slug and name.
     *
     * @param array $ids
     * @return array
     */
    private function export_categories( $ids ) {
        $categories = array();
        foreach ( (array) $ids as $id ) {
            $term = get_term( (int) $id, 'elementor_library_category' );
            if ( $term && ! is_wp_error( $term ) ) {
                $categories[] = array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name );
            }
        }
        return $categories;
    }

    /**
     * Describe templates by id, slug and title.
     *
     * @param array $ids
     * @return array
     */
    private function export_templates( $ids ) {
        $templates = array();
        foreach ( (array) $ids as $id ) {
            $post = get_post( (int) $id );
            if ( $post && 'elementor_library' === $post->post_type ) {
                $templates[] = array( 'id' => (int) $post->ID, 'slug' => $post->post_name, 'title' => $post->post_title );
            }
        }
        return $templates;
    }

    /**
     * Import keys from an uploaded export file.
     *
     * @return void
     */
    public function handle_import() {
        if ( ! ( isset( $_POST['action'] ) && $_POST['action'] === 'mme_kit_import_keys' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_import_keys', '_wp_nonce_import_keys' );

        $file = isset( $_FILES['mme_kit_import_file'] ) ? $_FILES['mme_kit_import_file'] : null;
        if ( ! $file || UPLOAD_ERR_OK !== $file['error'] || empty( $file['tmp_name'] ) ) {
            $this->notice = array( 'error', __( 'Please choose an export file to import.', 'mme-elementor-kit' ) );
            return;
        }

        if ( $file['size'] > MB_IN_BYTES ) {
            $this->notice = array( 'error', __( 'The file is too large.', 'mme-elementor-kit' ) );
            return;
        }

        $data = json_decode( file_get_contents( $file['tmp_name'] ), true );
        if ( ! is_array( $data ) || ! isset( $data['format'] ) || self::FORMAT !== $data['format'] || ! isset( $data['keys'] ) || ! is_array( $data['keys'] ) ) {
            $this->notice = array( 'error', __( 'This is not a MMe Elementor Kit key export file.', 'mme-elementor-kit' ) );
            return;
        }

        $replace   = isset( $_POST['mme_kit_import_mode'] ) && 'replace' === $_POST['mme_kit_import_mode'];
        $same_site = isset( $data['source'] ) && untrailingslashit( $data['source'] ) === untrailingslashit( home_url( '/' ) );

        $old_rules = get_option( 'ems_elements_share_server_rules' );
        $old_rules = is_array( $old_rules ) ? $old_rules : array();

        // Index by key so an imported key replaces the existing one with the same key.
        $rules_by_key = array();
        if ( ! $replace ) {
            foreach ( $old_rules as $rule ) {
                $rules_by_key[ $rule['secret_key'] ] = $rule;
            }
        }

        $imported  = 0;
        $unmatched = 0;
        foreach ( $data['keys'] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            // The connect / disconnect REST routes only accept word characters.
            $key = isset( $item['secret_key'] ) ? sanitize_text_field( $item['secret_key'] ) : '';
            if ( ! preg_match( '/^\w+$/', $key ) ) {
                continue;
            }

            $scope = isset( $item['template_select_by'] ) ? $item['template_select_by'] : 'all';
            if ( ! in_array( $scope, array( 'all', 'categories', 'templates' ), true ) ) {
                $scope = 'all';
            }

            $rules_by_key[ $key ] = array(
                'secret_key'         => $key,
                'template_select_by' => $scope,
                'categories'         => $this->match_categories( isset( $item['categories'] ) ? $item['categories'] : array(), $same_site, $unmatched ),
                'templates'          => $this->match_templates( isset( $item['templates'] ) ? $item['templates'] : array(), $same_site, $unmatched ),
                'reference'          => isset( $item['reference'] ) ? sanitize_text_field( $item['reference'] ) : '',
                'max_site'           => isset( $item['max_site'] ) ? max( 1, (int) $item['max_site'] ) : 1,
            );

            if ( isset( $item['site_used'] ) ) {
                update_option( 'ems_elements_share_key_' . $key . '_site_used', max( 0, (int) $item['site_used'] ) );
            }
            $imported++;
        }

        // Replaced keys no longer exist, so drop their usage counters.
        if ( $replace ) {
            foreach ( $old_rules as $rule ) {
                if ( ! isset( $rules_by_key[ $rule['secret_key'] ] ) ) {
                    delete_option( 'ems_elements_share_key_' . $rule['secret_key'] . '_site_used' );
                }
            }
        }

        update_option( 'ems_elements_share_server_rules', array_values( $rules_by_key ) );

        /* translators: %d: number of keys */
        $message = sprintf( _n( '%d key imported.', '%d keys imported.', $imported, 'mme-elementor-kit' ), $imported );
        if ( $unmatched ) {
            /* translators: %d: number of templates or categories */
            $message .= ' ' . sprintf( _n( '%d template or category was not found on this site, please review the affected keys.', '%d templates or categories were not found on this site, please review the affected keys.', $unmatched, 'mme-elementor-kit' ), $unmatched );
        }
        $this->notice = array( $unmatched ? 'error' : 'success', $message );
    }

    /**
     * Map exported categories to term ids on this site.
     *
     * @param array   $items
     * @param boolean $same_site Whether the file was exported from this site.
     * @param int     $unmatched Incremented for every category not found.
     * @return array
     */
    private function match_categories( $items, $same_site, &$unmatched ) {
        $taxonomy = 'elementor_library_category';
        $ids      = array();
        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $term = null;
            if ( $same_site && ! empty( $item['id'] ) ) {
                $term = get_term( (int) $item['id'], $taxonomy );
            }
            if ( ( ! $term || is_wp_error( $term ) ) && ! empty( $item['slug'] ) ) {
                $term = get_term_by( 'slug', $item['slug'], $taxonomy );
            }
            if ( ( ! $term || is_wp_error( $term ) ) && ! empty( $item['name'] ) ) {
                $term = get_term_by( 'name', $item['name'], $taxonomy );
            }

            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = (string) $term->term_id;
            } else {
                $unmatched++;
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * Map exported templates to post ids on this site.
     *
     * @param array   $items
     * @param boolean $same_site Whether the file was exported from this site.
     * @param int     $unmatched Incremented for every template not found.
     * @return array
     */
    private function match_templates( $items, $same_site, &$unmatched ) {
        $ids = array();
        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $id = 0;
            if ( $same_site && ! empty( $item['id'] ) ) {
                $post = get_post( (int) $item['id'] );
                $id   = $post && 'elementor_library' === $post->post_type ? $post->ID : 0;
            }
            if ( ! $id && ! empty( $item['slug'] ) ) {
                $post = get_page_by_path( $item['slug'], OBJECT, 'elementor_library' );
                $id   = $post ? $post->ID : 0;
            }
            if ( ! $id && ! empty( $item['title'] ) ) {
                $found = get_posts( array(
                    'post_type'      => 'elementor_library',
                    'title'          => $item['title'],
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ) );
                $id = $found ? (int) $found[0] : 0;
            }

            if ( $id ) {
                $ids[] = (string) $id;
            } else {
                $unmatched++;
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * Display the import result.
     *
     * @return void
     */
    public function add_notice() {
        if ( $this->notice === null ) {
            return;
        }
        list( $type, $message ) = $this->notice;
        ?>
        <div class="ems-alert ems-alert--<?php echo esc_attr( $type ); ?>">
            <span class="dashicons <?php echo 'success' === $type ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
            <?php echo esc_html( $message ); ?>
        </div>
        <?php
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
