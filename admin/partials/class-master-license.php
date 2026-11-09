<?php
namespace Ems_Elements_Share\Admin;

/**
 * Class responsible for unlocking Master (server) mode.
 *
 * Master mode only works with an activation code issued by MMe.vn for this
 * site's domain. Codes are signed with MMe's private Ed25519 key, the plugin
 * only ships the public key, so codes cannot be forged or moved to another domain.
 *
 * Code format: base64url( payload json ) . "." . base64url( signature )
 * Payload: { "domain": "mme.vn", "issued": 1700000000, "expires": 0, "note": "" }
 */
class Ems_Master_License {

    /**
     * MMe.vn public signing key (base64).
     */
    const PUBLIC_KEY = 'cf+2YoBwCpgkeBV25iAG9VcV1QaMtH0dh2TAQMPuWkk=';

    /**
     * Option holding the activation code.
     */
    const OPTION = 'mme_kit_master_license';

    private static $instance = null;

    /**
     * Verified payload, or WP_Error. Cached per request.
     *
     * @var array|\WP_Error|null
     */
    private $status = null;

    /**
     * Notice shown after activating / deactivating: array( type, message ).
     *
     * @var array|null
     */
    private $notice = null;

    public function __construct() {
        // Hooks
        add_action( 'admin_init', array( $this, 'handle_form' ) );
        add_action( 'ems_elements_share_license_notice', array( $this, 'add_notice' ) );
    }

    /**
     * Whether Master mode is unlocked on this site.
     *
     * @return boolean
     */
    public function is_active() {
        return ! is_wp_error( $this->get_status() );
    }

    /**
     * Verified license payload, or the reason it is not valid.
     *
     * @return array|\WP_Error
     */
    public function get_status() {
        if ( $this->status === null ) {
            $code         = get_option( self::OPTION, '' );
            $this->status = strlen( $code ) ? $this->verify( $code ) : new \WP_Error( 'missing', __( 'Master mode is not activated on this site.', 'mme-elementor-kit' ) );
        }
        return $this->status;
    }

    /**
     * This site's domain, the way codes are issued.
     *
     * @return string
     */
    public function get_site_domain() {
        return self::normalize_domain( wp_parse_url( home_url(), PHP_URL_HOST ) );
    }

    /**
     * Lowercase host without "www.".
     *
     * @param string $domain
     * @return string
     */
    public static function normalize_domain( $domain ) {
        return preg_replace( '/^www\./', '', strtolower( trim( (string) $domain ) ) );
    }

    /**
     * Verify an activation code for this site.
     *
     * @param string $code
     * @return array|\WP_Error Payload when valid.
     */
    public function verify( $code ) {
        $invalid = new \WP_Error( 'invalid', __( 'Invalid activation code.', 'mme-elementor-kit' ) );

        $parts = explode( '.', trim( $code ) );
        if ( count( $parts ) !== 2 ) {
            return $invalid;
        }

        $payload_raw = self::base64url_decode( $parts[0] );
        $signature   = self::base64url_decode( $parts[1] );
        if ( ! $payload_raw || ! $signature || strlen( $signature ) !== 64 ) {
            return $invalid;
        }

        // sodium_* is available through sodium_compat bundled with WordPress 5.2+.
        try {
            $verified = sodium_crypto_sign_verify_detached( $signature, $payload_raw, base64_decode( self::PUBLIC_KEY ) );
        } catch ( \Exception $e ) {
            $verified = false;
        }
        if ( ! $verified ) {
            return new \WP_Error( 'signature', __( 'This activation code was not issued by MMe.vn.', 'mme-elementor-kit' ) );
        }

        $payload = json_decode( $payload_raw, true );
        if ( ! is_array( $payload ) || empty( $payload['domain'] ) ) {
            return $invalid;
        }

        if ( self::normalize_domain( $payload['domain'] ) !== $this->get_site_domain() ) {
            /* translators: 1: domain in the code, 2: this site's domain */
            return new \WP_Error( 'domain', sprintf( __( 'This activation code is for %1$s, not for this site (%2$s).', 'mme-elementor-kit' ), $payload['domain'], $this->get_site_domain() ) );
        }

        if ( ! empty( $payload['expires'] ) && time() > (int) $payload['expires'] ) {
            return new \WP_Error( 'expired', __( 'This activation code has expired. Please ask MMe.vn for a new one.', 'mme-elementor-kit' ) );
        }

        return $payload;
    }

    /**
     * Save or remove the activation code.
     *
     * @return void
     */
    public function handle_form() {
        if ( ! ( isset( $_POST['action'] ) && in_array( $_POST['action'], array( 'mme_kit_activate_master', 'mme_kit_deactivate_master' ), true ) ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_master_license', '_wp_nonce_master_license' );

        if ( $_POST['action'] === 'mme_kit_deactivate_master' ) {
            delete_option( self::OPTION );
            $this->status = null;
            $this->notice = array( 'success', __( 'Master mode deactivated.', 'mme-elementor-kit' ) );
            return;
        }

        $code   = isset( $_POST['mme_kit_master_code'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_POST['mme_kit_master_code'] ) ) : '';
        $result = $this->verify( $code );
        if ( is_wp_error( $result ) ) {
            $this->notice = array( 'error', $result->get_error_message() );
            return;
        }

        update_option( self::OPTION, $code );
        $this->status = $result;
        $this->notice = array( 'success', __( 'Master mode activated.', 'mme-elementor-kit' ) );
    }

    /**
     * Display the activation result.
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

    /**
     * Decode base64url, false on failure.
     *
     * @param string $data
     * @return string|false
     */
    private static function base64url_decode( $data ) {
        return base64_decode( strtr( $data, '-_', '+/' ), true );
    }

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
