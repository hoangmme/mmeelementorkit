<?php
namespace Ems_Elements_Share\Areas;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extra Elementor areas per post (e.g. "Bottom" under the_content()).
 *
 * Every area is a hidden `mme_area` post linked to its parent post, so it is a
 * regular Elementor document with its own _elementor_data, CSS file and
 * revisions. The parent's own Elementor data is never touched.
 *
 *   post #123 ── meta _mme_area_bottom = 456 ──> mme_area #456 (post_parent 123)
 */
class Ems_Areas_Manager {

    const POST_TYPE = 'mme_area';

    const OPTION = 'mme_kit_areas';

    private static $instance = null;

    /**
     * Areas already printed in this request: "{post_id}:{area}" => true.
     *
     * @var array
     */
    private $rendered = array();

    /**
     * Area documents being rendered right now, to stop an area rendering itself.
     *
     * @var array
     */
    private $rendering = array();

    /**
     * Notice shown after saving settings: array( type, message ).
     *
     * @var array|null
     */
    private $notice = null;

    public function __construct() {
        // Hooks
        add_action( 'init', array( $this, 'register_post_type' ), 5 );
        add_filter( 'map_meta_cap', array( $this, 'map_area_caps' ), 10, 4 );
        add_action( 'template_redirect', array( $this, 'protect_area_urls' ) );
        add_action( 'before_delete_post', array( $this, 'delete_child_areas' ) );

        // Automatic output after the content.
        add_filter( 'the_content', array( $this, 'append_to_content' ), 20 );
        add_filter( 'elementor/frontend/the_content', array( $this, 'append_to_elementor_content' ) );
        add_shortcode( 'mme_area', array( $this, 'shortcode' ) );

        // Edit buttons.
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
        add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
        add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 200 );
        add_action( 'admin_post_mme_kit_edit_area', array( $this, 'handle_edit' ) );
        add_action( 'admin_post_mme_kit_remove_area', array( $this, 'handle_remove' ) );
        add_action( 'load-post.php', array( $this, 'redirect_area_edit_screen' ) );

        // Settings page.
        add_action( 'admin_init', array( $this, 'handle_settings_form' ) );
        add_action( 'ems_elements_share_areas_notice', array( $this, 'add_notice' ) );
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /*
     * Settings
     */

    /**
     * Settings with defaults: post types that get areas, and the areas themselves.
     *
     * @return array { post_types: string[], areas: array<name, { label, mode }> }
     */
    public function get_config() {
        $config = get_option( self::OPTION );
        if ( ! is_array( $config ) ) {
            $config = array(
                'post_types' => array( 'post' ),
                'areas'      => array(
                    'bottom' => array( 'label' => __( 'Bottom', 'mme-elementor-kit' ), 'mode' => 'after_content' ),
                ),
            );
        }
        $config['post_types'] = isset( $config['post_types'] ) && is_array( $config['post_types'] ) ? $config['post_types'] : array();
        $config['areas']      = isset( $config['areas'] ) && is_array( $config['areas'] ) ? $config['areas'] : array();
        return $config;
    }

    /**
     * Configured areas.
     *
     * @return array
     */
    public function get_areas() {
        $config = $this->get_config();
        return $config['areas'];
    }

    /**
     * Whether a post type has areas.
     *
     * @param string $post_type
     * @return boolean
     */
    public function supports( $post_type ) {
        $config = $this->get_config();
        return $post_type && in_array( $post_type, $config['post_types'], true ) && count( $config['areas'] );
    }

    /**
     * Post types that can be given areas.
     *
     * @return \WP_Post_Type[]
     */
    public function get_available_post_types() {
        $types = get_post_types( array( 'public' => true ), 'objects' );
        unset( $types['attachment'], $types['elementor_library'], $types['e-landing-page'], $types[ self::POST_TYPE ] );
        return $types;
    }

