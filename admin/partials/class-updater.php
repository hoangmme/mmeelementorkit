<?php
namespace Ems_Elements_Share\Admin;

/**
 * Class responsible for updating the plugin from its public GitHub repository.
 *
 * The Version header of the main plugin file on the release branch is the
 * latest version. When it is newer than the installed one, WordPress shows
 * the usual "update available" notice and installs the branch zip.
 */
class Ems_Updater {

    const REPO = 'hoangmme/mmeelementorkit';

    const BRANCH = 'main';

    /**
     * Slug used by the plugin information popup.
     */
    const SLUG = 'mme-elementor-kit';

    /**
     * Site transient caching the remote version info.
     */
    const CACHE = 'mme_kit_update_info';

    private static $instance = null;

    /**
     * Notice shown after a manual check: array( type, message ).
     *
     * @var array|null
     */
    private $notice = null;

    public function __construct() {
        // Hooks
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ) );

        add_action( 'admin_init', array( $this, 'handle_form' ) );
        add_action( 'ems_elements_share_updates_notice', array( $this, 'add_notice' ) );
    }

    /**
     * Url of a raw file on the release branch.
     *
     * @param string $file
     * @return string
     */
    private function raw_url( $file ) {
        return 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/' . $file;
    }

    /**
     * Zip of the release branch.
     *
     * @return string
     */
    public function get_package_url() {
        return 'https://github.com/' . self::REPO . '/archive/refs/heads/' . self::BRANCH . '.zip';
    }

    /**
     * Repository page.
     *
     * @return string
     */
    public function get_repo_url() {
        return 'https://github.com/' . self::REPO;
    }

    /**
     * Latest version info from GitHub, cached for 6 hours (1 hour after an error).
     *
     * @param boolean $force Skip the cache.
     * @return array { version, requires, requires_php, changelog, checked } or { error, checked }
     */
    public function get_remote_info( $force = false ) {
        $cached = get_site_transient( self::CACHE );
        if ( ! $force && is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get( $this->raw_url( basename( EMS_ELEMENTS_SHARE_BASENAME ) ), array( 'timeout' => 15 ) );
        $code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
        $headers  = 200 === $code ? $this->parse_headers( wp_remote_retrieve_body( $response ) ) : array();

        if ( empty( $headers['Version'] ) ) {
            if ( is_wp_error( $response ) ) {
                $error = $response->get_error_message();
            } elseif ( 404 === $code ) {
                $error = __( 'The GitHub repository or branch was not found. Is the repository public?', 'mme-elementor-kit' );
            } else {
                /* translators: %d: HTTP status code */
                $error = sprintf( __( 'Could not read the latest version from GitHub (HTTP %d).', 'mme-elementor-kit' ), $code );
            }
            $info = array( 'error' => $error, 'checked' => time() );
            set_site_transient( self::CACHE, $info, HOUR_IN_SECONDS );
            return $info;
        }

        $readme = wp_remote_get( $this->raw_url( 'README.txt' ), array( 'timeout' => 15 ) );

        $info = array(
            'version'      => $headers['Version'],
            'requires'     => isset( $headers['Requires at least'] ) ? $headers['Requires at least'] : '',
            'requires_php' => isset( $headers['Requires PHP'] ) ? $headers['Requires PHP'] : '',
            'changelog'    => is_wp_error( $readme ) ? '' : $this->parse_changelog( wp_remote_retrieve_body( $readme ) ),
            'checked'      => time(),
        );
        set_site_transient( self::CACHE, $info, 6 * HOUR_IN_SECONDS );
        return $info;
    }

    /**
     * Read "Name: value" headers from a plugin file header comment.
     *
     * @param string $contents
     * @return array
     */
    public function parse_headers( $contents ) {
        $headers = array();
        $names   = array( 'Version', 'Requires at least', 'Requires PHP' );
        foreach ( $names as $name ) {
            if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $contents, $match ) ) {
                $headers[ $name ] = trim( $match[1] );
            }
        }
        return $headers;
    }

    /**
     * Turn the README "== Changelog ==" section into simple HTML.
     *
     * @param string $readme
     * @return string
     */
    public function parse_changelog( $readme ) {
        $start = strpos( $readme, '== Changelog ==' );
        if ( false === $start ) {
            return '';
        }

        $html    = '';
        $in_list = false;
        $lines   = explode( "\n", substr( $readme, $start + strlen( '== Changelog ==' ) ) );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( preg_match( '/^==[^=]/', $line ) ) {
                break;
            }
            if ( 0 === strpos( $line, '* ' ) ) {
                if ( ! $in_list ) {
                    $html   .= '<ul>';
                    $in_list = true;
                }
                $html .= '<li>' . esc_html( substr( $line, 2 ) ) . '</li>';
                continue;
            }
            if ( $in_list ) {
                $html   .= '</ul>';
                $in_list = false;
            }
            if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $match ) ) {
                $html .= '<h4>' . esc_html( $match[1] ) . '</h4>';
            }
        }
        if ( $in_list ) {
            $html .= '</ul>';
        }
        return $html;
    }

    /**
     * Whether GitHub has a newer version than the installed one.
     *
     * @param boolean $fetch False to only look at the cache (no remote request).
     * @return boolean
     */
    public function has_update( $fetch = true ) {
        $info = $fetch ? $this->get_remote_info() : get_site_transient( self::CACHE );
        return ! empty( $info['version'] ) && version_compare( $info['version'], EMS_ELEMENTS_SHARE_VERSION, '>' );
    }

    /**
     * Admin url that installs the update.
     *
     * @return string
     */
    public function get_update_url() {
        $plugin = EMS_ELEMENTS_SHARE_BASENAME;
        return wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $plugin ) ), 'upgrade-plugin_' . $plugin );
    }

    /**
     * Add this plugin to the WordPress update list.
     *
     * @param object $transient
     * @return object
     */
    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
            return $transient;
        }

        $info = $this->get_remote_info();
        if ( empty( $info['version'] ) ) {
            return $transient;
        }

        $item = (object) array(
            'id'           => 'github.com/' . self::REPO,
            'slug'         => self::SLUG,
            'plugin'       => EMS_ELEMENTS_SHARE_BASENAME,
            'new_version'  => $info['version'],
            'url'          => $this->get_repo_url(),
            'package'      => $this->get_package_url(),
            'requires'     => $info['requires'],
            'requires_php' => $info['requires_php'],
            'icons'        => array( 'svg' => EMS_ELEMENTS_SHARE_URL . 'admin/elementor/template-library/assets/editor/templates/img/mme-kit-logo.svg' ),
        );

        if ( version_compare( $info['version'], EMS_ELEMENTS_SHARE_VERSION, '>' ) ) {
            $transient->response[ EMS_ELEMENTS_SHARE_BASENAME ] = $item;
        } else {
            $transient->no_update[ EMS_ELEMENTS_SHARE_BASENAME ] = $item;
        }
        return $transient;
    }

    /**
     * Data for the "View details" popup.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object|array
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) {
            return $result;
        }

        $info = $this->get_remote_info();
        return (object) array(
            'name'          => 'MMe Elementor Kit',
            'slug'          => self::SLUG,
            'version'       => isset( $info['version'] ) ? $info['version'] : EMS_ELEMENTS_SHARE_VERSION,
            'author'        => '<a href="https://mme.vn">MMe.vn</a>',
            'homepage'      => 'https://mme.vn',
            'requires'      => isset( $info['requires'] ) ? $info['requires'] : '',
            'requires_php'  => isset( $info['requires_php'] ) ? $info['requires_php'] : '',
            'download_link' => $this->get_package_url(),
            'sections'      => array(
                'description' => esc_html__( 'Bộ công cụ Elementor của MMe.vn: chia sẻ template từ site Master sang các site Client và widget động (Info Table ACF/SCF).', 'mme-elementor-kit' ),
                'changelog'   => ! empty( $info['changelog'] ) ? $info['changelog'] : '',
            ),
        );
    }

    /**
     * GitHub zips extract to "mmeelementorkit-main/". Rename it to the installed
     * folder name, otherwise WordPress would treat the update as a new plugin.
     *
     * @param string       $source
     * @param string       $remote_source
     * @param \WP_Upgrader $upgrader
     * @param array        $hook_extra
     * @return string|\WP_Error
     */
    public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        if ( ! isset( $hook_extra['plugin'] ) || EMS_ELEMENTS_SHARE_BASENAME !== $hook_extra['plugin'] ) {
            return $source;
        }

        $desired = trailingslashit( $remote_source ) . dirname( EMS_ELEMENTS_SHARE_BASENAME ) . '/';
        if ( trailingslashit( $source ) === $desired ) {
            return $source;
        }

        global $wp_filesystem;
        if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
            return $desired;
        }
        return new \WP_Error( 'mme_kit_rename_failed', __( 'Could not prepare the MMe Elementor Kit update folder.', 'mme-elementor-kit' ) );
    }

    /**
     * Forget the cached version after any update.
     *
     * @return void
     */
    public function purge_cache() {
        delete_site_transient( self::CACHE );
    }

    /**
     * "Check for updates" button.
     *
     * @return void
     */
    public function handle_form() {
        if ( ! ( isset( $_POST['action'] ) && $_POST['action'] === 'mme_kit_check_updates' ) ) {
            return;
        }

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_check_updates', '_wp_nonce_check_updates' );

        $info = $this->get_remote_info( true );

        // Rebuild the WordPress update list so the Plugins page and the update link agree.
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        if ( ! empty( $info['error'] ) ) {
            $this->notice = array( 'error', $info['error'] );
        } elseif ( $this->has_update() ) {
            /* translators: %s: version */
            $this->notice = array( 'success', sprintf( __( 'Version %s is available.', 'mme-elementor-kit' ), $info['version'] ) );
        } else {
            $this->notice = array( 'success', __( 'You are using the latest version.', 'mme-elementor-kit' ) );
        }
    }

    /**
     * Display the check result.
     *
     * @return void
     */
    public function add_notice() {
        if ( $this->notice === null ) {
            return;
        }
        list( $type, $message ) = $this->notice;
        ?>
        <div class="ems-alert ems-alert--<?php echo esc_attr( $type ); ?>">
            <span class="dashicons <?php echo 'success' === $type ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
            <?php echo esc_html( $message ); ?>
        </div>
        <?php
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
