<?php
namespace Ems_Elements_Share\Mcp;

use Ems_Elements_Share\Admin\Ems_Master_License;
use Ems_Elements_Share\Admin\Ems_Updater;
use Ems_Elements_Share\Areas\Ems_Areas_Manager;
use Ems_Elements_Share\Widgets\Ems_Widgets_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MMe Kit abilities (WordPress Abilities API, WordPress 6.9+).
 *
 * With the MCP Adapter plugin active they become MCP tools, so an AI client
 * connected with an application password can diagnose the site, render
 * widgets and install updates. Every ability needs an administrator.
 */
class Ems_Mcp_Abilities {

    const CATEGORY = 'mme-kit';

    /**
     * Output of some abilities is cut above this size so it fits a model's context.
     */
    const MAX_OUTPUT = 120000;

    private static $instance = null;

    public function __construct() {
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
    }

    /**
     * Whether this WordPress has the Abilities API.
     *
     * @return boolean
     */
    public static function abilities_api_available() {
        return function_exists( 'wp_register_ability' );
    }

    /**
     * Whether the MCP Adapter plugin is active.
     *
     * @return boolean
     */
    public static function mcp_adapter_active() {
        return class_exists( '\WP\MCP\Plugin' );
    }

    /**
     * Endpoint of the MCP Adapter's default server.
     *
     * @return string
     */
    public static function endpoint_url() {
        return rest_url( 'mcp/mcp-adapter-default-server' );
    }

    public function register_category() {
        wp_register_ability_category( self::CATEGORY, array(
            'label'       => __( 'MMe Elementor Kit', 'mme-elementor-kit' ),
            'description' => __( 'Diagnostics, widget rendering and updates for MMe Elementor Kit.', 'mme-elementor-kit' ),
        ) );
    }

