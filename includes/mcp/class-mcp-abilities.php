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

    /**
     * WordPress.org slug of the official MCP Adapter plugin.
     */
    const ADAPTER_SLUG = 'mcp-adapter';

    /**
     * Admin page slug of "MMe Kit → MCP & Logs" (Ems_Elements_Share_Menu_Handler::MCP_SLUG).
     */
    const PAGE_SLUG = 'mme-elementor-kit-mcp';

    private static $instance = null;

    public function __construct() {
        add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
        add_action( 'admin_init', array( $this, 'handle_adapter_form' ) );
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
     * Whether MCP Adapter code is loaded, from its plugin or bundled in another
     * plugin (Elementor 4.x ships a copy). See adapter_state() for the details.
     *
     * @return boolean
     */
    public static function mcp_adapter_active() {
        return class_exists( '\WP\MCP\Core\McpAdapter' ) || class_exists( '\WP\MCP\Plugin' );
    }

    /**
     * Endpoint of the MCP Adapter's default server.
     *
     * @return string
     */
    public static function endpoint_url() {
        return rest_url( 'mcp/mcp-adapter-default-server' );
    }

    /**
     * Whether the default MCP server route is registered (the endpoint really answers).
     *
     * @return boolean
     */
    public static function endpoint_registered() {
        if ( ! function_exists( 'rest_get_server' ) ) {
            return false;
        }
        $routes = rest_get_server()->get_routes();
        return isset( $routes['/mcp/mcp-adapter-default-server'] );
    }

    /**
     * Plugin file of the MCP Adapter plugin ("mcp-adapter/mcp-adapter.php"), or '' when not installed.
     *
     * @return string
     */
    public static function adapter_plugin_file() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach ( get_plugins() as $file => $data ) {
            if ( 0 === strpos( $file, self::ADAPTER_SLUG . '/' ) || 'MCP Adapter' === $data['Name'] ) {
                return $file;
            }
        }
        return '';
    }

    /**
     * Where the MCP connection comes from, for the MCP page.
     *
     * @return array { file, installed, active, version, bundled_by, endpoint }
     */
    public static function adapter_state() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $file    = self::adapter_plugin_file();
        $active  = $file && is_plugin_active( $file );

        // Adapter code loaded without its plugin: find the plugin that bundles it.
        $bundled_by = '';
        if ( ! $active && class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
            $reflection = new \ReflectionClass( '\WP\MCP\Core\McpAdapter' );
            $path       = wp_normalize_path( (string) $reflection->getFileName() );
            $root       = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
            $bundled_by = __( 'another plugin', 'mme-elementor-kit' );
            if ( 0 === strpos( $path, $root ) ) {
                $parts  = explode( '/', substr( $path, strlen( $root ) ) );
                $folder = $parts[0];
                foreach ( $plugins as $plugin_file => $data ) {
                    if ( 0 === strpos( $plugin_file, $folder . '/' ) ) {
                        $bundled_by = trim( $data['Name'] . ' ' . $data['Version'] );
                        break;
                    }
                }
            }
        }

        return array(
            'file'       => $file,
            'installed'  => '' !== $file,
            'active'     => $active,
            'version'    => $file ? $plugins[ $file ]['Version'] : '',
            'bundled_by' => $bundled_by,
            'endpoint'   => self::endpoint_registered(),
        );
    }

    /**
     * "Install & activate" / "Activate" button for the MCP Adapter plugin on the MCP page.
     * Redirects back to the page with the result (so the next load runs with the plugin active).
     *
     * @return void
     */
    public function handle_adapter_form() {
        if ( ! ( isset( $_POST['action'] ) && 'mme_kit_mcp_adapter' === $_POST['action'] ) ) {
            return;
        }
        check_admin_referer( 'mme_kit_mcp_adapter', '_wp_nonce_mcp_adapter' );

        $result = self::install_adapter();
        if ( is_wp_error( $result ) ) {
            set_transient( 'mme_kit_mcp_adapter_error_' . get_current_user_id(), $result->get_error_message(), 5 * MINUTE_IN_SECONDS );
            $status = 'error';
        } else {
            $status = $result;
        }

        wp_safe_redirect( add_query_arg( 'mcp_adapter', $status, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
        exit;
    }

    /**
     * Install the MCP Adapter plugin from WordPress.org when missing, then activate it.
     *
     * @return string|\WP_Error 'installed' (installed and activated), 'activated' or 'active'.
     */
    public static function install_adapter() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $file      = self::adapter_plugin_file();
        $installed = false;

        if ( ! $file ) {
            if ( ! current_user_can( 'install_plugins' ) ) {
                return new \WP_Error( 'mme_kit_cap', __( 'Your account cannot install plugins.', 'mme-elementor-kit' ) );
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            if ( 'direct' !== get_filesystem_method() ) {
                return new \WP_Error( 'mme_kit_filesystem', __( 'WordPress needs FTP credentials to install plugins on this server. Use "Install from Plugins" instead.', 'mme-elementor-kit' ) );
            }

            $api = plugins_api( 'plugin_information', array(
                'slug'   => self::ADAPTER_SLUG,
                'fields' => array( 'sections' => false ),
            ) );
            if ( is_wp_error( $api ) ) {
                return $api;
            }

            $skin     = new \WP_Ajax_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader( $skin );
            $result   = $upgrader->install( $api->download_link );

            if ( is_wp_error( $result ) ) {
                return $result;
            }
            if ( is_wp_error( $skin->result ) ) {
                return $skin->result;
            }
            if ( $skin->get_errors()->has_errors() ) {
                return $skin->get_errors();
            }
            if ( ! $result ) {
                return new \WP_Error( 'mme_kit_install_failed', __( 'The MCP Adapter plugin could not be installed.', 'mme-elementor-kit' ) );
            }

            wp_clean_plugins_cache();
            $file      = $upgrader->plugin_info() ? $upgrader->plugin_info() : self::adapter_plugin_file();
            $installed = true;
        }

        if ( ! $file ) {
            return new \WP_Error( 'mme_kit_install_failed', __( 'The MCP Adapter plugin was installed but its file was not found.', 'mme-elementor-kit' ) );
        }
        if ( is_plugin_active( $file ) ) {
            return 'active';
        }
        if ( ! current_user_can( 'activate_plugin', $file ) ) {
            return new \WP_Error( 'mme_kit_cap', __( 'Your account cannot activate plugins.', 'mme-elementor-kit' ) );
        }

        $activated = activate_plugin( $file );
        // With display_errors on, the adapter's harmless "WP_MCP_VERSION already defined" warning (when
        // Elementor's copy is loaded too) counts as unexpected output, though the plugin is active.
        if ( is_wp_error( $activated ) && ! ( 'unexpected_output' === $activated->get_error_code() && is_plugin_active( $file ) ) ) {
            return $activated;
        }

        return $installed ? 'installed' : 'activated';
    }

    /**
     * Result message of the MCP Adapter button, read once after the redirect.
     *
     * @return array|null array( type, message )
     */
    public static function adapter_notice() {
        $status = isset( $_GET['mcp_adapter'] ) ? sanitize_key( wp_unslash( $_GET['mcp_adapter'] ) ) : '';
        switch ( $status ) {
            case 'installed':
                return array( 'success', __( 'MCP Adapter installed and activated.', 'mme-elementor-kit' ) );
            case 'activated':
                return array( 'success', __( 'MCP Adapter activated.', 'mme-elementor-kit' ) );
            case 'active':
                return array( 'success', __( 'MCP Adapter is already active.', 'mme-elementor-kit' ) );
            case 'error':
                $key     = 'mme_kit_mcp_adapter_error_' . get_current_user_id();
                $message = get_transient( $key );
                delete_transient( $key );
                return array( 'error', $message ? $message : __( 'The MCP Adapter could not be installed.', 'mme-elementor-kit' ) );
        }
        return null;
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
            'mme-kit/update-element'         => array(
                'label'               => __( 'Update Elementor element', 'mme-elementor-kit' ),
                'description'         => __( 'Change the settings of one element (widget, container, ...) already on an Elementor page and save the page like the editor does (a revision is kept, so it can be restored from the editor History). Settings are merged into the current ones; the previous values of every changed key are returned as a backup. Use dry_run to preview.', 'mme-elementor-kit' ),
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'post_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
                        'element_id' => array( 'type' => 'string', 'description' => 'Element id, from mme-kit/inspect-elementor-page.' ),
                        'settings'   => array( 'type' => 'object', 'description' => 'Settings to set, e.g. {"html": "..."} for an HTML widget or {"title": "..."} for a heading. A null value removes the key.' ),
                        'dry_run'    => array( 'type' => 'boolean', 'default' => false, 'description' => 'Return what would change without saving.' ),
                    ),
                    'required'   => array( 'post_id', 'element_id', 'settings' ),
                ),
                'execute_callback'    => array( $this, 'update_element' ),
                'permission_callback' => function ( $input = array() ) {
                    $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
                    return current_user_can( 'manage_options' ) && $post_id && current_user_can( 'edit_post', $post_id );
                },
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

    public function update_element( $input = array() ) {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return new \WP_Error( 'mme_kit_no_elementor', 'Elementor is not active.' );
        }
        $post_id    = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
        $element_id = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';
        $changes    = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
        if ( ! $changes ) {
            return new \WP_Error( 'mme_kit_no_settings', 'settings is empty.' );
        }

        $document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
        if ( ! $document || ! $document->is_built_with_elementor() ) {
            return new \WP_Error( 'mme_kit_not_elementor', 'This post is not built with Elementor.' );
        }

        $data     = $document->get_elements_data();
        $previous = array();
        $type     = '';
        $found    = $this->apply_settings( $data, $element_id, $changes, $previous, $type );
        if ( ! $found ) {
            return new \WP_Error( 'mme_kit_not_found', 'Element not found on this post.' );
        }

        $changed = array();
        foreach ( $changes as $key => $value ) {
            if ( ! array_key_exists( $key, $previous ) || $previous[ $key ] !== $value ) {
                $changed[] = $key;
            }
        }

        $result = array(
            'post_id'  => $post_id,
            'element'  => $element_id,
            'type'     => $type,
            'changed'  => $changed,
            'previous' => $previous,
            'saved'    => false,
        );
        if ( ! empty( $input['dry_run'] ) || ! $changed ) {
            return $this->limit_size( $result );
        }

        $saved = $document->save( array( 'elements' => $data ) );
        if ( ! $saved ) {
            return new \WP_Error( 'mme_kit_save_failed', 'Elementor did not save the page.' );
        }

        // Check what was stored (sanitizing can drop values, e.g. HTML for users without unfiltered_html).
        $document = \Elementor\Plugin::$instance->documents->get( $post_id, false );
        $stored   = $this->find_element( $document->get_elements_data(), $element_id );
        $mismatch = array();
        foreach ( $changed as $key ) {
            $now = isset( $stored['settings'][ $key ] ) ? $stored['settings'][ $key ] : null;
            if ( $now !== $changes[ $key ] ) {
                $mismatch[] = $key;
            }
        }

        $result['saved']     = true;
        $result['not_as_sent'] = $mismatch;
        $result['link']      = get_permalink( $post_id );
        return $this->limit_size( $result );
    }

    /**
     * Merge settings into the element with this id, in place.
     *
     * @param array  $items     Elements tree (by reference).
     * @param string $id
     * @param array  $changes   Settings to set; null removes the key.
     * @param array  $previous  Filled with the old value of every key in $changes (null when unset).
     * @param string $type      Filled with the widget type or element type.
     * @return boolean Found.
     */
    private function apply_settings( &$items, $id, $changes, &$previous, &$type ) {
        foreach ( $items as &$item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                $settings = isset( $item['settings'] ) && is_array( $item['settings'] ) ? $item['settings'] : array();
                foreach ( $changes as $key => $value ) {
                    $previous[ $key ] = array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
                    if ( null === $value ) {
                        unset( $settings[ $key ] );
                    } else {
                        $settings[ $key ] = $value;
                    }
                }
                $item['settings'] = $settings;
                $type             = ! empty( $item['widgetType'] ) ? $item['widgetType'] : $item['elType'];
                return true;
            }
            if ( ! empty( $item['elements'] ) && $this->apply_settings( $item['elements'], $id, $changes, $previous, $type ) ) {
                return true;
            }
        }
        return false;
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
