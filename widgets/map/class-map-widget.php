<?php
namespace Ems_Elements_Share\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-map-location.php';

/**
 * Map widget: Google Maps embed without an API key. The location comes from an
 * ACF / SCF field, post meta or text (address, "lat,lng" or a Google Maps link).
 */
class Ems_Map_Widget extends Widget_Base {

    use Ems_Field_Source_Trait;

    public function get_name() {
        return 'mme-map';
    }

    public function get_title() {
        return __( 'Map (no API key)', 'mme-elementor-kit' );
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_categories() {
        return array( Ems_Widgets_Manager::CATEGORY );
    }

    public function get_keywords() {
        return array( 'map', 'google', 'location', 'address', 'acf', 'scf', 'mme' );
    }

    public function get_style_depends() {
        return array( 'mme-map' );
    }

    protected function register_controls() {
        $this->register_location_controls();
        $this->register_map_controls();
        $this->register_style_controls();
    }

    /**
     * Where the location comes from.
     *
     * @return void
     */
    private function register_location_controls() {
        $acf_fields = $this->get_acf_field_options();

        $this->start_controls_section( 'section_location', array(
            'label' => __( 'Location', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'source', array(
            'label'   => __( 'Location source', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'acf',
            'options' => array(
                'acf'  => __( 'ACF / SCF field', 'mme-elementor-kit' ),
                'meta' => __( 'Custom field (post meta)', 'mme-elementor-kit' ),
                'text' => __( 'Address / coordinates / link', 'mme-elementor-kit' ),
            ),
        ) );

        if ( count( $acf_fields ) > 1 ) {
            $this->add_control( 'acf_field', array(
                'label'       => __( 'Field', 'mme-elementor-kit' ),
                'type'        => Controls_Manager::SELECT,
                'options'     => $acf_fields,
                'default'     => '',
                'label_block' => true,
                'condition'   => array( 'source' => 'acf' ),
            ) );
        }

        $this->add_control( 'field_name', array(
            'label'       => __( 'Field name', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'map',
            'placeholder' => 'map',
            'label_block' => true,
            'description' => __( 'The field can hold a Google Maps link, "lat,lng", an address, or be an ACF Google Map / Link field.', 'mme-elementor-kit' ),
            'condition'   => array( 'source' => array( 'acf', 'meta' ) ),
        ) );

        $this->add_control( 'location', array(
            'label'       => __( 'Location', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 2,
            'default'     => 'Hồ Hoàn Kiếm, Hà Nội',
            'placeholder' => '21.0287,105.8524  /  https://maps.app.goo.gl/…',
            'dynamic'     => array( 'active' => true ),
            'condition'   => array( 'source' => 'text' ),
        ) );

        $this->add_control( 'fallback', array(
            'label'       => __( 'Fallback location', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __( 'Used when the field is empty. Leave empty to hide the map.', 'mme-elementor-kit' ),
            'condition'   => array( 'source' => array( 'acf', 'meta' ) ),
        ) );

        $this->register_post_source_controls();

        $this->end_controls_section();
    }

    /**
     * Map options.
     *
     * @return void
     */
    private function register_map_controls() {
        $this->start_controls_section( 'section_map', array(
            'label' => __( 'Map', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'zoom', array(
            'label'   => __( 'Zoom', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SLIDER,
            'default' => array( 'size' => 15 ),
            'range'   => array( 'px' => array( 'min' => 1, 'max' => 21 ) ),
        ) );

        $this->add_control( 'map_type', array(
            'label'   => __( 'Map type', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'm',
            'options' => array(
                'm' => __( 'Road map', 'mme-elementor-kit' ),
                'k' => __( 'Satellite', 'mme-elementor-kit' ),
            ),
        ) );

        $this->add_control( 'language', array(
            'label'       => __( 'Language', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'vi',
            'description' => __( 'Two-letter code, e.g. vi, en, ko. Empty uses the visitor\'s language.', 'mme-elementor-kit' ),
        ) );

        $this->add_responsive_control( 'height', array(
            'label'      => __( 'Height', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'vh' ),
            'default'    => array( 'size' => 400, 'unit' => 'px' ),
            'range'      => array(
                'px' => array( 'min' => 150, 'max' => 1000 ),
                'vh' => array( 'min' => 10, 'max' => 100 ),
            ),
            'selectors'  => array( '{{WRAPPER}} .mme-map__frame' => 'height: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->add_control( 'show_link', array(
            'label'        => __( 'Show "Open in Google Maps" link', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'separator'    => 'before',
        ) );

        $this->add_control( 'link_text', array(
            'label'     => __( 'Link text', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::TEXT,
            'default'   => __( 'Open in Google Maps', 'mme-elementor-kit' ),
            'condition' => array( 'show_link' => 'yes' ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Style tab controls.
     *
     * @return void
     */
    private function register_style_controls() {
        $this->start_controls_section( 'section_style_map', array(
            'label' => __( 'Map', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'map_radius', array(
            'label'      => __( 'Border radius', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array( '{{WRAPPER}} .mme-map__frame' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'map_border',
            'selector' => '{{WRAPPER}} .mme-map__frame',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'map_shadow',
            'selector' => '{{WRAPPER}} .mme-map__frame',
        ) );

        $this->add_control( 'map_grayscale', array(
            'label'     => __( 'Grayscale', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
            'selectors' => array( '{{WRAPPER}} .mme-map__frame' => 'filter: grayscale({{SIZE}}%);' ),
        ) );

        $this->end_controls_section();

        $this->start_controls_section( 'section_style_link', array(
            'label'     => __( 'Link', 'mme-elementor-kit' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_link' => 'yes' ),
        ) );

        $this->add_control( 'link_color', array(
            'label'     => __( 'Color', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .mme-map__link' => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'link_typography',
            'selector' => '{{WRAPPER}} .mme-map__link',
        ) );

        $this->add_responsive_control( 'link_align', array(
            'label'     => __( 'Alignment', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => __( 'Left', 'mme-elementor-kit' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'mme-elementor-kit' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'mme-elementor-kit' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors' => array( '{{WRAPPER}} .mme-map__footer' => 'text-align: {{VALUE}};' ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Raw location value from the chosen source.
     *
     * @param array $settings
     * @return array { value: mixed, label: string } label names the field for the editor placeholder.
     */
    private function get_location_value( $settings ) {
        if ( 'text' === $settings['source'] ) {
            return array( 'value' => $settings['location'], 'label' => '' );
        }

        $name = isset( $settings['field_name'] ) ? trim( $settings['field_name'] ) : '';
        if ( '' === $name && 'acf' === $settings['source'] && ! empty( $settings['acf_field'] ) ) {
            $name = $settings['acf_field'];
        }

        $target_id = $this->get_target_id( $settings );
        if ( '' === $name || ! $target_id ) {
            return array( 'value' => '', 'label' => $name );
        }

        if ( 'acf' === $settings['source'] && function_exists( 'get_field' ) ) {
            return array( 'value' => get_field( $name, $target_id ), 'label' => $name );
        }
        return array( 'value' => $this->get_meta_value( $name, $target_id ), 'label' => $name );
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $source   = $this->get_location_value( $settings );
        $location = Ems_Map_Location::parse( $source['value'] );

        if ( ! $location && ! empty( $settings['fallback'] ) && 'text' !== $settings['source'] ) {
            $location = Ems_Map_Location::parse( $settings['fallback'] );
        }

        if ( ! $location ) {
            if ( $this->is_editor() ) {
                $hint = $source['label']
                    /* translators: %s: field name */
                    ? sprintf( __( 'No location found in field "%s" for this post.', 'mme-elementor-kit' ), $source['label'] )
                    : __( 'Enter an address, coordinates or a Google Maps link.', 'mme-elementor-kit' );
                echo '<div class="mme-map__placeholder">' . esc_html( $hint ) . '</div>';
            }
            return;
        }

        $zoom     = isset( $settings['zoom']['size'] ) ? (int) $settings['zoom']['size'] : 15;
        $language = isset( $settings['language'] ) ? trim( $settings['language'] ) : '';
        ?>
        <div class="mme-map">
            <iframe class="mme-map__frame"
                src="<?php echo esc_url( Ems_Map_Location::embed_url( $location, $zoom, $settings['map_type'], $language ) ); ?>"
                title="<?php echo esc_attr( $location['query'] ); ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen></iframe>
            <?php if ( 'yes' === $settings['show_link'] ) : ?>
                <div class="mme-map__footer">
                    <a class="mme-map__link" href="<?php echo esc_url( Ems_Map_Location::open_url( $location ) ); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html( $settings['link_text'] ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
