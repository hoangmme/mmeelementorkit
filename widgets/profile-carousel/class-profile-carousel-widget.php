<?php
namespace Ems_Elements_Share\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Profile Carousel widget: one large slide (image + name, role, bio, button)
 * with a thumbnail strip (image + name) to switch between people.
 * Items come from a repeater or from posts (featured image, title, ACF / meta field).
 */
class Ems_Profile_Carousel_Widget extends Widget_Base {

    use Ems_Field_Source_Trait;

    public function get_name() {
        return 'mme-profile-carousel';
    }

    public function get_title() {
        return __( 'Profile Carousel', 'mme-elementor-kit' );
    }

    public function get_icon() {
        return 'eicon-slider-push';
    }

    public function get_categories() {
        return array( Ems_Widgets_Manager::CATEGORY );
    }

    public function get_keywords() {
        return array( 'carousel', 'slider', 'team', 'profile', 'member', 'testimonial', 'mme' );
    }

    public function get_style_depends() {
        return array( 'mme-profile-carousel' );
    }

    public function get_script_depends() {
        return array( 'mme-profile-carousel' );
    }

    protected function register_controls() {
        $this->register_items_controls();
        $this->register_layout_controls();
        $this->register_carousel_controls();
        $this->register_style_controls();
    }

    /**
     * Where the slides come from.
     *
     * @return void
     */
    private function register_items_controls() {
        $this->start_controls_section( 'section_items', array(
            'label' => __( 'Items', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'source', array(
            'label'   => __( 'Source', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'manual',
            'options' => array(
                'manual' => __( 'Manual items', 'mme-elementor-kit' ),
                'posts'  => __( 'Posts (featured image, title, fields)', 'mme-elementor-kit' ),
            ),
        ) );

        $repeater = new Repeater();

        $repeater->add_control( 'image', array(
            'label'   => __( 'Image', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::MEDIA,
            'default' => array( 'url' => Utils::get_placeholder_image_src() ),
            'dynamic' => array( 'active' => true ),
        ) );

        $repeater->add_control( 'name', array(
            'label'       => __( 'Name', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'dynamic'     => array( 'active' => true ),
        ) );

        $repeater->add_control( 'role', array(
            'label'       => __( 'Role / position', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'dynamic'     => array( 'active' => true ),
        ) );

        $repeater->add_control( 'bio', array(
            'label'   => __( 'Description', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::WYSIWYG,
            'dynamic' => array( 'active' => true ),
        ) );

        $repeater->add_control( 'button_text', array(
            'label'   => __( 'Button text', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Tìm hiểu thêm',
            'dynamic' => array( 'active' => true ),
        ) );

        $repeater->add_control( 'link', array(
            'label'       => __( 'Button link', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::URL,
            'placeholder' => 'https://',
            'dynamic'     => array( 'active' => true ),
            'description' => __( 'The button is hidden when the link is empty.', 'mme-elementor-kit' ),
        ) );

        $sample_bio = '<p>Hơn 10 năm kinh nghiệm trong lĩnh vực của mình, từng đảm nhận nhiều vị trí quản lý tại các doanh nghiệp lớn.</p>';

        $this->add_control( 'items', array(
            'label'       => __( 'Items', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ name }}}',
            'default'     => array(
                array( 'name' => 'Nguyễn Văn An', 'role' => 'Giám đốc điều hành', 'bio' => $sample_bio ),
                array( 'name' => 'Trần Thị Bình', 'role' => 'Giám đốc tài chính', 'bio' => $sample_bio ),
                array( 'name' => 'Lê Minh Châu', 'role' => 'Trưởng phòng kinh doanh', 'bio' => $sample_bio ),
            ),
            'condition'   => array( 'source' => 'manual' ),
        ) );

        $post_types = array();
        foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
            if ( 'attachment' !== $type->name ) {
                $post_types[ $type->name ] = $type->labels->singular_name;
            }
        }

        $this->add_control( 'post_type', array(
            'label'     => __( 'Post type', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SELECT,
            'options'   => $post_types,
            'default'   => 'post',
            'condition' => array( 'source' => 'posts' ),
        ) );

        $this->add_control( 'posts_per_page', array(
            'label'     => __( 'Number of items', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 50,
            'default'   => 8,
            'condition' => array( 'source' => 'posts' ),
        ) );

        $this->add_control( 'post_ids', array(
            'label'       => __( 'Only these post IDs', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => '12, 34, 56',
            'label_block' => true,
            'description' => __( 'Optional, comma separated. Kept in this order when "Order by" is "IDs order".', 'mme-elementor-kit' ),
            'condition'   => array( 'source' => 'posts' ),
        ) );

        $this->add_control( 'orderby', array(
            'label'     => __( 'Order by', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'menu_order',
            'options'   => array(
                'menu_order' => __( 'Menu order', 'mme-elementor-kit' ),
                'date'       => __( 'Date', 'mme-elementor-kit' ),
                'title'      => __( 'Title', 'mme-elementor-kit' ),
                'post__in'   => __( 'IDs order', 'mme-elementor-kit' ),
                'rand'       => __( 'Random', 'mme-elementor-kit' ),
            ),
            'condition' => array( 'source' => 'posts' ),
        ) );

        $this->add_control( 'order', array(
            'label'     => __( 'Order', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'ASC',
            'options'   => array(
                'ASC'  => __( 'Ascending', 'mme-elementor-kit' ),
                'DESC' => __( 'Descending', 'mme-elementor-kit' ),
            ),
            'condition' => array( 'source' => 'posts', 'orderby!' => array( 'post__in', 'rand' ) ),
        ) );

        $this->add_control( 'role_field', array(
            'label'       => __( 'Role field', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'position',
            'label_block' => true,
            'description' => __( 'ACF / SCF field name or post meta key holding the role. Empty hides the role.', 'mme-elementor-kit' ),
            'separator'   => 'before',
            'condition'   => array( 'source' => 'posts' ),
        ) );

        $this->add_control( 'bio_source', array(
            'label'     => __( 'Description', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'excerpt',
            'options'   => array(
                'excerpt' => __( 'Excerpt', 'mme-elementor-kit' ),
                'field'   => __( 'ACF / SCF field', 'mme-elementor-kit' ),
                'none'    => __( 'None', 'mme-elementor-kit' ),
            ),
            'condition' => array( 'source' => 'posts' ),
        ) );

        $this->add_control( 'bio_field', array(
            'label'       => __( 'Description field', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'bio',
            'label_block' => true,
            'condition'   => array( 'source' => 'posts', 'bio_source' => 'field' ),
        ) );

        $this->add_control( 'excerpt_length', array(
            'label'     => __( 'Excerpt length (words)', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 10,
            'default'   => 60,
            'condition' => array( 'source' => 'posts', 'bio_source' => 'excerpt' ),
        ) );

        $this->add_control( 'post_button_text', array(
            'label'       => __( 'Button text', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => 'Tìm hiểu thêm',
            'description' => __( 'Links to the post. Empty hides the button.', 'mme-elementor-kit' ),
            'condition'   => array( 'source' => 'posts' ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Slide and thumbnail layout.
     *
     * @return void
     */
    private function register_layout_controls() {
        $this->start_controls_section( 'section_layout', array(
            'label' => __( 'Layout', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'image_position', array(
            'label'   => __( 'Image position', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::CHOOSE,
            'default' => 'left',
            'toggle'  => false,
            'options' => array(
                'left'  => array( 'title' => __( 'Left', 'mme-elementor-kit' ), 'icon' => 'eicon-h-align-left' ),
                'right' => array( 'title' => __( 'Right', 'mme-elementor-kit' ), 'icon' => 'eicon-h-align-right' ),
            ),
        ) );

        $this->add_responsive_control( 'media_width', array(
            'label'          => __( 'Image column width', 'mme-elementor-kit' ),
            'type'           => Controls_Manager::SLIDER,
            'size_units'     => array( '%' ),
            'range'          => array( '%' => array( 'min' => 25, 'max' => 70 ) ),
            'default'        => array( 'size' => 42, 'unit' => '%' ),
            'selectors'      => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-media: {{SIZE}}%;' ),
            'description'    => __( 'On mobile the image always sits above the text.', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'image_ratio', array(
            'label'     => __( 'Image ratio', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => '1 / 1',
            'options'   => array(
                '1 / 1'  => '1:1',
                '4 / 5'  => '4:5',
                '3 / 4'  => '3:4',
                '2 / 3'  => '2:3',
                '4 / 3'  => '4:3',
                'auto'   => __( 'Original', 'mme-elementor-kit' ),
            ),
            'selectors' => array( '{{WRAPPER}} .mme-pc__media' => 'aspect-ratio: {{VALUE}};' ),
        ) );

        $this->add_group_control( Group_Control_Image_Size::get_type(), array(
            'name'    => 'image',
            'default' => 'large',
            'exclude' => array( 'custom' ),
        ) );

        $this->add_control( 'name_tag', array(
            'label'   => __( 'Name HTML tag', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'h3',
            'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ),
        ) );

        $this->add_control( 'show_role', array(
            'label'        => __( 'Show role', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_control( 'show_bio', array(
            'label'        => __( 'Show description', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_control( 'show_button', array(
            'label'        => __( 'Show button', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_control( 'heading_thumbs', array(
            'label'     => __( 'Thumbnails', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'show_thumbs', array(
            'label'        => __( 'Show thumbnails', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_responsive_control( 'thumbs_per_view', array(
            'label'           => __( 'Thumbnails per view', 'mme-elementor-kit' ),
            'type'            => Controls_Manager::SLIDER,
            'range'           => array( 'px' => array( 'min' => 1, 'max' => 8, 'step' => 0.5 ) ),
            'default'         => array( 'size' => 4 ),
            'tablet_default'  => array( 'size' => 3.5 ),
            'mobile_default'  => array( 'size' => 2.5 ),
            'description'     => __( 'A half value (e.g. 2.5) shows part of the next thumbnail so visitors know they can scroll.', 'mme-elementor-kit' ),
            'selectors'       => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-per-view: {{SIZE}};' ),
            'condition'       => array( 'show_thumbs' => 'yes' ),
        ) );

        $this->add_group_control( Group_Control_Image_Size::get_type(), array(
            'name'      => 'thumb',
            'default'   => 'thumbnail',
            'exclude'   => array( 'custom' ),
            'condition' => array( 'show_thumbs' => 'yes' ),
        ) );

        $this->add_control( 'thumb_show_name', array(
            'label'        => __( 'Name under thumbnail', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => array( 'show_thumbs' => 'yes' ),
        ) );

        $this->add_control( 'thumb_show_role', array(
            'label'        => __( 'Role under thumbnail', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'condition'    => array( 'show_thumbs' => 'yes' ),
        ) );

        $this->add_control( 'watermark', array(
            'label'       => __( 'Background text', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'team',
            'description' => __( 'Large faded word behind the carousel. Empty hides it.', 'mme-elementor-kit' ),
            'separator'   => 'before',
        ) );

        $this->end_controls_section();
    }

    /**
     * Carousel behaviour.
     *
     * @return void
     */
    private function register_carousel_controls() {
        $this->start_controls_section( 'section_carousel', array(
            'label' => __( 'Carousel', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'transition', array(
            'label'   => __( 'Transition', 'mme-elementor-kit' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'slide',
            'options' => array(
                'slide' => __( 'Fade + slide', 'mme-elementor-kit' ),
                'fade'  => __( 'Fade', 'mme-elementor-kit' ),
            ),
        ) );

        $this->add_control( 'autoplay', array(
            'label'        => __( 'Autoplay', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'description'  => __( 'Paused inside the Elementor editor, on hover and while a visitor uses the keyboard.', 'mme-elementor-kit' ),
        ) );

        $this->add_control( 'autoplay_delay', array(
            'label'     => __( 'Delay (ms)', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 2000,
            'step'      => 500,
            'default'   => 6000,
            'condition' => array( 'autoplay' => 'yes' ),
        ) );

        $this->add_control( 'show_progress', array(
            'label'        => __( 'Progress bar', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => array( 'autoplay' => 'yes' ),
        ) );

        $this->add_control( 'loop', array(
            'label'        => __( 'Loop', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_control( 'show_arrows', array(
            'label'        => __( 'Arrows', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->add_control( 'show_counter', array(
            'label'        => __( 'Counter (01 / 05)', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ) );

        $this->end_controls_section();
    }

    /**
     * Style tab controls.
     *
     * @return void
     */
    private function register_style_controls() {
        // General.
        $this->start_controls_section( 'section_style_general', array(
            'label' => __( 'General', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'accent_color', array(
            'label'       => __( 'Accent color', 'mme-elementor-kit' ),
            'type'        => Controls_Manager::COLOR,
            'description' => __( 'Used by the button, the active thumbnail, arrows and the progress bar unless set separately.', 'mme-elementor-kit' ),
            'selectors'   => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-accent: {{VALUE}};' ),
        ) );

        $this->add_responsive_control( 'column_gap', array(
            'label'      => __( 'Gap between image and text', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 160 ) ),
            'selectors'  => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-gap: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->add_control( 'vertical_align', array(
            'label'     => __( 'Vertical alignment', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'start'  => array( 'title' => __( 'Top', 'mme-elementor-kit' ), 'icon' => 'eicon-v-align-top' ),
                'center' => array( 'title' => __( 'Middle', 'mme-elementor-kit' ), 'icon' => 'eicon-v-align-middle' ),
                'end'    => array( 'title' => __( 'Bottom', 'mme-elementor-kit' ), 'icon' => 'eicon-v-align-bottom' ),
            ),
            'selectors' => array( '{{WRAPPER}} .mme-pc__slide' => 'align-items: {{VALUE}};' ),
        ) );

        $this->add_responsive_control( 'nav_spacing', array(
            'label'      => __( 'Space above thumbnails', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
            'selectors'  => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-nav-space: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->end_controls_section();

        // Image.
        $this->start_controls_section( 'section_style_image', array(
            'label' => __( 'Image', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'image_radius', array(
            'label'      => __( 'Border radius', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array( '{{WRAPPER}} .mme-pc__media' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'image_border',
            'selector' => '{{WRAPPER}} .mme-pc__media',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'image_shadow',
            'selector' => '{{WRAPPER}} .mme-pc__media',
        ) );

        $this->end_controls_section();

        // Content.
        $this->start_controls_section( 'section_style_content', array(
            'label' => __( 'Content', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_style_text_controls( 'name', __( 'Name', 'mme-elementor-kit' ), '.mme-pc__name' );
        $this->add_style_text_controls( 'role', __( 'Role', 'mme-elementor-kit' ), '.mme-pc__role' );
        $this->add_style_text_controls( 'bio', __( 'Description', 'mme-elementor-kit' ), '.mme-pc__bio' );

        $this->end_controls_section();

        // Button.
        $this->start_controls_section( 'section_style_button', array(
            'label'     => __( 'Button', 'mme-elementor-kit' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_button' => 'yes' ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'button_typography',
            'selector' => '{{WRAPPER}} .mme-pc__btn',
        ) );

        $this->start_controls_tabs( 'button_tabs' );
        foreach ( array( 'normal' => '', 'hover' => ':hover' ) as $state => $pseudo ) {
            $this->start_controls_tab( 'button_tab_' . $state, array(
                'label' => 'normal' === $state ? __( 'Normal', 'mme-elementor-kit' ) : __( 'Hover', 'mme-elementor-kit' ),
            ) );
            $this->add_control( 'button_color_' . $state, array(
                'label'     => __( 'Text color', 'mme-elementor-kit' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array( '{{WRAPPER}} .mme-pc .mme-pc__btn' . $pseudo => 'color: {{VALUE}};' ),
            ) );
            $this->add_control( 'button_bg_' . $state, array(
                'label'     => __( 'Background', 'mme-elementor-kit' ),
                'type'      => Controls_Manager::COLOR,
                'selectors' => array( '{{WRAPPER}} .mme-pc .mme-pc__btn' . $pseudo => 'background-color: {{VALUE}};' ),
            ) );
            $this->end_controls_tab();
        }
        $this->end_controls_tabs();

        $this->add_responsive_control( 'button_padding', array(
            'label'      => __( 'Padding', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'separator'  => 'before',
            'selectors'  => array( '{{WRAPPER}} .mme-pc__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_control( 'button_radius', array(
            'label'      => __( 'Border radius', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array( '{{WRAPPER}} .mme-pc__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->end_controls_section();

        // Thumbnails.
        $this->start_controls_section( 'section_style_thumbs', array(
            'label'     => __( 'Thumbnails', 'mme-elementor-kit' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_thumbs' => 'yes' ),
        ) );

        $this->add_responsive_control( 'thumb_width', array(
            'label'      => __( 'Image size', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 40, 'max' => 220 ) ),
            'selectors'  => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-thumb: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->add_control( 'thumb_shape', array(
            'label'     => __( 'Shape', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SELECT,
            'default'   => '50%',
            'options'   => array(
                '50%'  => __( 'Circle', 'mme-elementor-kit' ),
                '16px' => __( 'Rounded', 'mme-elementor-kit' ),
                '0'    => __( 'Square', 'mme-elementor-kit' ),
            ),
            'selectors' => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-thumb-radius: {{VALUE}};' ),
        ) );

        $this->add_control( 'thumb_ring_color', array(
            'label'     => __( 'Active ring color', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-ring: {{VALUE}};' ),
        ) );

        $this->add_control( 'thumb_opacity', array(
            'label'     => __( 'Inactive opacity', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::SLIDER,
            'range'     => array( 'px' => array( 'min' => 0.2, 'max' => 1, 'step' => 0.05 ) ),
            'selectors' => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-dim: {{SIZE}};' ),
        ) );

        $this->add_control( 'thumb_grayscale', array(
            'label'        => __( 'Grayscale inactive', 'mme-elementor-kit' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'selectors'    => array( '{{WRAPPER}} .mme-pc__thumb:not(.is-active):not(:hover) .mme-pc__thumb-img' => 'filter: grayscale(1);' ),
        ) );

        $this->add_style_text_controls( 'thumb_name', __( 'Name', 'mme-elementor-kit' ), '.mme-pc__thumb-name' );
        $this->add_style_text_controls( 'thumb_role', __( 'Role', 'mme-elementor-kit' ), '.mme-pc__thumb-role' );

        $this->end_controls_section();

        // Arrows and counter.
        $this->start_controls_section( 'section_style_arrows', array(
            'label' => __( 'Arrows & counter', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'arrow_color', array(
            'label'     => __( 'Arrow color', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-arrow: {{VALUE}};' ),
        ) );

        $this->add_control( 'arrow_size', array(
            'label'      => __( 'Arrow size', 'mme-elementor-kit' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 28, 'max' => 72 ) ),
            'selectors'  => array( '{{WRAPPER}} .mme-pc' => '--mme-pc-arrow-size: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->add_style_text_controls( 'counter', __( 'Counter', 'mme-elementor-kit' ), '.mme-pc__counter' );

        $this->end_controls_section();

        // Background text.
        $this->start_controls_section( 'section_style_watermark', array(
            'label' => __( 'Background text', 'mme-elementor-kit' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_style_text_controls( 'watermark', __( 'Background text', 'mme-elementor-kit' ), '.mme-pc__watermark' );

        $this->end_controls_section();
    }

    /**
     * Heading, color and typography controls for one text element.
     *
     * @param string $key
     * @param string $label
     * @param string $selector
     * @return void
     */
    private function add_style_text_controls( $key, $label, $selector ) {
        $this->add_control( $key . '_heading', array(
            'label'     => $label,
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( $key . '_color', array(
            'label'     => __( 'Color', 'mme-elementor-kit' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => $key . '_typography',
            'selector' => '{{WRAPPER}} ' . $selector,
        ) );
    }

    /**
     * Slides from the repeater.
     *
     * @param array $settings
     * @return array
     */
    private function get_manual_items( $settings ) {
        $items = array();
        foreach ( (array) $settings['items'] as $item ) {
            $items[] = array(
                'image'  => isset( $item['image'] ) ? $item['image'] : array(),
                'name'   => isset( $item['name'] ) ? $item['name'] : '',
                'role'   => isset( $item['role'] ) ? $item['role'] : '',
                'bio'    => empty( $item['bio'] ) ? '' : $this->parse_text_editor( $item['bio'] ),
                'button' => isset( $item['button_text'] ) ? $item['button_text'] : '',
                'link'   => isset( $item['link'] ) && is_array( $item['link'] ) ? $item['link'] : array(),
            );
        }
        return $items;
    }

    /**
     * Slides from posts: featured image, title, role / description fields, permalink.
     *
     * @param array $settings
     * @return array
     */
    private function get_post_items( $settings ) {
        $args = array(
            'post_type'           => $settings['post_type'] ? $settings['post_type'] : 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => max( 1, min( 50, (int) $settings['posts_per_page'] ) ),
            'orderby'             => $settings['orderby'],
            'order'               => 'DESC' === $settings['order'] ? 'DESC' : 'ASC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        );

        $ids = array_filter( array_map( 'absint', explode( ',', (string) $settings['post_ids'] ) ) );
        if ( $ids ) {
            $args['post__in'] = $ids;
        } elseif ( 'post__in' === $args['orderby'] ) {
            $args['orderby'] = 'menu_order';
        }

        $items = array();
        foreach ( get_posts( $args ) as $post ) {
            $bio = '';
            if ( 'field' === $settings['bio_source'] && ! empty( $settings['bio_field'] ) ) {
                $bio = wpautop( wp_kses_post( $this->field_text( $settings['bio_field'], $post->ID ) ) );
            } elseif ( 'excerpt' === $settings['bio_source'] ) {
                // Built without the_content filters, so a post designed with Elementor is not rendered here.
                $text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
                $text = wp_trim_words( $text, max( 10, (int) $settings['excerpt_length'] ) );
                $bio  = '' === $text ? '' : wpautop( esc_html( $text ) );
            }

            $thumbnail_id = get_post_thumbnail_id( $post );
            $items[]      = array(
                'image'  => $thumbnail_id ? array( 'id' => $thumbnail_id, 'url' => '' ) : array(),
                'name'   => get_the_title( $post ),
                'role'   => empty( $settings['role_field'] ) ? '' : wp_strip_all_tags( $this->field_text( $settings['role_field'], $post->ID ) ),
                'bio'    => $bio,
                'button' => $settings['post_button_text'],
                'link'   => array( 'url' => get_permalink( $post ) ),
            );
        }
        return $items;
    }

    /**
     * A field value as text: ACF / SCF when active, post meta otherwise.
     *
     * @param string $name
     * @param int    $post_id
     * @return string
     */
    private function field_text( $name, $post_id ) {
        $value = function_exists( 'get_field' ) ? get_field( trim( $name ), $post_id ) : $this->get_meta_value( trim( $name ), $post_id );
        if ( is_array( $value ) ) {
            $value = implode( ', ', array_filter( array_map( function ( $part ) {
                return is_scalar( $part ) ? (string) $part : '';
            }, $value ) ) );
        }
        return is_scalar( $value ) ? (string) $value : '';
    }

    /**
     * <img> for an image control value, or '' when there is none.
     *
     * @param array  $image  { id, url }
     * @param string $size
     * @param string $alt    Used when the attachment has no alt text.
     * @param bool   $eager
     * @return string
     */
    private function image_html( $image, $size, $alt, $eager ) {
        $attrs = array( 'loading' => $eager ? 'eager' : 'lazy' );

        if ( ! empty( $image['id'] ) ) {
            if ( '' === (string) get_post_meta( $image['id'], '_wp_attachment_image_alt', true ) ) {
                $attrs['alt'] = $alt;
            }
            $html = wp_get_attachment_image( $image['id'], $size, false, $attrs );
            if ( $html ) {
                return $html;
            }
        }

        if ( empty( $image['url'] ) ) {
            return '';
        }
        return sprintf( '<img src="%s" alt="%s" loading="%s">', esc_url( $image['url'] ), esc_attr( $alt ), esc_attr( $attrs['loading'] ) );
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $items    = 'posts' === $settings['source'] ? $this->get_post_items( $settings ) : $this->get_manual_items( $settings );

        if ( ! $items ) {
            if ( $this->is_editor() ) {
                echo '<div class="mme-pc__placeholder">' . esc_html__( 'No items to show. Add items, or check the post type of the Posts source.', 'mme-elementor-kit' ) . '</div>';
            }
            return;
        }

        $uid      = 'mme-pc-' . $this->get_id();
        $count    = count( $items );
        $tag      = in_array( $settings['name_tag'], array( 'h2', 'h3', 'h4', 'div' ), true ) ? $settings['name_tag'] : 'h3';
        $autoplay = 'yes' === $settings['autoplay'] && $count > 1;
        $options  = array(
            'autoplay' => $autoplay,
            'delay'    => max( 2000, (int) $settings['autoplay_delay'] ),
            'loop'     => 'yes' === $settings['loop'],
        );
        $thumbs   = 'yes' === $settings['show_thumbs'] && $count > 1;
        $classes  = array(
            'mme-pc',
            'mme-pc--' . ( 'fade' === $settings['transition'] ? 'fade' : 'slide' ),
            'mme-pc--media-' . ( 'right' === $settings['image_position'] ? 'right' : 'left' ),
        );
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $uid ); ?>" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr__( 'Profiles', 'mme-elementor-kit' ); ?>" data-mme-pc="<?php echo esc_attr( wp_json_encode( $options ) ); ?>">
            <?php if ( '' !== trim( (string) $settings['watermark'] ) ) : ?>
                <div class="mme-pc__watermark" aria-hidden="true"><span><?php echo esc_html( $settings['watermark'] ); ?></span></div>
            <?php endif; ?>

            <div class="mme-pc__stage">
                <?php foreach ( $items as $index => $item ) : ?>
                    <?php
                    $image = $this->image_html( $item['image'], $settings['image_size'], $item['name'], 0 === $index );
                    $link  = 'yes' === $settings['show_button'] && ! empty( $item['link']['url'] ) && '' !== trim( (string) $item['button'] );
                    if ( $link ) {
                        $this->add_link_attributes( 'button-' . $index, $item['link'] );
                        $this->add_render_attribute( 'button-' . $index, 'class', 'mme-pc__btn' );
                    }
                    ?>
                    <div class="mme-pc__slide<?php echo 0 === $index ? ' is-active' : ''; ?><?php echo $image ? '' : ' mme-pc__slide--no-media'; ?>"
                        id="<?php echo esc_attr( $uid . '-slide-' . $index ); ?>"
                        role="<?php echo $thumbs ? 'tabpanel' : 'group'; ?>"
                        aria-roledescription="slide"
                        <?php if ( $thumbs ) : ?>aria-labelledby="<?php echo esc_attr( $uid . '-tab-' . $index ); ?>"<?php else : ?>aria-label="<?php echo esc_attr( ( $index + 1 ) . ' / ' . $count ); ?>"<?php endif; ?>>
                        <?php if ( $image ) : ?>
                            <div class="mme-pc__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image / escaped above. ?></div>
                        <?php endif; ?>
                        <div class="mme-pc__body">
                            <?php if ( 'yes' === $settings['show_counter'] && $count > 1 ) : ?>
                                <span class="mme-pc__counter" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?> <span>/ <?php echo esc_html( sprintf( '%02d', $count ) ); ?></span></span>
                            <?php endif; ?>
                            <?php if ( '' !== $item['name'] ) : ?>
                                <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- whitelisted. ?> class="mme-pc__name"><?php echo esc_html( $item['name'] ); ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
                            <?php endif; ?>
                            <?php if ( 'yes' === $settings['show_role'] && '' !== $item['role'] ) : ?>
                                <p class="mme-pc__role"><?php echo esc_html( $item['role'] ); ?></p>
                            <?php endif; ?>
                            <?php if ( 'yes' === $settings['show_bio'] && '' !== $item['bio'] ) : ?>
                                <div class="mme-pc__bio"><?php echo wp_kses_post( $item['bio'] ); ?></div>
                            <?php endif; ?>
                            <?php if ( $link ) : ?>
                                <a <?php $this->print_render_attribute_string( 'button-' . $index ); ?>>
                                    <span><?php echo esc_html( $item['button'] ); ?></span>
                                    <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ( $count > 1 ) : ?>
                <?php if ( $autoplay && 'yes' === $settings['show_progress'] ) : ?>
                    <div class="mme-pc__progress" aria-hidden="true"><span></span></div>
                <?php endif; ?>

                <div class="mme-pc__nav">
                    <?php if ( 'yes' === $settings['show_arrows'] ) : ?>
                        <button type="button" class="mme-pc__arrow mme-pc__arrow--prev" aria-controls="<?php echo esc_attr( $uid ); ?>" aria-label="<?php echo esc_attr__( 'Previous', 'mme-elementor-kit' ); ?>">
                            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><path d="M12 5 7 10l5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    <?php endif; ?>

                    <?php if ( $thumbs ) : ?>
                        <div class="mme-pc__thumbs" role="tablist" aria-label="<?php echo esc_attr__( 'Choose a slide', 'mme-elementor-kit' ); ?>">
                            <?php foreach ( $items as $index => $item ) : ?>
                                <?php $thumb = $this->image_html( $item['image'], $settings['thumb_size'], $item['name'], false ); ?>
                                <button type="button" class="mme-pc__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
                                    id="<?php echo esc_attr( $uid . '-tab-' . $index ); ?>"
                                    role="tab"
                                    aria-controls="<?php echo esc_attr( $uid . '-slide-' . $index ); ?>"
                                    aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                                    tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>"
                                    <?php if ( 'yes' !== $settings['thumb_show_name'] ) : ?>aria-label="<?php echo esc_attr( $item['name'] ? $item['name'] : ( $index + 1 ) ); ?>"<?php endif; ?>>
                                    <span class="mme-pc__thumb-img"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- see image_html(). ?></span>
                                    <?php if ( 'yes' === $settings['thumb_show_name'] && '' !== $item['name'] ) : ?>
                                        <span class="mme-pc__thumb-name"><?php echo esc_html( $item['name'] ); ?></span>
                                    <?php endif; ?>
                                    <?php if ( 'yes' === $settings['thumb_show_role'] && '' !== $item['role'] ) : ?>
                                        <span class="mme-pc__thumb-role"><?php echo esc_html( $item['role'] ); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="mme-pc__spacer"></div>
                    <?php endif; ?>

                    <?php if ( 'yes' === $settings['show_arrows'] ) : ?>
                        <button type="button" class="mme-pc__arrow mme-pc__arrow--next" aria-controls="<?php echo esc_attr( $uid ); ?>" aria-label="<?php echo esc_attr__( 'Next', 'mme-elementor-kit' ); ?>">
                            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><path d="m8 5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }
}
