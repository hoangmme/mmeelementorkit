<?php
namespace Ems_Elements_Share\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Info Table widget: a two column label / value table whose values come from
 * ACF (or SCF) fields, post meta, shortcodes or Elementor dynamic tags.
 */
class Ems_Info_Table_Widget extends Widget_Base {

    public function get_name() {
        return 'mme-info-table';
    }

    public function get_title() {
        return __( 'Info Table (ACF)', 'mme-elementor-kit' );
    }

    public function get_icon() {
        return 'eicon-table-of-contents';
    }

    public function get_categories() {
        return array( Ems_Widgets_Manager::CATEGORY );
    }

    public function get_keywords() {
        return array( 'table', 'info', 'acf', 'scf', 'field', 'meta', 'mme' );
    }

    public function get_style_depends() {
        return array( 'mme-info-table' );
    }

    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Content tab controls.
     *
     * @return void
     */
    private function register_content_controls() {
        $acf_fields = $this->get_acf_field_options();

        $this->start_controls_section( 'section_rows', array(
            'label' => __( 'Rows', 'mme-elementor-kit' ),
        ) );

        $repeater = new Repeater();

        $repeater->add_control( 'label', array(
            'label'       => __( 'Label', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => __( 'Label', 'mme-elementor-kit' ),
            'label_block' => true,
            'dynamic'     => array( 'active' => true ),
        ) );

        $repeater->add_control( 'source', array(
            'label'   => __( 'Value source', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'acf',
            'options' => array(
                'acf'       => __( 'ACF / SCF field', 'mme-elementor-kit' ),
                'meta'      => __( 'Custom field (post meta)', 'mme-elementor-kit' ),
                'text'      => __( 'Text / Dynamic tag', 'mme-elementor-kit' ),
                'shortcode' => __( 'Shortcode', 'mme-elementor-kit' ),
            ),
        ) );

        if ( count( $acf_fields ) > 1 ) {
            $repeater->add_control( 'acf_field', array(
                'label'       => __( 'Field', 'mme-elementor-kit' ),
                'type'        => Controls_Manager::SELECT,
                'options'     => $acf_fields,
                'default'     => '',
                'label_block' => true,
                'condition'   => array( 'source' => 'acf' ),
            ) );
        }

        $repeater->add_control( 'field_name', array(
            'label'       => __( 'Field name', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'location',
            'label_block' => true,
            'description' => count( $acf_fields ) > 1 ? __( 'Overrides the field picked above.', 'mme-elementor-kit' ) : __( 'The field name (meta key), e.g. establish_year.', 'mme-elementor-kit' ),
            'condition'   => array( 'source' => array( 'acf', 'meta' ) ),
        ) );

        $repeater->add_control( 'value', array(
            'label'       => __( 'Value', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 2,
            'placeholder' => '[scf field="location"]',
            'dynamic'     => array( 'active' => true ),
            'condition'   => array( 'source' => array( 'text', 'shortcode' ) ),
        ) );

        $repeater->add_control( 'before', array(
            'label'     => __( 'Before value', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::TEXT,
            'separator' => 'before',
        ) );

        $repeater->add_control( 'after', array(
            'label' => __( 'After value', 'mme-elementor-kit' ),
            'type'  => Controls_Manager::TEXT,
        ) );

        $repeater->add_control( 'fallback', array(
            'label'       => __( 'Fallback', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'description' => __( 'Shown when the value is empty.', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'rows', array(
            'label'       => __( 'Rows', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ label }}}',
            'default'     => array(
                array( 'label' => __( 'Location', 'mme-elementor-kit' ), 'source' => 'acf', 'field_name' => 'location' ),
                array( 'label' => __( 'Establish year', 'mme-elementor-kit' ), 'source' => 'acf', 'field_name' => 'establish_year' ),
                array( 'label' => __( 'Admission grade', 'mme-elementor-kit' ), 'source' => 'acf', 'field_name' => 'admission_grade' ),
                array( 'label' => __( 'Proportion of Korean Students', 'mme-elementor-kit' ), 'source' => 'acf', 'field_name' => 'proportion_of_korean_students' ),
                array( 'label' => __( 'Total number of students', 'mme-elementor-kit' ), 'source' => 'acf', 'field_name' => 'total_number_of_students' ),
                array( 'label' => __( 'Class Size', 'mme-elementor-kit' ), 'source' => 'acf', 'field_name' => 'class_size' ),
            ),
        ) );

        $this->end_controls_section();

        $this->start_controls_section( 'section_data', array(
            'label' => __( 'Data source', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'post_source', array(
            'label'   => __( 'Read fields from', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'current',
            'options' => array(
                'current' => __( 'Current post', 'mme-elementor-kit' ),
                'custom'  => __( 'Specific post ID', 'mme-elementor-kit' ),
                'term'    => __( 'Current term (archive)', 'mme-elementor-kit' ),
                'option'  => __( 'Options page', 'mme-elementor-kit' ),
            ),
        ) );

        $this->add_control( 'custom_post_id', array(
            'label'     => __( 'Post ID', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'dynamic'   => array( 'active' => true ),
            'condition' => array( 'post_source' => 'custom' ),
        ) );

        $this->add_control( 'preview_post_id', array(
            'label'       => __( 'Editor preview post ID', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'description' => __( 'Only used inside the Elementor editor, e.g. when designing a single template.', 'mme-elementor-kit' ),
            'condition'   => array( 'post_source' => 'current' ),
        ) );

        $this->add_control( 'hide_empty', array(
            'label'        => __( 'Hide empty rows', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'separator'    => 'before',
        ) );

        $this->add_control( 'stack_mobile', array(
            'label'        => __( 'Stack on mobile', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => '',
            'return_value' => 'yes',
            'description'  => __( 'Show label above value on small screens.', 'mme-elementor-kit' ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Style tab controls.
     *
     * @return void
     */
    private function register_style_controls() {
        $this->start_controls_section( 'section_style_table', array(
            'label' => __( 'Table', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'table_background', array(
            'label'     => __( 'Background', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .mme-info-table-wrap' => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'table_border',
            'selector' => '{{WRAPPER}} .mme-info-table-wrap',
        ) );

        $this->add_responsive_control( 'table_radius', array(
            'label'      => __( 'Border radius', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array( '{{WRAPPER}} .mme-info-table-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'table_shadow',
            'selector' => '{{WRAPPER}} .mme-info-table-wrap',
        ) );

        $this->add_responsive_control( 'label_width', array(
            'label'      => __( 'Label column width', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( '%' ),
            'range'      => array( '%' => array( 'min' => 10, 'max' => 90 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .mme-info-table th' => 'width: {{SIZE}}%;',
                '{{WRAPPER}} .mme-info-table td' => 'width: calc(100% - {{SIZE}}%);',
            ),
        ) );

        $this->end_controls_section();

        $this->start_controls_section( 'section_style_rows', array(
            'label' => __( 'Rows', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'row_divider_color', array(
            'label'     => __( 'Divider color', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .mme-info-table tr' => 'border-bottom-color: {{VALUE}};' ),
        ) );

        $this->add_control( 'row_hover_background', array(
            'label'     => __( 'Hover background', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .mme-info-table tr:hover' => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_control( 'row_striped', array(
            'label'        => __( 'Striped rows', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'prefix_class' => 'mme-info-table--striped-',
        ) );

        $this->add_responsive_control( 'cell_padding', array(
            'label'      => __( 'Cell padding', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .mme-info-table th, {{WRAPPER}} .mme-info-table td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();

        $this->register_cell_style_section( 'label', __( 'Label', 'mme-elementor-kit' ), 'th' );
        $this->register_cell_style_section( 'value', __( 'Value', 'mme-elementor-kit' ), 'td' );
    }

    /**
     * Style section for the label (th) or value (td) cells.
     *
     * @param string $prefix
     * @param string $title
     * @param string $tag
     * @return void
     */
    private function register_cell_style_section( $prefix, $title, $tag ) {
        $selector = '{{WRAPPER}} .mme-info-table ' . $tag;

        $this->start_controls_section( 'section_style_' . $prefix, array(
            'label' => $title,
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( $prefix . '_color', array(
            'label'     => __( 'Text color', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( $selector => 'color: {{VALUE}};' ),
        ) );

        $this->add_control( $prefix . '_background', array(
            'label'     => __( 'Background', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => $prefix . '_typography',
            'selector' => $selector,
        ) );

        $this->add_responsive_control( $prefix . '_align', array(
            'label'     => __( 'Alignment', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => __( 'Left', 'mme-elementor-kit' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'mme-elementor-kit' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'mme-elementor-kit' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors' => array( $selector => 'text-align: {{VALUE}};' ),
        ) );

        if ( 'value' === $prefix ) {
            $this->add_control( 'value_link_color', array(
                'label'     => __( 'Link color', 'mme-elementor-kit' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array( $selector . ' a' => 'color: {{VALUE}};' ),
            ) );
        }

        $this->end_controls_section();
    }

    /**
     * ACF fields grouped by field group, for the field picker.
     *
     * @return array
     */
    private function get_acf_field_options() {
        $options = array( '' => __( '— Select a field —', 'mme-elementor-kit' ) );

        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return $options;
        }

        // Layout and nested fields don't map to a single table cell.
        $skip_types = array( 'tab', 'message', 'accordion', 'group', 'repeater', 'flexible_content', 'clone' );

        foreach ( acf_get_field_groups() as $group ) {
            $fields = acf_get_fields( $group );
            if ( ! is_array( $fields ) ) {
                continue;
            }
            foreach ( $fields as $field ) {
                if ( empty( $field['name'] ) || in_array( $field['type'], $skip_types, true ) ) {
                    continue;
                }
                $options[ $field['name'] ] = sprintf( '%s › %s (%s)', $group['title'], $field['label'], $field['name'] );
            }
        }

        return $options;
    }

    /**
     * Whether we are rendering inside the Elementor editor.
     *
     * @return boolean
     */
    private function is_editor() {
        $plugin = \Elementor\Plugin::instance();
        return $plugin->editor->is_edit_mode() || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
    }

    /**
     * Resolve the object the fields are read from, in ACF's post_id format.
     *
     * @param array $settings
     * @return int|string
     */
    private function get_target_id( $settings ) {
        switch ( $settings['post_source'] ) {
            case 'option':
                return 'option';

            case 'term':
                $term = get_queried_object();
                if ( $term instanceof \WP_Term ) {
                    return 'term_' . $term->term_id;
                }
                return 0;

            case 'custom':
                if ( ! empty( $settings['custom_post_id'] ) ) {
                    return absint( $settings['custom_post_id'] );
                }
                break;

            default:
                if ( ! empty( $settings['preview_post_id'] ) && $this->is_editor() ) {
                    return absint( $settings['preview_post_id'] );
                }
                break;
        }

        return get_the_ID();
    }

    /**
     * Get the field name of a row.
     *
     * @param array $row
     * @return string
     */
    private function get_row_field_name( $row ) {
        $name = isset( $row['field_name'] ) ? trim( $row['field_name'] ) : '';
        if ( '' === $name && 'acf' === $row['source'] && ! empty( $row['acf_field'] ) ) {
            $name = $row['acf_field'];
        }
        return $name;
    }

    /**
     * Get the formatted (HTML safe) value of a row.
     *
     * @param array      $row
     * @param int|string $target_id
     * @return string
     */
    private function get_row_value( $row, $target_id ) {
        switch ( $row['source'] ) {
            case 'text':
                return wp_kses_post( $row['value'] );

            case 'shortcode':
                return wp_kses_post( do_shortcode( $row['value'] ) );
        }

        $name = $this->get_row_field_name( $row );
        if ( '' === $name || ! $target_id ) {
            return '';
        }

        if ( 'acf' === $row['source'] && function_exists( 'get_field' ) ) {
            $field = function_exists( 'get_field_object' ) ? get_field_object( $name, $target_id, false, false ) : false;
            return $this->format_value( get_field( $name, $target_id ), is_array( $field ) ? $field : array() );
        }

        // Plain meta, also the fallback when ACF / SCF is not active.
        if ( 'option' === $target_id ) {
            $value = get_option( 'options_' . $name, '' );
        } elseif ( is_string( $target_id ) && 0 === strpos( $target_id, 'term_' ) ) {
            $value = get_term_meta( (int) substr( $target_id, 5 ), $name, true );
        } else {
            $value = get_post_meta( $target_id, $name, true );
        }

        return $this->format_value( $value );
    }

    /**
     * Turn any ACF return value into HTML safe output.
     *
     * @param mixed $value
     * @param array $field ACF field settings, when known.
     * @return string
     */
    private function format_value( $value, $field = array() ) {
        $type = isset( $field['type'] ) ? $field['type'] : '';

        if ( 'true_false' === $type ) {
            return $value ? esc_html__( 'Yes', 'mme-elementor-kit' ) : esc_html__( 'No', 'mme-elementor-kit' );
        }

        if ( null === $value || false === $value || '' === $value || array() === $value ) {
            return '';
        }

        if ( true === $value ) {
            return esc_html__( 'Yes', 'mme-elementor-kit' );
        }

        if ( $value instanceof \WP_Post ) {
            return sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $value ) ), esc_html( get_the_title( $value ) ) );
        }

        if ( $value instanceof \WP_Term ) {
            $link = get_term_link( $value );
            return is_wp_error( $link ) ? esc_html( $value->name ) : sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html( $value->name ) );
        }

        if ( $value instanceof \WP_User ) {
            return esc_html( $value->display_name );
        }

        if ( is_array( $value ) ) {
            return $this->format_array_value( $value, $field );
        }

        if ( 'image' === $type && is_numeric( $value ) ) {
            return wp_get_attachment_image( (int) $value, 'medium' );
        }

        // Select / radio / checkbox fields returning the raw value: show the choice label.
        if ( ( is_string( $value ) || is_int( $value ) ) && isset( $field['choices'][ $value ] ) && is_scalar( $field['choices'][ $value ] ) ) {
            return esc_html( $field['choices'][ $value ] );
        }

        return wp_kses_post( (string) $value );
    }

    /**
     * Format array return values (link, image, file, select with label, multiple values).
     *
     * @param array $value
     * @param array $field ACF field settings, when known.
     * @return string
     */
    private function format_array_value( $value, $field ) {
        $type = isset( $field['type'] ) ? $field['type'] : '';

        // Image array.
        if ( isset( $value['sizes'] ) || ( 'image' === $type && isset( $value['url'] ) ) ) {
            $id = isset( $value['ID'] ) ? (int) $value['ID'] : ( isset( $value['id'] ) ? (int) $value['id'] : 0 );
            $image = $id ? wp_get_attachment_image( $id, 'medium' ) : '';
            if ( ! $image ) {
                $alt   = isset( $value['alt'] ) ? $value['alt'] : '';
                $image = sprintf( '<img src="%s" alt="%s">', esc_url( $value['url'] ), esc_attr( $alt ) );
            }
            return $image;
        }

        // Link array.
        if ( isset( $value['url'] ) && array_key_exists( 'title', $value ) && array_key_exists( 'target', $value ) ) {
            $title = '' !== $value['title'] ? $value['title'] : $value['url'];
            $target = $value['target'] ? $value['target'] : '_self';
            return sprintf( '<a href="%s" target="%s">%s</a>', esc_url( $value['url'] ), esc_attr( $target ), esc_html( $title ) );
        }

        // File array.
        if ( 'file' === $type && isset( $value['url'] ) ) {
            $title = ! empty( $value['title'] ) ? $value['title'] : basename( $value['url'] );
            return sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $value['url'] ), esc_html( $title ) );
        }

        // Select / radio returning both value and label.
        if ( isset( $value['label'] ) && array_key_exists( 'value', $value ) ) {
            return esc_html( $value['label'] );
        }

        // Google map.
        if ( isset( $value['address'] ) && is_string( $value['address'] ) ) {
            return esc_html( $value['address'] );
        }

        // Multiple values (checkbox, multi select, relationship, taxonomy...).
        $item_field = isset( $field['choices'] ) ? array( 'choices' => $field['choices'] ) : array();
        $parts      = array();
        foreach ( $value as $item ) {
            $formatted = $this->format_value( $item, $item_field );
            if ( '' !== $formatted ) {
                $parts[] = $formatted;
            }
        }
        return implode( ', ', $parts );
    }

    protected function render() {
        $settings  = $this->get_settings_for_display();
        $rows      = isset( $settings['rows'] ) && is_array( $settings['rows'] ) ? $settings['rows'] : array();
        $target_id = $this->get_target_id( $settings );
        $is_editor = $this->is_editor();
        $output    = '';

        $row_defaults = array(
            'label'      => '',
            'source'     => 'acf',
            'acf_field'  => '',
            'field_name' => '',
            'value'      => '',
            'before'     => '',
            'after'      => '',
            'fallback'   => '',
        );

        foreach ( $rows as $row ) {
            $row   = wp_parse_args( $row, $row_defaults );
            $value = $this->get_row_value( $row, $target_id );

            if ( '' === $value && '' !== (string) $row['fallback'] ) {
                $value = esc_html( $row['fallback'] );
            } elseif ( '' !== $value ) {
                $value = esc_html( $row['before'] ) . $value . esc_html( $row['after'] );
            }

            if ( '' === $value ) {
                if ( ! $is_editor && 'yes' === $settings['hide_empty'] ) {
                    continue;
                }
                // Show which field is missing so the row stays visible while designing.
                if ( $is_editor ) {
                    $name  = $this->get_row_field_name( $row );
                    $value = '<span class="mme-info-table__placeholder">' . esc_html( $name ? '{' . $name . '}' : __( '(empty)', 'mme-elementor-kit' ) ) . '</span>';
                }
            }

            $output .= '<tr>';
            $output .= '<th scope="row">' . wp_kses_post( $row['label'] ) . '</th>';
            $output .= '<td>' . $value . '</td>';
            $output .= '</tr>';
        }

        if ( '' === $output ) {
            return;
        }

        $wrap_class = 'mme-info-table-wrap';
        if ( 'yes' === $settings['stack_mobile'] ) {
            $wrap_class .= ' mme-info-table-wrap--stack';
        }
        ?>
        <div class="<?php echo esc_attr( $wrap_class ); ?>">
            <table class="mme-info-table">
                <tbody>
                    <?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while building rows. ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
