<?php
namespace Ems_Elements_Share\Ai;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI layout generator.
 *
 * The MMe AI service (see Ems_Ai_Client) writes a small layout format (sections
 * made of blocks) using the customer's business data; it never writes Elementor
 * data directly. The layout is validated again and turned into Elementor
 * containers and core widgets here. Unknown blocks and values are dropped, text
 * is sanitized, images are placeholders.
 */
class Ems_Ai_Layout {

    const MAX_SECTIONS = 10;
    const MAX_BLOCKS   = 14;
    const MAX_ITEMS    = 12;

    private static $instance = null;

    public function __construct() {
        add_action( 'admin_init', array( $this, 'handle_key_form' ) );
    }

    /* ------------------------------------------------------------------ */
    /* Admin: key form                                                      */
    /* ------------------------------------------------------------------ */

    public function handle_key_form() {
        if ( ! ( isset( $_POST['action'] ) && 'mme_kit_ai_key' === $_POST['action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_ai_key', '_wp_nonce_ai_key' );

        $do     = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
        $status = '';
        if ( 'save' === $do ) {
            $key = isset( $_POST['ai_key'] ) ? trim( wp_unslash( $_POST['ai_key'] ) ) : '';
            if ( ! Ems_Ai_Client::save_key( $key ) ) { // Also replaces a legacy key.
                $status = 'key_invalid_format';
            } else {
                $check  = Ems_Ai_Client::check();
                $status = is_wp_error( $check ) ? 'saved_check_failed' : 'saved';
                if ( is_wp_error( $check ) ) {
                    set_transient( 'mme_kit_ai_notice_' . get_current_user_id(), $check->get_error_message(), 5 * MINUTE_IN_SECONDS );
                }
            }
        } elseif ( 'test' === $do ) {
            $check  = Ems_Ai_Client::check();
            $status = is_wp_error( $check ) ? 'check_failed' : 'checked';
            if ( is_wp_error( $check ) ) {
                set_transient( 'mme_kit_ai_notice_' . get_current_user_id(), $check->get_error_message(), 5 * MINUTE_IN_SECONDS );
            }
        } elseif ( 'remove' === $do ) {
            Ems_Ai_Client::remove_key();
            $status = 'removed';
        }

        wp_safe_redirect( add_query_arg( 'ai', $status, admin_url( 'admin.php?page=mme-elementor-kit-mcp' ) ) . '#mme-ai' );
        exit;
    }

    /**
     * Result of the key form, read once after the redirect.
     *
     * @return array|null array( type, message )
     */
    public static function key_notice() {
        $status = isset( $_GET['ai'] ) ? sanitize_key( wp_unslash( $_GET['ai'] ) ) : '';
        if ( '' === $status ) {
            return null;
        }
        $key     = 'mme_kit_ai_notice_' . get_current_user_id();
        $detail  = (string) get_transient( $key );
        delete_transient( $key );

        switch ( $status ) {
            case 'saved':
                return array( 'success', __( 'MMe API key saved (encrypted) and connected.', 'mme-elementor-kit' ) );
            case 'checked':
                return array( 'success', __( 'Connected to MMe.', 'mme-elementor-kit' ) );
            case 'removed':
                return array( 'success', __( 'API key removed from this site.', 'mme-elementor-kit' ) );
            case 'key_invalid_format':
                return array( 'error', __( 'That is not an MMe API key. It starts with "mme_" and is given by MMe.', 'mme-elementor-kit' ) );
            case 'saved_check_failed':
                return array( 'error', __( 'API key saved, but the connection check failed:', 'mme-elementor-kit' ) . ' ' . $detail );
            case 'check_failed':
                return array( 'error', $detail ? $detail : __( 'The check failed.', 'mme-elementor-kit' ) );
        }
        return null;
    }

    public function is_elementor_page( $post_id ) {
        return class_exists( '\Elementor\Plugin' ) && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
    }

    /* ------------------------------------------------------------------ */
    /* Layout types (the prompt itself lives on the MMe AI service)         */
    /* ------------------------------------------------------------------ */

    public static function kinds() {
        return array(
            'auto'     => __( 'Let the AI decide', 'mme-elementor-kit' ),
            'landing'  => __( 'Full landing page', 'mme-elementor-kit' ),
            'hero'     => __( 'Hero (top banner)', 'mme-elementor-kit' ),
            'features' => __( 'Benefits / features', 'mme-elementor-kit' ),
            'services' => __( 'Services', 'mme-elementor-kit' ),
            'process'  => __( 'Process / steps', 'mme-elementor-kit' ),
            'team'     => __( 'Team', 'mme-elementor-kit' ),
            'pricing'  => __( 'Pricing', 'mme-elementor-kit' ),
            'faq'      => __( 'FAQ', 'mme-elementor-kit' ),
            'cta'      => __( 'Call to action', 'mme-elementor-kit' ),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Layout → Elementor                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Colors per section style: background, heading, text, card background, card border.
     */
    private function palette( $style ) {
        if ( 'primary' === $style ) {
            $primary = $this->kit_color( 'primary', '#1E3A8A' );
            if ( $this->is_light( $primary ) ) {
                // Light brand color: dark text keeps it readable.
                return array( 'bg' => $primary, 'heading' => '#0F172A', 'text' => '#1E293B', 'card' => 'rgba(255,255,255,0.55)', 'border' => 'rgba(15,23,42,0.12)', 'on_dark' => false, 'global_bg' => 'primary' );
            }
        }
        $palettes = array(
            'light'   => array( 'bg' => '#FFFFFF', 'heading' => '#0F172A', 'text' => '#475569', 'card' => '#FFFFFF', 'border' => '#E2E8F0', 'on_dark' => false ),
            'soft'    => array( 'bg' => '#F5F7FA', 'heading' => '#0F172A', 'text' => '#475569', 'card' => '#FFFFFF', 'border' => '#E2E8F0', 'on_dark' => false ),
            'primary' => array( 'bg' => $this->kit_color( 'primary', '#1E3A8A' ), 'heading' => '#FFFFFF', 'text' => '#E2E8F0', 'card' => 'rgba(255,255,255,0.08)', 'border' => 'rgba(255,255,255,0.18)', 'on_dark' => true, 'global_bg' => 'primary' ),
            'dark'    => array( 'bg' => '#0F172A', 'heading' => '#FFFFFF', 'text' => '#CBD5E1', 'card' => 'rgba(255,255,255,0.06)', 'border' => 'rgba(255,255,255,0.14)', 'on_dark' => true ),
        );
        return isset( $palettes[ $style ] ) ? $palettes[ $style ] : $palettes['light'];
    }

    /**
     * The site's primary brand color (active Elementor kit), for the HTML mode.
     */
    public function brand_color() {
        return $this->kit_color( 'primary', '#1E3A8A' );
    }

    /**
     * One full-width container holding an HTML widget (MMe AI "HTML" mode).
     *
     * @param string $html Block from the MMe AI service (already sanitized and scoped there).
     * @return array Elementor elements.
     */
    public function html_elements( $html ) {
        $settings = array(
            'content_width' => 'full',
            'flex_direction' => 'column',
            'padding'       => $this->box( 0, 0, 0, 0, true ),
        );
        return array(
            $this->container( $settings, array( $this->widget( 'html', array( 'html' => (string) $html ) ) ), false ),
        );
    }

    /**
     * @param array $layout
     * @return array Elementor elements.
     */
    public function build( $layout ) {
        $sections = isset( $layout['sections'] ) && is_array( $layout['sections'] ) ? array_slice( $layout['sections'], 0, self::MAX_SECTIONS ) : array();
        $elements = array();
        foreach ( $sections as $section ) {
            if ( ! is_array( $section ) || empty( $section['blocks'] ) || ! is_array( $section['blocks'] ) ) {
                continue;
            }
            $style   = isset( $section['style'] ) ? sanitize_key( $section['style'] ) : 'light';
            $align   = isset( $section['align'] ) && 'center' === $section['align'] ? 'center' : 'left';
            $palette = $this->palette( $style );
            $ctx     = array( 'p' => $palette, 'align' => $align, 'first' => empty( $elements ) );

            $children = $this->blocks( array_slice( $section['blocks'], 0, self::MAX_BLOCKS ), $ctx, true );
            if ( ! $children ) {
                continue;
            }

            $settings = array(
                'content_width'        => 'boxed',
                'boxed_width'          => array( 'unit' => 'px', 'size' => 1140, 'sizes' => array() ),
                'flex_direction'       => 'column',
                'flex_gap'             => $this->gap( 20 ),
                'padding'              => $this->box( 96, 20, 96, 20 ),
                'padding_tablet'       => $this->box( 72, 20, 72, 20 ),
                'padding_mobile'       => $this->box( 56, 16, 56, 16 ),
                'background_background' => 'classic',
                'background_color'     => $palette['bg'],
            );
            if ( ! empty( $palette['global_bg'] ) ) {
                $settings['__globals__'] = array( 'background_color' => 'globals/colors?id=' . $palette['global_bg'] );
            }
            $elements[] = $this->container( $settings, $children, false );
        }
        return $elements;
    }

    private function blocks( $blocks, $ctx, $top = false ) {
        $out = array();
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) || empty( $block['type'] ) ) {
                continue;
            }
            $built = $this->block( $block, $ctx, $top );
            if ( $built ) {
                $out[] = $built;
            }
        }
        return $out;
    }

    private function block( $b, $ctx, $top ) {
        $p     = $ctx['p'];
        $align = $ctx['align'];

        switch ( $b['type'] ) {
            case 'eyebrow':
                $text = $this->text( $b, 'text' );
                return '' === $text ? null : $this->widget( 'heading', array(
                    'title'                          => $text,
                    'header_size'                    => 'p',
                    'align'                          => $align,
                    'title_color'                    => $p['on_dark'] ? '#FFFFFF' : '#2563EB',
                    '__globals__'                    => $p['on_dark'] ? array() : array( 'title_color' => 'globals/colors?id=accent' ),
                    'typography_typography'          => 'custom',
                    'typography_font_size'           => array( 'unit' => 'px', 'size' => 14, 'sizes' => array() ),
                    'typography_font_weight'         => '600',
                    'typography_text_transform'      => 'uppercase',
                    'typography_letter_spacing'      => array( 'unit' => 'px', 'size' => 1.5, 'sizes' => array() ),
                ) );

            case 'heading':
                $text  = $this->text( $b, 'text' );
                $level = isset( $b['level'] ) ? (int) $b['level'] : 2;
                $level = 1 === $level && ! $ctx['first'] ? 2 : max( 1, min( 3, $level ) );
                $sizes = array( 1 => array( 52, 40, 32 ), 2 => array( 40, 32, 28 ), 3 => array( 24, 22, 20 ) );
                return '' === $text ? null : $this->widget( 'heading', array(
                    'title'                         => $text,
                    'header_size'                   => 'h' . $level,
                    'align'                         => $align,
                    'title_color'                   => $p['heading'],
                    'typography_typography'         => 'custom',
                    'typography_font_size'          => array( 'unit' => 'px', 'size' => $sizes[ $level ][0], 'sizes' => array() ),
                    'typography_font_size_tablet'   => array( 'unit' => 'px', 'size' => $sizes[ $level ][1], 'sizes' => array() ),
                    'typography_font_size_mobile'   => array( 'unit' => 'px', 'size' => $sizes[ $level ][2], 'sizes' => array() ),
                    'typography_font_weight'        => $level < 3 ? '700' : '600',
                    'typography_line_height'        => array( 'unit' => 'em', 'size' => 1.2, 'sizes' => array() ),
                ) );

            case 'text':
                $html = $this->html( isset( $b['html'] ) ? $b['html'] : ( isset( $b['text'] ) ? '<p>' . $b['text'] . '</p>' : '' ) );
                return '' === $html ? null : $this->text_widget( $html, $ctx );

            case 'buttons':
                $items = isset( $b['items'] ) && is_array( $b['items'] ) ? array_slice( $b['items'], 0, 2 ) : array();
                $buttons = array();
                foreach ( $items as $item ) {
                    $label = $this->text( $item, 'text' );
                    if ( '' === $label ) {
                        continue;
                    }
                    $buttons[] = $this->button( $label, isset( $item['url'] ) ? $item['url'] : '#', isset( $item['style'] ) ? $item['style'] : 'primary', $ctx );
                }
                if ( ! $buttons ) {
                    return null;
                }
                return $this->container( array(
                    'content_width'         => 'full',
                    'flex_direction'        => 'row',
                    'flex_wrap'             => 'wrap',
                    'flex_gap'              => $this->gap( 12 ),
                    'flex_justify_content'  => 'center' === $align ? 'center' : 'flex-start',
                    'padding'               => $this->box( 8, 0, 0, 0 ),
                ), $buttons, true );

            case 'image':
                return $this->widget( 'image', array(
                    'image'               => array( 'url' => \Elementor\Utils::get_placeholder_image_src(), 'id' => '', 'alt' => $this->text( $b, 'alt' ) ),
                    'image_size'          => 'full',
                    'width'               => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
                    'image_border_radius' => $this->box( 16, 16, 16, 16, true ),
                ) );

            case 'list':
                $icon  = $this->icon( isset( $b['icon'] ) ? $b['icon'] : '', 'fas fa-check' );
                $items = array();
                foreach ( $this->items( $b ) as $item ) {
                    $text = is_array( $item ) ? $this->text( $item, 'text' ) : sanitize_text_field( (string) $item );
                    if ( '' !== $text ) {
                        $items[] = array( '_id' => $this->id(), 'text' => $text, 'selected_icon' => $icon );
                    }
                }
                return ! $items ? null : $this->widget( 'icon-list', array(
                    'icon_list'                   => $items,
                    'space_between'               => array( 'unit' => 'px', 'size' => 10, 'sizes' => array() ),
                    'icon_color'                  => $p['on_dark'] ? '#FFFFFF' : '#2563EB',
                    '__globals__'                 => $p['on_dark'] ? array() : array( 'icon_color' => 'globals/colors?id=accent' ),
                    'text_color'                  => $p['text'],
                    'icon_typography_typography'  => 'custom',
                    'icon_typography_font_size'   => array( 'unit' => 'px', 'size' => 17, 'sizes' => array() ),
                ) );

            case 'cards':
                $cards = array();
                foreach ( $this->items( $b ) as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }
                    $title = $this->text( $item, 'title' );
                    $text  = $this->text( $item, 'text' );
                    if ( '' === $title && '' === $text ) {
                        continue;
                    }
                    $icon     = isset( $item['icon'] ) ? $this->icon( $item['icon'], '' ) : null;
                    $settings = array(
                        'title_text'                         => $title,
                        'description_text'                   => $text,
                        'position'                           => 'top',
                        'text_align'                         => $align,
                        'title_color'                        => $p['heading'],
                        'description_color'                  => $p['text'],
                        'title_typography_typography'        => 'custom',
                        'title_typography_font_size'         => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
                        'title_typography_font_weight'       => '600',
                        'title_bottom_space'                 => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ),
                        'icon_space'                         => array( 'unit' => 'px', 'size' => 16, 'sizes' => array() ),
                        'icon_size'                          => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ),
                        'primary_color'                      => $p['on_dark'] ? '#FFFFFF' : '#2563EB',
                        '__globals__'                        => $p['on_dark'] ? array() : array( 'primary_color' => 'globals/colors?id=accent' ),
                    );
                    if ( $icon ) {
                        $settings['selected_icon'] = $icon;
                    } else {
                        $settings['selected_icon'] = array( 'value' => '', 'library' => '' );
                    }
                    $cards[] = $this->card( array( $this->widget( 'icon-box', $settings ) ), $ctx );
                }
                return ! $cards ? null : $this->grid( $cards, isset( $b['columns'] ) ? (int) $b['columns'] : min( 3, count( $cards ) ) );

