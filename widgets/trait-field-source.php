<?php
namespace Ems_Elements_Share\Widgets;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared by widgets that read ACF / SCF fields or post meta:
 * the "Read fields from" controls, the ACF field picker and value lookup.
 */
trait Ems_Field_Source_Trait {

    /**
     * Add the post_source / custom_post_id / preview_post_id controls
     * to the current controls section.
     *
     * @return void
     */
    protected function register_post_source_controls() {
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
    }

    /**
     * ACF fields grouped by field group, for the field picker.
     *
     * @param array|null $only_types Limit to these field types, null for every simple field.
     * @return array
     */
    protected function get_acf_field_options( $only_types = null ) {
        $options = array( '' => __( '— Select a field —', 'mme-elementor-kit' ) );

        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return $options;
        }

        // Layout and nested fields don't map to a single value.
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
                if ( is_array( $only_types ) && ! in_array( $field['type'], $only_types, true ) ) {
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
    protected function is_editor() {
        $plugin = \Elementor\Plugin::instance();
        return $plugin->editor->is_edit_mode() || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
    }

    /**
     * Resolve the object the fields are read from, in ACF's post_id format.
     *
     * @param array $settings
     * @return int|string
     */
    protected function get_target_id( $settings ) {
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
     * Read a value as plain meta. Also the fallback when ACF / SCF is not active.
     *
     * @param string     $name
     * @param int|string $target_id
     * @return mixed
     */
    protected function get_meta_value( $name, $target_id ) {
        if ( 'option' === $target_id ) {
            return get_option( 'options_' . $name, '' );
        }
        if ( is_string( $target_id ) && 0 === strpos( $target_id, 'term_' ) ) {
            return get_term_meta( (int) substr( $target_id, 5 ), $name, true );
        }
        return get_post_meta( $target_id, $name, true );
    }
}
