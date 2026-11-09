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

    public function __construct() {
        // Only fires when Elementor is active.
        add_action( 'elementor/init', array( $this, 'init' ) );
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
    }

    /**
     * Load widget classes. They extend Elementor classes, so they can only be
     * required once Elementor is loaded.
     *
     * @return void
     */
    private function include_widgets() {
        require_once __DIR__ . '/info-table/class-info-table-widget.php';
    }

    /**
     * Register widgets (Elementor 3.5+).
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     * @return void
     */
    public function register_widgets( $widgets_manager ) {
        $this->include_widgets();
        $widgets_manager->register( new Ems_Info_Table_Widget() );
    }

    /**
     * Register widgets (Elementor < 3.5).
     *
     * @return void
     */
    public function register_widgets_legacy() {
        $this->include_widgets();
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Ems_Info_Table_Widget() );
    }
}