    /**
     * Definitions of every ability: name => args. Also listed on the MCP admin page.
     *
     * @return array
     */
    public function get_definitions() {
        $admin = function () {
            return current_user_can( 'manage_options' );
        };

        return array(
            'mme-kit/site-info'              => array(
                'label'               => __( 'Site info', 'mme-elementor-kit' ),
                'description'         => __( 'Versions (WordPress, PHP, Elementor, Pro, ACF), theme, active plugins, debug settings, Elementor experiments, and MMe Kit state: widgets on / off, areas, update status, capture mode.', 'mme-elementor-kit' ),
                'execute_callback'    => array( $this, 'site_info' ),
                'permission_callback' => $admin,
                'annotations'         => array( 'readonly' => true, 'idempotent' => true ),
            ),
            'mme-kit/get-logs'               => array(
                'label'               => __( 'Get error log', 'mme-elementor-kit' ),
                'description'         => __( 'Recent errors recorded by MMe Kit, newest first: PHP fatal errors always, plus warnings / notices / deprecations and Elementor editor JavaScript errors while capture mode is on. Can also return the tail of wp-content/debug.log.', 'mme-elementor-kit' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'default'    => array(),
                    'properties' => array(
                        'limit'          => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ),
                        'source'         => array( 'type' => 'string', 'enum' => array( 'all', 'php', 'js' ), 'default' => 'all' ),
                        'since'          => array( 'type' => 'integer', 'description' => 'Unix timestamp; only entries after it.' ),
                        'search'         => array( 'type' => 'string', 'description' => 'Only entries containing this text.' ),
                        'debug_log_tail' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 500, 'default' => 0, 'description' => 'Also return the last N lines of WordPress\' debug.log, if it exists.' ),
                    ),
                ),
                'execute_callback'    => array( $this, 'get_logs' ),
                'permission_callback' => $admin,
                'annotations'         => array( 'readonly' => true, 'idempotent' => true ),
            ),
            'mme-kit/set-debug-capture'      => array(
                'label'               => __( 'Set capture mode', 'mme-elementor-kit' ),
                'description'         => __( 'Turn capture mode on for N minutes (records warnings, notices and editor JavaScript errors), or off with 0. Optionally clear the log first.', 'mme-elementor-kit' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'minutes' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 1440 ),
                        'clear'   => array( 'type' => 'boolean', 'default' => false ),
                    ),
                    'required'   => array( 'minutes' ),
                ),
                'execute_callback'    => array( $this, 'set_debug_capture' ),
                'permission_callback' => $admin,
                'annotations'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
            ),
            'mme-kit/inspect-elementor-page' => array(
                'label'               => __( 'Inspect Elementor page', 'mme-elementor-kit' ),
                'description'         => __( 'Elementor structure of a post / page / template: document type, element tree with widget types, and the settings of MMe widgets (or of every element with full=true, or of one widget type).', 'mme-elementor-kit' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                        'widget'  => array( 'type' => 'string', 'description' => 'Only include settings for this widget type, e.g. "heading".' ),
                        'full'    => array( 'type' => 'boolean', 'default' => false, 'description' => 'Include the settings of every element.' ),
                    ),
                    'required'   => array( 'post_id' ),
                ),
                'execute_callback'    => array( $this, 'inspect_page' ),
                'permission_callback' => $admin,
                'annotations'         => array( 'readonly' => true, 'idempotent' => true ),
            ),
            'mme-kit/render-widget'          => array(
                'label'               => __( 'Render widget', 'mme-elementor-kit' ),
                'description'         => __( 'Render an Elementor widget on the server and return its HTML plus every PHP error raised while rendering. Either give widget + settings (optionally post_id as the current post), or post_id + element_id to render a widget already placed on a page.', 'mme-elementor-kit' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'widget'     => array( 'type' => 'string', 'description' => 'Widget name, e.g. "mme-profile-carousel".' ),
                        'settings'   => array( 'type' => 'object', 'description' => 'Widget settings; missing ones use the widget defaults.' ),
                        'post_id'    => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Post used as the current post.' ),
                        'element_id' => array( 'type' => 'string', 'description' => 'Element id on post_id to render as saved.' ),
                    ),
                ),
                'execute_callback'    => array( $this, 'render_widget' ),
                'permission_callback' => $admin,
                'annotations'         => array( 'readonly' => true, 'idempotent' => true ),
            ),
            'mme-kit/clear-elementor-cache'  => array(
                'label'               => __( 'Clear Elementor CSS cache', 'mme-elementor-kit' ),
                'description'         => __( 'Regenerate Elementor CSS files and data (Elementor → Tools → Regenerate files & data).', 'mme-elementor-kit' ),
                'execute_callback'    => array( $this, 'clear_elementor_cache' ),
                'permission_callback' => $admin,
                'annotations'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
            ),
            'mme-kit/check-update'           => array(
                'label'               => __( 'Check for update', 'mme-elementor-kit' ),
                'description'         => __( 'Compare the installed MMe Elementor Kit with the latest version on GitHub (skips the cache).', 'mme-elementor-kit' ),
                'execute_callback'    => array( $this, 'check_update' ),
                'permission_callback' => function () {
                    return current_user_can( 'update_plugins' );
                },
                'annotations'         => array( 'readonly' => true, 'idempotent' => true ),
            ),
            'mme-kit/update-plugin'          => array(
                'label'               => __( 'Update MMe Elementor Kit', 'mme-elementor-kit' ),
                'description'         => __( 'Install the latest MMe Elementor Kit from GitHub now and keep it active. Needs direct file access on the server (no FTP prompt).', 'mme-elementor-kit' ),
                'execute_callback'    => array( $this, 'update_plugin' ),
                'permission_callback' => function () {
                    return current_user_can( 'update_plugins' );
                },
                'annotations'         => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
            ),
        );
    }

    public function register_abilities() {
        foreach ( $this->get_definitions() as $name => $definition ) {
            $annotations = $definition['annotations'];
            unset( $definition['annotations'] );

            wp_register_ability( $name, array_merge( $definition, array(
                'category' => self::CATEGORY,
                'meta'     => array(
                    // The MCP Adapter's default server only exposes abilities
                    // flagged meta.mcp.public; a top-level 'public' is ignored.
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'show_in_rest' => true,
                    'annotations'  => $annotations,
                ),
            ) ) );
        }
    }

    /* ------------------------------------------------------------------ */
    /* Abilities                                                           */
    /* ------------------------------------------------------------------ */

    public function site_info() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = array();
        $active  = (array) get_option( 'active_plugins', array() );
        foreach ( get_plugins() as $file => $plugin ) {
            if ( in_array( $file, $active, true ) || is_plugin_active_for_network( $file ) ) {
                $plugins[] = $plugin['Name'] . ' ' . $plugin['Version'] . ' (' . $file . ')';
            }
        }

        $theme       = wp_get_theme();
        $experiments = array();
        if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) && method_exists( \Elementor\Plugin::$instance->experiments, 'get_active_features' ) ) {
            $experiments = array_keys( \Elementor\Plugin::$instance->experiments->get_active_features() );
        }

        $widgets = array();
        foreach ( Ems_Widgets_Manager::get_widgets() as $key => $widget ) {
            $widgets[ $widget['name'] ] = Ems_Widgets_Manager::is_enabled( $key ) ? 'enabled' : 'disabled';
        }

        $log  = Ems_Debug_Log::get_instance();
        $file = $log->get_file();

        return array(
            'site'      => array(
                'home'        => home_url( '/' ),
                'wordpress'   => get_bloginfo( 'version' ),
                'php'         => PHP_VERSION,
                'multisite'   => is_multisite(),
                'locale'      => get_locale(),
                'memory'      => ini_get( 'memory_limit' ) . ' (WP_MEMORY_LIMIT ' . WP_MEMORY_LIMIT . ')',
                'max_exec'    => ini_get( 'max_execution_time' ),
                'debug'       => array(
                    'WP_DEBUG'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false,
                    'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : null,
                    'display_errors'   => ini_get( 'display_errors' ),
                ),
                'object_cache' => wp_using_ext_object_cache(),
            ),
            'theme'     => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $theme->parent() ? ' (child of ' . $theme->parent()->get( 'Name' ) . ' ' . $theme->parent()->get( 'Version' ) . ')' : '' ),
            'plugins'   => $plugins,
            'elementor' => array(
                'version'     => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
                'pro'         => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null,
                'experiments' => $experiments,
            ),
            'acf'       => class_exists( 'ACF' ) ? ( defined( 'ACF_VERSION' ) ? ACF_VERSION : 'active' ) : null,
            'mme_kit'   => array(
                'version'        => EMS_ELEMENTS_SHARE_VERSION,
                'widgets'        => $widgets,
                'areas'          => Ems_Areas_Manager::get_instance()->get_config(),
                'master_license' => Ems_Master_License::get_instance()->is_active(),
                'update'         => get_site_transient( Ems_Updater::CACHE ),
                'capture_until'  => $log->capture_until() ? gmdate( 'c', $log->capture_until() ) : null,
                'log_size'       => $file && file_exists( $file ) ? filesize( $file ) : 0,
            ),
        );
    }

    public function get_logs( $input = array() ) {
        $input = is_array( $input ) ? $input : array();
        $log   = Ems_Debug_Log::get_instance();

        $result = array(
            'capture_until' => $log->capture_until() ? gmdate( 'c', $log->capture_until() ) : null,
            'entries'       => $log->read( array(
                'limit'  => isset( $input['limit'] ) ? (int) $input['limit'] : 50,
                'source' => isset( $input['source'] ) ? $input['source'] : 'all',
                'since'  => isset( $input['since'] ) ? (int) $input['since'] : 0,
                'search' => isset( $input['search'] ) ? (string) $input['search'] : '',
            ) ),
        );

        $tail = isset( $input['debug_log_tail'] ) ? (int) $input['debug_log_tail'] : 0;
        if ( $tail > 0 ) {
            $result['debug_log'] = $this->tail_debug_log( $tail );
        }
        return $result;
    }

    /**
     * Last lines of WordPress' debug.log.
     *
     * @param int $lines
     * @return array { file, lines } or { error }
     */
    private function tail_debug_log( $lines ) {
        $file = WP_CONTENT_DIR . '/debug.log';
        if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
            $file = WP_DEBUG_LOG;
        }
        if ( ! is_readable( $file ) ) {
            return array( 'error' => 'No readable debug.log (WP_DEBUG_LOG is off or the file does not exist).' );
        }

        $size   = filesize( $file );
        $handle = fopen( $file, 'rb' );
        fseek( $handle, max( 0, $size - 65536 ) );
        $chunk = (string) fread( $handle, 65536 );
        fclose( $handle );

        $all = preg_split( '/\r?\n/', trim( $chunk ) );
        return array(
            'file'     => $file,
            'size'     => $size,
            'modified' => gmdate( 'c', filemtime( $file ) ),
            'lines'    => array_slice( $all, -$lines ),
        );
    }

    public function set_debug_capture( $input = array() ) {
        $log = Ems_Debug_Log::get_instance();
        if ( ! empty( $input['clear'] ) ) {
            $log->clear();
        }
        $until = $log->set_capture( isset( $input['minutes'] ) ? (int) $input['minutes'] : 0 );
        return array(
            'capturing' => $until > 0,
            'until'     => $until ? gmdate( 'c', $until ) : null,
            'cleared'   => ! empty( $input['clear'] ),
        );
    }

    public function inspect_page( $input = array() ) {
        $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $post    = $post_id ? get_post( $post_id ) : null;
        if ( ! $post ) {
            return new \WP_Error( 'mme_kit_not_found', 'Post not found.' );
        }

        $raw  = get_post_meta( $post_id, '_elementor_data', true );
        $data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );

        $elements = array();
        $counts   = array();
        $filter   = isset( $input['widget'] ) ? (string) $input['widget'] : '';
        $full     = ! empty( $input['full'] );

        $walk = function ( $items, $depth ) use ( &$walk, &$elements, &$counts, $filter, $full ) {
            foreach ( (array) $items as $item ) {
                $type = isset( $item['widgetType'] ) ? $item['widgetType'] : $item['elType'];
                $counts[ $type ] = isset( $counts[ $type ] ) ? $counts[ $type ] + 1 : 1;

                $row = array( 'id' => $item['id'], 'depth' => $depth, 'type' => $type );
                $show_settings = $full || ( '' !== $filter ? $type === $filter : 0 === strpos( $type, 'mme-' ) );
                if ( $show_settings ) {
                    $row['settings'] = isset( $item['settings'] ) ? $item['settings'] : array();
                }
                $elements[] = $row;

                if ( ! empty( $item['elements'] ) ) {
                    $walk( $item['elements'], $depth + 1 );
                }
            }
        };
        $walk( is_array( $data ) ? $data : array(), 0 );

        $result = array(
            'post'          => array(
                'id'        => $post->ID,
                'title'     => get_the_title( $post ),
                'type'      => $post->post_type,
                'status'    => $post->post_status,
                'parent'    => $post->post_parent,
                'link'      => get_permalink( $post ),
                'edit_link' => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
            ),
            'elementor'     => array(
                'edit_mode'        => get_post_meta( $post_id, '_elementor_edit_mode', true ),
                'template_type'    => get_post_meta( $post_id, '_elementor_template_type', true ),
                'version'          => get_post_meta( $post_id, '_elementor_version', true ),
                'pro_version'      => get_post_meta( $post_id, '_elementor_pro_version', true ),
                'page_template'    => get_post_meta( $post_id, '_wp_page_template', true ),
                'data_is_valid'    => is_array( $data ),
                'data_bytes'       => is_string( $raw ) ? strlen( $raw ) : null,
            ),
            'widget_counts' => $counts,
            'elements'      => $elements,
        );

        // MMe areas linked to this post.
        $areas = array();
        foreach ( array_keys( Ems_Areas_Manager::get_instance()->get_areas() ) as $name ) {
            $area_id = (int) get_post_meta( $post_id, '_mme_area_' . $name, true );
            if ( $area_id ) {
                $areas[ $name ] = $area_id;
            }
        }
        if ( $areas ) {
            $result['mme_areas'] = $areas;
        }

        return $this->limit_size( $result );
    }

    public function render_widget( $input = array() ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return new \WP_Error( 'mme_kit_no_elementor', 'Elementor is not active.' );
        }
        $elementor = \Elementor\Plugin::$instance;
        $post_id   = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

        if ( ! empty( $input['element_id'] ) ) {
            if ( ! $post_id ) {
                return new \WP_Error( 'mme_kit_missing_post', 'element_id needs post_id.' );
            }
            $document = $elementor->documents->get( $post_id );
            $data     = $document ? $this->find_element( $document->get_elements_data(), (string) $input['element_id'] ) : null;
            if ( ! $data ) {
                return new \WP_Error( 'mme_kit_not_found', 'Element not found on this post.' );
            }
        } else {
            if ( empty( $input['widget'] ) ) {
                return new \WP_Error( 'mme_kit_missing_widget', 'Give widget (+ settings) or post_id + element_id.' );
            }
            $data = array(
                'id'         => substr( md5( uniqid( '', true ) ), 0, 7 ),
                'elType'     => 'widget',
                'widgetType' => sanitize_key( $input['widget'] ),
                'settings'   => isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array(),
                'elements'   => array(),
            );
        }

        if ( 'widget' === $data['elType'] && ! $elementor->widgets_manager->get_widget_types( $data['widgetType'] ) ) {
            return new \WP_Error( 'mme_kit_unknown_widget', sprintf( 'Widget "%s" is not registered (unknown, or switched off on the MMe Kit Widgets page).', $data['widgetType'] ) );
        }

        // The given post becomes the current post, as on its own page.
        global $post;
        $previous_post = $post;
        if ( $post_id && get_post( $post_id ) ) {
            $post = get_post( $post_id );
            setup_postdata( $post );
            $document = $elementor->documents->get( $post_id );
            if ( $document ) {
                $elementor->documents->switch_to_document( $document );
            }
        }

        $log     = Ems_Debug_Log::get_instance();
        $element = null;
        $run     = $log->collect( function () use ( $elementor, $data, &$element ) {
            $element = $elementor->elements_manager->create_element_instance( $data );
            if ( $element ) {
                $element->print_element();
            }
        } );

        if ( $post_id && isset( $document ) && $document ) {
            $elementor->documents->restore_document();
        }
        $post = $previous_post;
        if ( $post ) {
            setup_postdata( $post );
        }

        $html = trim( $run['output'] );
        return $this->limit_size( array(
            'widget'        => isset( $data['widgetType'] ) ? $data['widgetType'] : $data['elType'],
            'rendered'      => (bool) $element,
            'html_bytes'    => strlen( $html ),
            'errors'        => $run['errors'],
            'style_depends' => $element && method_exists( $element, 'get_style_depends' ) ? $element->get_style_depends() : array(),
            'script_depends' => $element && method_exists( $element, 'get_script_depends' ) ? $element->get_script_depends() : array(),
            'html'          => $html,
        ) );
    }

    /**
     * Element data by id, searched through the tree.
     *
     * @param array  $items
     * @param string $id
     * @return array|null
     */
    private function find_element( $items, $id ) {
        foreach ( (array) $items as $item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                return $item;
            }
            if ( ! empty( $item['elements'] ) ) {
                $found = $this->find_element( $item['elements'], $id );
                if ( $found ) {
                    return $found;
                }
            }
        }
        return null;
    }

    public function clear_elementor_cache() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return new \WP_Error( 'mme_kit_no_elementor', 'Elementor is not active.' );
        }
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        return array( 'cleared' => true );
    }

    public function check_update() {
        $updater = Ems_Updater::get_instance();
        $info    = $updater->get_remote_info( true );
        return array(
            'installed'  => EMS_ELEMENTS_SHARE_VERSION,
            'latest'     => isset( $info['version'] ) ? $info['version'] : null,
            'has_update' => $updater->has_update( false ),
            'error'      => isset( $info['error'] ) ? $info['error'] : null,
        );
    }

    public function update_plugin() {
        return Ems_Updater::get_instance()->upgrade_now();
    }

    /**
     * Cut long strings / lists so a result stays within MAX_OUTPUT bytes.
     *
     * @param array $result
     * @return array
     */
    private function limit_size( $result ) {
        $size = strlen( (string) wp_json_encode( $result ) );
        if ( $size <= self::MAX_OUTPUT ) {
            return $result;
        }
        if ( isset( $result['html'] ) ) {
            $result['html'] = substr( $result['html'], 0, self::MAX_OUTPUT / 2 ) . "\n<!-- … cut, " . $size . ' bytes in total -->';
        }
        if ( isset( $result['elements'] ) ) {
            while ( count( $result['elements'] ) > 10 && strlen( (string) wp_json_encode( $result ) ) > self::MAX_OUTPUT ) {
                $result['elements'] = array_slice( $result['elements'], 0, (int) ( count( $result['elements'] ) * 0.75 ) );
            }
            $result['truncated'] = true;
        }
        return $result;
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