    /**
     * Save the Areas settings page.
     *
     * @return void
     */
    public function handle_settings_form() {
        if ( ! ( isset( $_POST['action'] ) && $_POST['action'] === 'mme_kit_save_areas' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_save_areas', '_wp_nonce_areas' );

        $available  = array_keys( $this->get_available_post_types() );
        $post_types = isset( $_POST['mme_kit_area_post_types'] ) && is_array( $_POST['mme_kit_area_post_types'] ) ? array_map( 'sanitize_key', $_POST['mme_kit_area_post_types'] ) : array();
        $post_types = array_values( array_intersect( $post_types, $available ) );

        $areas = array();
        $rows  = isset( $_POST['mme_kit_areas'] ) && is_array( $_POST['mme_kit_areas'] ) ? wp_unslash( $_POST['mme_kit_areas'] ) : array();
        foreach ( $rows as $row ) {
            $name = isset( $row['name'] ) ? substr( sanitize_key( $row['name'] ), 0, 20 ) : '';
            if ( '' === $name || isset( $areas[ $name ] ) ) {
                continue;
            }
            $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
            $mode  = isset( $row['mode'] ) && 'manual' === $row['mode'] ? 'manual' : 'after_content';

            $areas[ $name ] = array(
                'label' => '' !== $label ? $label : ucfirst( $name ),
                'mode'  => $mode,
            );
        }

        update_option( self::OPTION, array( 'post_types' => $post_types, 'areas' => $areas ) );
        $this->notice = array( 'success', __( 'Areas saved.', 'mme-elementor-kit' ) );
    }

    /**
     * Display the save result.
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
            <span class="dashicons dashicons-yes-alt"></span>
            <?php echo esc_html( $message ); ?>
        </div>
        <?php
    }

    /*
     * Storage
     */

    /**
     * Register the hidden post type that stores area documents.
     *
     * @return void
     */
    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'label'               => __( 'MMe Areas', 'mme-elementor-kit' ),
            // Not public (no sitemap, search, menus), but viewable so Elementor can preview it.
            'public'              => false,
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,
            'show_in_rest'        => false,
            'rewrite'             => false,
            'query_var'           => true,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => array( 'title', 'revisions', 'elementor' ),
        ) );
    }

    /**
     * Meta key on the parent post that holds an area id.
     *
     * @param string $area
     * @return string
     */
    private function meta_key( $area ) {
        return '_mme_area_' . $area;
    }

    /**
     * Id of a post's area document, 0 when it does not exist.
     *
     * @param int    $post_id
     * @param string $area
     * @return int
     */
    public function get_area_id( $post_id, $area ) {
        $area_id = (int) get_post_meta( $post_id, $this->meta_key( $area ), true );
        if ( ! $area_id ) {
            return 0;
        }

        // A copied meta value (e.g. by a duplicate post plugin) must not show another post's area.
        $area_post = get_post( $area_id );
        if ( ! $area_post || self::POST_TYPE !== $area_post->post_type || (int) $area_post->post_parent !== (int) $post_id ) {
            return 0;
        }
        return $area_id;
    }

    /**
     * Get or create the area document of a post.
     *
     * @param int    $post_id
     * @param string $area
     * @return int|\WP_Error
     */
    public function ensure_area( $post_id, $area ) {
        $area_id = $this->get_area_id( $post_id, $area );
        if ( $area_id ) {
            return $area_id;
        }

        $areas = $this->get_areas();
        $label = isset( $areas[ $area ]['label'] ) ? $areas[ $area ]['label'] : $area;

        $area_id = wp_insert_post( array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_parent' => $post_id,
            /* translators: 1: post title, 2: area label */
            'post_title'  => sprintf( __( '%1$s — %2$s', 'mme-elementor-kit' ), get_the_title( $post_id ), $label ),
            'meta_input'  => array(
                '_mme_area_name'          => $area,
                '_elementor_edit_mode'    => 'builder',
                '_elementor_template_type' => 'wp-post',
                // Full width canvas in the editor, the area renders inside the parent's layout anyway.
                '_wp_page_template'       => 'elementor_canvas',
            ),
        ), true );

        if ( is_wp_error( $area_id ) ) {
            return $area_id;
        }

        update_post_meta( $post_id, $this->meta_key( $area ), $area_id );
        return $area_id;
    }

    /**
     * Area documents follow their parent's permissions.
     *
     * @param array  $caps
     * @param string $cap
     * @param int    $user_id
     * @param array  $args
     * @return array
     */
    public function map_area_caps( $caps, $cap, $user_id, $args ) {
        if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
            return $caps;
        }
        $post = get_post( $args[0] );
        if ( ! $post || self::POST_TYPE !== $post->post_type || ! $post->post_parent ) {
            return $caps;
        }
        return map_meta_cap( $cap, $user_id, $post->post_parent );
    }

    /**
     * Area urls only exist for the Elementor preview. Visitors go to the parent post.
     *
     * @return void
     */
    public function protect_area_urls() {
        if ( ! is_singular( self::POST_TYPE ) ) {
            return;
        }
        $area = get_queried_object();
        if ( current_user_can( 'edit_post', $area->ID ) ) {
            return;
        }
        if ( $area->post_parent && 'publish' === get_post_status( $area->post_parent ) ) {
            wp_safe_redirect( get_permalink( $area->post_parent ), 301 );
            exit;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
    }

    /**
     * Delete a post's areas together with the post.
     *
     * @param int $post_id
     * @return void
     */
    public function delete_child_areas( $post_id ) {
        if ( self::POST_TYPE === get_post_type( $post_id ) ) {
            return;
        }
        $children = get_children( array( 'post_parent' => $post_id, 'post_type' => self::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids' ) );
        foreach ( $children as $child_id ) {
            wp_delete_post( $child_id, true );
        }
    }

    /*
     * Output
     */

    /**
     * Whether we are inside the Elementor editor or its preview.
     *
     * @return boolean
     */
    private function is_elementor_editor() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return false;
        }
        $plugin = \Elementor\Plugin::instance();
        return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
    }

    /**
     * HTML of a post's area. Each area is printed once per request.
     *
     * @param int    $post_id
     * @param string $area
     * @return string
     */
    public function render( $post_id, $area ) {
        $key = $post_id . ':' . $area;
        if ( isset( $this->rendered[ $key ] ) || ! class_exists( '\Elementor\Plugin' ) ) {
            return '';
        }

        $area_id = $this->get_area_id( $post_id, $area );
        if ( ! $area_id || isset( $this->rendering[ $area_id ] ) ) {
            return '';
        }

        $this->rendered[ $key ]      = true;
        $this->rendering[ $area_id ] = true;
        $html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $area_id, true );
        unset( $this->rendering[ $area_id ] );

        if ( '' === trim( (string) $html ) ) {
            return '';
        }
        return '<div class="mme-area mme-area--' . esc_attr( $area ) . '">' . $html . '</div>';
    }

    /**
     * Areas set to show automatically after the content.
     *
     * @param int $post_id
     * @return string
     */
    private function render_after_content( $post_id ) {
        $html = '';
        foreach ( $this->get_areas() as $name => $area ) {
            if ( 'after_content' === $area['mode'] ) {
                $html .= $this->render( $post_id, $name );
            }
        }
        return $html;
    }

    /**
     * Whether automatic output applies to the main post of this request.
     *
     * @return int Post id, or 0.
     */
    private function get_auto_post_id() {
        if ( is_admin() || ! is_singular() || $this->is_elementor_editor() || ! empty( $this->rendering ) ) {
            return 0;
        }
        $post_id = get_queried_object_id();
        return $this->supports( get_post_type( $post_id ) ) ? $post_id : 0;
    }

    /**
     * Posts not built with Elementor: append to the_content().
     *
     * @param string $content
     * @return string
     */
    public function append_to_content( $content ) {
        $post_id = $this->get_auto_post_id();
        if ( ! $post_id || get_the_ID() !== $post_id || ! in_the_loop() ) {
            return $content;
        }

        // Elementor-built content is handled by append_to_elementor_content().
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $document = \Elementor\Plugin::instance()->documents->get( $post_id );
            if ( $document && $document->is_built_with_elementor() ) {
                return $content;
            }
        }

        return $content . $this->render_after_content( $post_id );
    }

    /**
     * Posts built with Elementor (theme the_content() or Elementor Pro "Post Content").
     *
     * @param string $content
     * @return string
     */
    public function append_to_elementor_content( $content ) {
        $post_id = $this->get_auto_post_id();
        if ( ! $post_id ) {
            return $content;
        }

        $document = \Elementor\Plugin::instance()->documents->get_current();
        if ( ! $document || (int) $document->get_main_id() !== $post_id ) {
            return $content;
        }

        return $content . $this->render_after_content( $post_id );
    }

    /**
     * [mme_area name="bottom" post_id=""]
     *
     * @param array $atts
     * @return string
     */
    public function shortcode( $atts ) {
        $atts    = shortcode_atts( array( 'name' => 'bottom', 'post_id' => 0 ), $atts, 'mme_area' );
        $post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();
        return $post_id ? $this->render( $post_id, sanitize_key( $atts['name'] ) ) : '';
    }

    /*
     * Edit buttons
     */

    /**
     * Url that creates the area if needed and opens it in Elementor.
     *
     * @param int    $post_id
     * @param string $area
     * @return string
     */
    public function get_edit_url( $post_id, $area ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=mme_kit_edit_area&post_id=' . (int) $post_id . '&area=' . rawurlencode( $area ) ),
            'mme_kit_edit_area_' . (int) $post_id . '_' . $area
        );
    }

    /**
     * Url that deletes the area document.
     *
     * @param int    $post_id
     * @param string $area
     * @return string
     */
    public function get_remove_url( $post_id, $area ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=mme_kit_remove_area&post_id=' . (int) $post_id . '&area=' . rawurlencode( $area ) ),
            'mme_kit_remove_area_' . (int) $post_id . '_' . $area
        );
    }

    /**
     * Read and check post_id / area from an edit or remove request.
     *
     * @param string $action
     * @return array array( post_id, area )
     */
    private function verify_area_request( $action ) {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        $area    = isset( $_GET['area'] ) ? sanitize_key( $_GET['area'] ) : '';

        check_admin_referer( $action . '_' . $post_id . '_' . $area );

        $areas = $this->get_areas();
        if ( ! $post_id || ! isset( $areas[ $area ] ) || ! $this->supports( get_post_type( $post_id ) ) ) {
            wp_die( __( 'This area does not exist.', 'mme-elementor-kit' ) );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        return array( $post_id, $area );
    }

    /**
     * Create the area if needed, then open it in Elementor.
     *
     * @return void
     */
    public function handle_edit() {
        list( $post_id, $area ) = $this->verify_area_request( 'mme_kit_edit_area' );

        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            wp_die( __( 'Elementor is not active.', 'mme-elementor-kit' ) );
        }

        $area_id = $this->ensure_area( $post_id, $area );
        if ( is_wp_error( $area_id ) ) {
            wp_die( esc_html( $area_id->get_error_message() ) );
        }

        $document = \Elementor\Plugin::instance()->documents->get( $area_id );
        $url      = $document ? $document->get_edit_url() : admin_url( 'post.php?post=' . $area_id . '&action=elementor' );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Delete the area document and go back to the post.
     *
     * @return void
     */
    public function handle_remove() {
        list( $post_id, $area ) = $this->verify_area_request( 'mme_kit_remove_area' );

        $area_id = $this->get_area_id( $post_id, $area );
        if ( $area_id ) {
            wp_delete_post( $area_id, true );
        }
        delete_post_meta( $post_id, $this->meta_key( $area ) );

        wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
        exit;
    }

    /**
     * Elementor's "Exit" opens the area's classic edit screen. Send the user to the parent post instead.
     *
     * @return void
     */
    public function redirect_area_edit_screen() {
        // Only the edit screen: "action=elementor" (the editor itself) runs later on admin_action_elementor.
        if ( ! isset( $_GET['post'], $_GET['action'] ) || 'edit' !== $_GET['action'] ) {
            return;
        }
        $post = get_post( absint( $_GET['post'] ) );
        if ( $post && self::POST_TYPE === $post->post_type && $post->post_parent ) {
            wp_safe_redirect( get_edit_post_link( $post->post_parent, 'raw' ) );
            exit;
        }
    }

    /**
     * Whether an area has Elementor content.
     *
     * @param int $area_id
     * @return boolean
     */
    private function has_content( $area_id ) {
        $data = get_post_meta( $area_id, '_elementor_data', true );
        return is_string( $data ) && strlen( $data ) > 2;
    }

    /**
     * "MMe Elementor areas" box on the post edit screen.
     *
     * @param string $post_type
     * @return void
     */
    public function add_meta_box( $post_type ) {
        if ( ! $this->supports( $post_type ) ) {
            return;
        }
        add_meta_box( 'mme-kit-areas', __( 'MMe Elementor areas', 'mme-elementor-kit' ), array( $this, 'render_meta_box' ), $post_type, 'side', 'high' );
    }

    /**
     * Meta box content.
     *
     * @param \WP_Post $post
     * @return void
     */
    public function render_meta_box( $post ) {
        if ( 'auto-draft' === $post->post_status ) {
            echo '<p>' . esc_html__( 'Save the post first, then you can design its extra areas with Elementor.', 'mme-elementor-kit' ) . '</p>';
            return;
        }

        foreach ( $this->get_areas() as $name => $area ) {
            $area_id = $this->get_area_id( $post->ID, $name );
            $filled  = $area_id && $this->has_content( $area_id );
            ?>
            <div class="mme-area-box" style="margin-bottom:12px">
                <p style="margin:0 0 6px;display:flex;justify-content:space-between;gap:8px">
                    <strong><?php echo esc_html( $area['label'] ); ?></strong>
                    <span style="color:<?php echo $filled ? '#059669' : '#64748b'; ?>">
                        <?php echo $filled ? esc_html__( 'Has content', 'mme-elementor-kit' ) : esc_html__( 'Empty', 'mme-elementor-kit' ); ?>
                    </span>
                </p>
                <a class="button button-primary" style="width:100%;text-align:center" href="<?php echo esc_url( $this->get_edit_url( $post->ID, $name ) ); ?>">
                    <?php
                    /* translators: %s: area label */
                    printf( esc_html__( 'Edit %s with Elementor', 'mme-elementor-kit' ), esc_html( $area['label'] ) );
                    ?>
                </a>
                <?php if ( $area_id ) : ?>
                    <a href="<?php echo esc_url( $this->get_remove_url( $post->ID, $name ) ); ?>" style="display:inline-block;margin-top:6px;color:#b32d2e"
                        onclick="return confirm('<?php echo esc_js( __( 'Delete this area and its Elementor content? This cannot be undone.', 'mme-elementor-kit' ) ); ?>');">
                        <?php echo esc_html__( 'Delete area', 'mme-elementor-kit' ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php
        }
        if ( count( $this->get_areas() ) ) {
            echo '<p class="description">' . esc_html__( 'Unsaved changes to the post are not kept when you leave this screen.', 'mme-elementor-kit' ) . '</p>';
        }
    }

    /**
     * "Edit Bottom" links in the posts list.
     *
     * @param array    $actions
     * @param \WP_Post $post
     * @return array
     */
    public function row_actions( $actions, $post ) {
        if ( ! $this->supports( $post->post_type ) || 'auto-draft' === $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
            return $actions;
        }
        foreach ( $this->get_areas() as $name => $area ) {
            /* translators: %s: area label */
            $label = sprintf( __( 'Edit %s', 'mme-elementor-kit' ), $area['label'] );
            $actions[ 'mme_area_' . $name ] = '<a href="' . esc_url( $this->get_edit_url( $post->ID, $name ) ) . '">' . esc_html( $label ) . '</a>';
        }
        return $actions;
    }

    /**
     * "Edit Bottom with Elementor" in the admin bar on the front end.
     *
     * @param \WP_Admin_Bar $admin_bar
     * @return void
     */
    public function admin_bar( $admin_bar ) {
        if ( is_admin() || ! is_singular() ) {
            return;
        }
        $post_id = get_queried_object_id();
        if ( ! $this->supports( get_post_type( $post_id ) ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        foreach ( $this->get_areas() as $name => $area ) {
            $admin_bar->add_node( array(
                'id'    => 'mme-area-' . $name,
                /* translators: %s: area label */
                'title' => esc_html( sprintf( __( 'Edit %s with Elementor', 'mme-elementor-kit' ), $area['label'] ) ),
                'href'  => $this->get_edit_url( $post_id, $name ),
            ) );
        }
    }
}