            case 'stats':
                $cells = array();
                foreach ( $this->items( $b ) as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }
                    $value = $this->text( $item, 'value' );
                    $label = $this->text( $item, 'label' );
                    if ( '' === $value ) {
                        continue;
                    }
                    $cells[] = $this->container( array(
                        'content_width'  => 'full',
                        'flex_direction' => 'column',
                        'flex_gap'       => $this->gap( 4 ),
                    ), array(
                        $this->widget( 'heading', array(
                            'title'                  => $value,
                            'header_size'            => 'p',
                            'align'                  => $align,
                            'title_color'            => $p['on_dark'] ? '#FFFFFF' : '#2563EB',
                            '__globals__'            => $p['on_dark'] ? array() : array( 'title_color' => 'globals/colors?id=accent' ),
                            'typography_typography'  => 'custom',
                            'typography_font_size'   => array( 'unit' => 'px', 'size' => 40, 'sizes' => array() ),
                            'typography_font_weight' => '700',
                        ) ),
                        $this->text_widget( '<p>' . esc_html( $label ) . '</p>', $ctx ),
                    ), true );
                }
                return ! $cells ? null : $this->grid( $cells, min( 4, count( $cells ) ) );

            case 'faq':
                $rows = array();
                foreach ( $this->items( $b ) as $item ) {
                    if ( ! is_array( $item ) ) {
                        continue;
                    }
                    $q = $this->text( $item, 'q' );
                    $a = $this->html( isset( $item['a'] ) ? '<p>' . $item['a'] . '</p>' : '' );
                    if ( '' === $q ) {
                        continue;
                    }
                    $rows[] = $this->card( array(
                        $this->widget( 'heading', array(
                            'title'                  => $q,
                            'header_size'            => 'h3',
                            'align'                  => 'left',
                            'title_color'            => $p['heading'],
                            'typography_typography'  => 'custom',
                            'typography_font_size'   => array( 'unit' => 'px', 'size' => 18, 'sizes' => array() ),
                            'typography_font_weight' => '600',
                        ) ),
                        $this->text_widget( $a, array_merge( $ctx, array( 'align' => 'left' ) ) ),
                    ), $ctx );
                }
                return ! $rows ? null : $this->container( array(
                    'content_width'  => 'full',
                    'flex_direction' => 'column',
                    'flex_gap'       => $this->gap( 12 ),
                ), $rows, true );

            case 'split':
                if ( ! $top ) {
                    return null;
                }
                $left  = isset( $b['left'] ) && is_array( $b['left'] ) ? $this->blocks( array_slice( $b['left'], 0, self::MAX_BLOCKS ), $ctx ) : array();
                $right = isset( $b['right'] ) && is_array( $b['right'] ) ? $this->blocks( array_slice( $b['right'], 0, self::MAX_BLOCKS ), $ctx ) : array();
                if ( ! $left && ! $right ) {
                    return null;
                }
                $ratios = array( '50-50' => '1fr 1fr', '60-40' => '3fr 2fr', '40-60' => '2fr 3fr' );
                $ratio  = isset( $b['ratio'], $ratios[ $b['ratio'] ] ) ? $ratios[ $b['ratio'] ] : '1fr 1fr';
                $column = function ( $children ) {
                    return $this->container( array(
                        'content_width'        => 'full',
                        'flex_direction'       => 'column',
                        'flex_justify_content' => 'center',
                        'flex_gap'             => $this->gap( 16 ),
                    ), $children, true );
                };
                return $this->container( array(
                    'content_width'             => 'full',
                    'container_type'            => 'grid',
                    'grid_columns_grid'         => array( 'unit' => 'custom', 'size' => $ratio, 'sizes' => array() ),
                    'grid_columns_grid_tablet'  => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
                    'grid_columns_grid_mobile'  => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
                    'grid_rows_grid'            => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
                    'grid_gaps'                 => $this->gaps( 48 ),
                    'grid_gaps_mobile'          => $this->gaps( 32 ),
                ), array( $column( $left ), $column( $right ) ), true );

            case 'spacer':
                $sizes = array( 'sm' => 16, 'md' => 32, 'lg' => 56 );
                $size  = isset( $b['size'], $sizes[ $b['size'] ] ) ? $sizes[ $b['size'] ] : 32;
                return $this->widget( 'spacer', array( 'space' => array( 'unit' => 'px', 'size' => $size, 'sizes' => array() ) ) );
        }
        return null;
    }

    /* ---------------- element helpers ---------------- */

    private function text_widget( $html, $ctx ) {
        return $this->widget( 'text-editor', array(
            'editor'                 => $html,
            'align'                  => $ctx['align'],
            'text_color'             => $ctx['p']['text'],
            'typography_typography'  => 'custom',
            'typography_font_size'   => array( 'unit' => 'px', 'size' => 17, 'sizes' => array() ),
            'typography_line_height' => array( 'unit' => 'em', 'size' => 1.65, 'sizes' => array() ),
        ) );
    }

    private function button( $label, $url, $style, $ctx ) {
        $p        = $ctx['p'];
        $outline  = 'outline' === $style;
        $settings = array(
            'text'                   => $label,
            'link'                   => array( 'url' => $this->url( $url ), 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ),
            'size'                   => 'md',
            'typography_typography'  => 'custom',
            'typography_font_size'   => array( 'unit' => 'px', 'size' => 16, 'sizes' => array() ),
            'typography_font_weight' => '600',
            'border_radius'          => $this->box( 999, 999, 999, 999, true ),
            'text_padding'           => $this->box( 14, 28, 14, 28 ),
            'background_background'  => 'classic',
        );
        if ( $outline ) {
            $settings['background_color']  = 'rgba(0,0,0,0)';
            $settings['button_text_color'] = $p['heading'];
            $settings['border_border']     = 'solid';
            $settings['border_width']      = $this->box( 2, 2, 2, 2, true );
            $settings['border_color']      = $p['on_dark'] ? 'rgba(255,255,255,0.6)' : '#CBD5E1';
        } elseif ( $p['on_dark'] ) {
            $settings['background_color']  = '#FFFFFF';
            $settings['button_text_color'] = '#0F172A';
        } else {
            $settings['background_color']  = $this->kit_color( 'accent', '#2563EB' );
            $settings['button_text_color'] = $this->is_light( $settings['background_color'] ) ? '#0F172A' : '#FFFFFF';
            $settings['__globals__']       = array( 'background_color' => 'globals/colors?id=accent' );
        }
        return $this->widget( 'button', $settings );
    }

    private function card( $children, $ctx ) {
        $p = $ctx['p'];
        return $this->container( array(
            'content_width'         => 'full',
            'flex_direction'        => 'column',
            'flex_gap'              => $this->gap( 8 ),
            'padding'               => $this->box( 28, 28, 28, 28, true ),
            'padding_mobile'        => $this->box( 22, 22, 22, 22, true ),
            'background_background' => 'classic',
            'background_color'      => $p['card'],
            'border_border'         => 'solid',
            'border_width'          => $this->box( 1, 1, 1, 1, true ),
            'border_color'          => $p['border'],
            'border_radius'         => $this->box( 16, 16, 16, 16, true ),
        ), $children, true );
    }

    private function grid( $children, $columns ) {
        $columns = max( 1, min( 4, (int) $columns ) );
        return $this->container( array(
            'content_width'            => 'full',
            'container_type'           => 'grid',
            'grid_columns_grid'        => array( 'unit' => 'fr', 'size' => $columns, 'sizes' => array() ),
            'grid_columns_grid_tablet' => array( 'unit' => 'fr', 'size' => min( 2, $columns ), 'sizes' => array() ),
            'grid_columns_grid_mobile' => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
            'grid_rows_grid'           => array( 'unit' => 'fr', 'size' => 1, 'sizes' => array() ),
            'grid_gaps'                => $this->gaps( 24 ),
            'grid_gaps_mobile'         => $this->gaps( 16 ),
            'grid_auto_flow'           => 'row',
            'padding'                  => $this->box( 16, 0, 0, 0 ),
        ), $children, true );
    }

    private function container( $settings, $children, $inner ) {
        return array(
            'id'       => $this->id(),
            'elType'   => 'container',
            'isInner'  => $inner,
            'settings' => $settings,
            'elements' => $children,
        );
    }

    private function widget( $type, $settings ) {
        if ( isset( $settings['__globals__'] ) && ! $settings['__globals__'] ) {
            unset( $settings['__globals__'] );
        }
        return array(
            'id'         => $this->id(),
            'elType'     => 'widget',
            'widgetType' => $type,
            'settings'   => $settings,
            'elements'   => array(),
        );
    }

    private function id() {
        return method_exists( '\Elementor\Utils', 'generate_random_string' ) ? \Elementor\Utils::generate_random_string() : substr( md5( uniqid( '', true ) ), 0, 7 );
    }

    private function box( $top, $right, $bottom, $left, $linked = false ) {
        return array(
            'unit'     => 'px',
            'top'      => (string) $top,
            'right'    => (string) $right,
            'bottom'   => (string) $bottom,
            'left'     => (string) $left,
            'isLinked' => $linked,
        );
    }

    private function gap( $size ) {
        return array( 'unit' => 'px', 'size' => $size, 'column' => (string) $size, 'row' => (string) $size, 'isLinked' => true );
    }

    private function gaps( $size ) {
        return array( 'unit' => 'px', 'column' => (string) $size, 'row' => (string) $size, 'isLinked' => true );
    }

    /* ---------------- colors ---------------- */

    /**
     * A system color of the active Elementor kit (primary, secondary, text, accent).
     */
    private function kit_color( $id, $fallback ) {
        static $colors = null;
        if ( null === $colors ) {
            $colors = array();
            $kit    = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
            if ( $kit ) {
                foreach ( (array) $kit->get_settings_for_display( 'system_colors' ) as $color ) {
                    if ( ! empty( $color['_id'] ) && ! empty( $color['color'] ) ) {
                        $colors[ $color['_id'] ] = $color['color'];
                    }
                }
            }
        }
        return isset( $colors[ $id ] ) && preg_match( '/^#[0-9a-f]{6}$/i', $colors[ $id ] ) ? strtoupper( $colors[ $id ] ) : $fallback;
    }

    /**
     * Whether white text would be hard to read on this color (relative luminance > 0.4).
     */
    private function is_light( $hex ) {
        if ( ! preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $m ) ) {
            return false;
        }
        $channel = function ( $v ) {
            $v = hexdec( $v ) / 255;
            return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
        };
        $l = 0.2126 * $channel( $m[1] ) + 0.7152 * $channel( $m[2] ) + 0.0722 * $channel( $m[3] );
        return $l > 0.4;
    }

    /* ---------------- value helpers ---------------- */

    private function items( $b ) {
        return isset( $b['items'] ) && is_array( $b['items'] ) ? array_slice( $b['items'], 0, self::MAX_ITEMS ) : array();
    }

    private function text( $source, $key ) {
        return is_array( $source ) && isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ? sanitize_text_field( (string) $source[ $key ] ) : '';
    }

    private function html( $html ) {
        $allowed = array(
            'p'      => array(),
            'br'     => array(),
            'strong' => array(),
            'b'      => array(),
            'em'     => array(),
            'i'      => array(),
            'ul'     => array(),
            'ol'     => array(),
            'li'     => array(),
            'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
        );
        $html = is_scalar( $html ) ? (string) $html : '';
        // wp_kses keeps the text inside removed tags; drop script / style blocks entirely.
        $html = preg_replace( '#<(script|style|iframe|object|noscript)\b[^>]*>.*?</\1\s*>#is', '', $html );
        $html = wp_kses( $html, $allowed );
        // Links: only #anchors, http(s), mailto and tel survive.
        $html = preg_replace_callback( '/href="([^"]*)"/i', function ( $m ) {
            return 'href="' . esc_attr( $this->url( html_entity_decode( $m[1] ) ) ) . '"';
        }, $html );
        return trim( $html );
    }

    private function url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return '#';
        }
        if ( '#' === $url[0] ) {
            return '#' . preg_replace( '/[^A-Za-z0-9_-]/', '', substr( $url, 1 ) );
        }
        if ( ! preg_match( '#^(https?://|mailto:|tel:|/(?!/))#i', $url ) ) {
            return '#';
        }
        $clean = esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel' ) );
        return $clean ? $clean : '#';
    }

    private function icon( $value, $fallback ) {
        $value = trim( (string) $value );
        if ( ! preg_match( '/^(fas|far|fab) fa-[a-z0-9-]+$/', $value ) ) {
            $value = $fallback;
        }
        if ( '' === $value ) {
            return null;
        }
        $libraries = array( 'fas' => 'fa-solid', 'far' => 'fa-regular', 'fab' => 'fa-brands' );
        return array( 'value' => $value, 'library' => $libraries[ substr( $value, 0, 3 ) ] );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
