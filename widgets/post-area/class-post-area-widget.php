<?php
namespace Ems_Elements_Share\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Ems_Elements_Share\Areas\Ems_Areas_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Post Area widget: prints an extra Elementor area (e.g. "Bottom") of the
 * current post. Place it in a Single template where the area should appear.
 */
class Ems_Post_Area_Widget extends Widget_Base {

    use Ems_Field_Source_Trait;

    public function get_name() {
        return 'mme-post-area';
    }

    public function get_title() {
        return __( 'Post Area', 'mme-elementor-kit' );
    }

    public function get_icon() {
        return 'eicon-post-content';
    }

    public function get_categories() {
        return array( Ems_Widgets_Manager::CATEGORY );
    }

    public function get_keywords() {
        return array( 'area', 'bottom', 'content', 'section', 'mme' );
    }

    protected function register_controls() {
        $options = array();
        foreach ( Ems_Areas_Manager::get_instance()->get_areas() as $name => $area ) {
            $options[ $name ] = $area['label'];
        }

        $this->start_controls_section( 'section_area', array(
            'label' => __( 'Area', 'mme-elementor-kit' ),
        ) );

        if ( $options ) {
            $this->add_control( 'area', array(
                'label'   => __( 'Area', 'mme-elementor-kit' ),
                'type'    => Controls_Manager::SELECT,
                'options' => $options,
                'default' => key( $options ),
            ) );
        } else {
            $this->add_control( 'no_areas', array(
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => esc_html__( 'No areas yet. Add one in the widget settings below.', 'mme-elementor-kit' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
            ) );
        }

        $this->add_control( 'preview_post_id', array(
            'label'       => __( 'Editor preview post ID', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'description' => __( 'Only used inside the Elementor editor, to see the area of a real post while designing the template.', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'settings_link', array(
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => sprintf(
                '%s <a href="%s" target="_blank">%s</a>',
                esc_html__( 'Set the area to "Manual" so it only appears where this widget is placed.', 'mme-elementor-kit' ),
                esc_url( admin_url( 'admin.php?page=' . \Ems_Elements_Share_Menu_Handler::WIDGETS_SLUG . '#settings-post-area' ) ),
                esc_html__( 'Area settings ↗', 'mme-elementor-kit' )
            ),
            'content_classes' => 'elementor-descriptor',
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $areas    = Ems_Areas_Manager::get_instance()->get_areas();
        $area     = isset( $settings['area'] ) ? $settings['area'] : '';
        if ( ! isset( $areas[ $area ] ) ) {
            return;
        }

        $is_editor = $this->is_editor();
        $post_id   = ( $is_editor && ! empty( $settings['preview_post_id'] ) ) ? absint( $settings['preview_post_id'] ) : get_the_ID();
        $html      = $post_id ? Ems_Areas_Manager::get_instance()->render( $post_id, $area ) : '';

        if ( '' === $html && $is_editor ) {
            /* translators: %s: area label */
            $message = sprintf( __( 'The "%s" area of the current post appears here.', 'mme-elementor-kit' ), $areas[ $area ]['label'] );
            echo '<div style="padding:24px;text-align:center;color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:8px">' . esc_html( $message ) . '</div>';
            return;
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor output.
    }
}
