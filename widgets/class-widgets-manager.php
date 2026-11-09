<?php
namespace Ems_Elements_Share\Widgets;

/**
 * Class responsible for registering the custom Elementor widgets.
 */
class Ems_Widgets_Manager {

    /**
     * Widget category slug shown in the Elementor panel.
     */
    const CATEGORY = 'mme-widgets';

    /**
     * Option holding the keys of disabled widgets.
     */
    const DISABLED_OPTION = 'mme_kit_disabled_widgets';

    /**
     * Notice shown after saving: array( type, message ).
     *
     * @var array|null
     */
    private static $notice = null;

    public function __construct() {
        // Only fires when Elementor is active.
        add_action( 'elementor/init', array( $this, 'init' ) );

        // Widgets admin page.
        add_action( 'admin_init', array( $this, 'handle_form' ) );
        add_action( 'ems_elements_share_widgets_notice', array( $this, 'add_notice' ) );
    }

    /**
     * All widgets shipped with the plugin.
     *
     * Add new widgets here: the Widgets admin page and the registration both read this list.
     *
     * @return array
     */
    public static function get_widgets() {
        return array(
            'info-table' => array(
                'class'       => __NAMESPACE__ . '\Ems_Info_Table_Widget',
                'file'        => __DIR__ . '/info-table/class-info-table-widget.php',
                'name'        => 'mme-info-table',
                'title'       => __( 'Info Table (ACF)', 'mme-elementor-kit' ),
                'description' => __( 'Two column label / value table. Values come from ACF or SCF fields, post meta, shortcodes or Elementor dynamic tags. Empty rows are hidden automatically.', 'mme-elementor-kit' ),
                'icon'        => 'dashicons-editor-table',
                'tags'        => array( __( 'ACF / SCF', 'mme-elementor-kit' ), __( 'Post meta', 'mme-elementor-kit' ), __( 'Dynamic tags', 'mme-elementor-kit' ) ),
                'usage'       => __( 'Add it to a single post template, pick a field for each row, then style it from the Style tab.', 'mme-elementor-kit' ),
            ),
            'map'        => array(
                'class'       => __NAMESPACE__ . '\Ems_Map_Widget',
                'file'        => __DIR__ . '/map/class-map-widget.php',
                'name'        => 'mme-map',
                'title'       => __( 'Map (no API key)', 'mme-elementor-kit' ),
                'description' => __( 'Google Maps embed without an API key. The location can be an address, "lat,lng", a Google Maps link (including maps.app.goo.gl) or an ACF Google Map / Link field.', 'mme-elementor-kit' ),
                'icon'        => 'dashicons-location-alt',
                'tags'        => array( __( 'No API key', 'mme-elementor-kit' ), __( 'ACF / SCF', 'mme-elementor-kit' ), __( 'Google Maps link', 'mme-elementor-kit' ) ),
                'usage'       => __( 'Store the Google Maps link of each place in a field (e.g. "map"), then point the widget at that field.', 'mme-elementor-kit' ),
            ),
        );
    }

    /**
     * Whether a widget is enabled.
     *
     * @param string $key
     * @return boolean
     */
    public static function is_enabled( $key ) {
        $disabled = get_option( self::DISABLED_OPTION, array() );
        return ! in_array( $key, is_array( $disabled ) ? $disabled : array(), true );
    }

    /**
     * Setup hooks once Elementor is loaded.
     *
     * @return void
     */
    public function init() {
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
        add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_styles' ) );

        // Elementor 3.5+ uses `register`, older versions `widgets_registered`.
        if ( version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
            add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        } else {
            add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets_legacy' ) );
        }
    }

    /**
     * Add the MMe widget category.
     *
     * @param \Elementor\Elements_Manager $elements_manager
     * @return void
     */
    public function register_category( $elements_manager ) {
        $elements_manager->add_category( self::CATEGORY, array(
            'title' => __( 'MMe Widgets', 'mme-elementor-kit' ),
            'icon'  => 'fa fa-plug',
        ) );
    }

    /**
     * Register widget stylesheets so widgets can declare them in get_style_depends().
     *
     * @return void
     */
    public function register_styles() {
        wp_register_style( 'mme-info-table', EMS_ELEMENTS_SHARE_URL . 'widgets/assets/css/info-table.css', array(), EMS_ELEMENTS_SHARE_VERSION );
        wp_register_style( 'mme-map', EMS_ELEMENTS_SHARE_URL . 'widgets/assets/css/map.css', array(), EMS_ELEMENTS_SHARE_VERSION );
    }

    /**
     * Instances of the enabled widgets. Widget classes extend Elementor classes,
     * so they can only be required once Elementor is loaded.
     *
     * @return array
     */
    private function get_enabled_instances() {
        require_once __DIR__ . '/trait-field-source.php';

        $instances = array();
        foreach ( self::get_widgets() as $key => $widget ) {
            if ( ! self::is_enabled( $key ) ) {
                continue;
            }
            require_once $widget['file'];
            $instances[] = new $widget['class']();
        }
        return $instances;
    }

    /**
     * Register widgets (Elementor 3.5+).
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     * @return void
     */
    public function register_widgets( $widgets_manager ) {
        foreach ( $this->get_enabled_instances() as $widget ) {
            $widgets_manager->register( $widget );
        }
    }

    /**
     * Register widgets (Elementor < 3.5).
     *
     * @return void
     */
    public function register_widgets_legacy() {
        foreach ( $this->get_enabled_instances() as $widget ) {
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( $widget );
        }
    }

    /**
     * Save the enabled widgets from the Widgets admin page.
     *
     * @return void
     */
    public function handle_form() {
        if ( ! ( isset( $_POST['action'] ) && $_POST['action'] === 'mme_kit_save_widgets' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_save_widgets', '_wp_nonce_widgets' );

        $enabled  = isset( $_POST['mme_kit_widgets'] ) && is_array( $_POST['mme_kit_widgets'] ) ? array_map( 'sanitize_key', $_POST['mme_kit_widgets'] ) : array();
        $disabled = array_values( array_diff( array_keys( self::get_widgets() ), $enabled ) );
        update_option( self::DISABLED_OPTION, $disabled );

        self::$notice = array( 'success', __( 'Widgets saved.', 'mme-elementor-kit' ) );
    }

    /**
     * Display the save result.
     *
     * @return void
     */
    public function add_notice() {
        if ( self::$notice === null ) {
            return;
        }
        list( $type, $message ) = self::$notice;
        ?>
        <div class="ems-alert ems-alert--<?php echo esc_attr( $type ); ?>">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php echo esc_html( $message ); ?>
        </div>
        <?php
    }
}
