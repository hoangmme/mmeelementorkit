<?php
namespace Ems_Elements_Share\Ai;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MMe AI account of this site: the encrypted MMe API key and the calls to the
 * MMe AI service (the chatbot platform), which writes the layouts.
 *
 * The site never talks to an AI provider directly: MMe runs the AI, uses the
 * customer's business data to fill in real facts, and counts usage against the
 * account's monthly limit.
 *
 * The key is encrypted with libsodium (bundled with WordPress) using a key derived
 * from the site's security salts in wp-config.php, so a database copy alone does
 * not reveal it. It is never sent to the browser or written to a log.
 */
class Ems_Ai_Client {

    const OPTION = 'mme_kit_ai';

    /**
     * MMe AI service. Override with define( 'MME_KIT_AI_API_URL', 'https://…' ) in
     * wp-config.php (e.g. for a staging server) or the mme_kit_ai_base_url filter.
     */
    const BASE_URL = 'https://chat.mme.vn';

    const KEY_PATTERN = '/^mme_[A-Za-z0-9_-]{20,200}$/';

    private static $instance = null;

    /* ------------------------------------------------------------------ */
    /* Key storage                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Stored data: cipher, last4, saved, kind, checked, status, account, usage.
     *
     * @return array
     */
    public static function data() {
        $data = get_option( self::OPTION, array() );
        return is_array( $data ) ? $data : array();
    }

    /**
     * Whether an MMe API key is stored.
     */
    public static function has_key() {
        $data = self::data();
        return ! empty( $data['cipher'] ) && isset( $data['kind'] ) && 'mme' === $data['kind'];
    }

    /**
     * A key from before 1.8 (a direct AI provider key). It is no longer used.
     */
    public static function has_legacy_key() {
        $data = self::data();
        return ! empty( $data['cipher'] ) && ( ! isset( $data['kind'] ) || 'mme' !== $data['kind'] );
    }

    /**
     * Last 4 characters, for "••••a1b2".
     *
     * @return string
     */
    public static function last4() {
        $data = self::data();
        return isset( $data['last4'] ) ? (string) $data['last4'] : '';
    }

    private static function secret() {
        return sodium_crypto_generichash( wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|mme-kit-ai', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
    }

    public static function is_valid_format( $key ) {
        return (bool) preg_match( self::KEY_PATTERN, trim( (string) $key ) );
    }

    /**
     * Encrypt and store an MMe API key.
     *
     * @param string $key
     * @return boolean
     */
    public static function save_key( $key ) {
        $key = trim( (string) $key );
        if ( ! self::is_valid_format( $key ) ) {
            return false;
        }
        $nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = sodium_crypto_secretbox( $key, $nonce, self::secret() );

        update_option( self::OPTION, array(
            'cipher' => base64_encode( $nonce . $cipher ),
            'last4'  => substr( $key, -4 ),
            'saved'  => time(),
            'kind'   => 'mme',
        ), false );
        return true;
    }

    /**
     * Decrypted MMe key, or '' (none, a legacy key, or unreadable because the salts changed).
     *
     * @return string
     */
    public static function get_key() {
        if ( ! self::has_key() ) {
            return '';
        }
        $data = self::data();
        $raw  = base64_decode( $data['cipher'], true );
        if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
            return '';
        }
        $nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $key    = sodium_crypto_secretbox_open( $cipher, $nonce, self::secret() );
        return false === $key ? '' : $key;
    }

    public static function remove_key() {
        delete_option( self::OPTION );
    }

    /**
     * Remember the result of the last call (shown on the page).
     *
     * @param array $fields
     */
    private static function update_data( $fields ) {
        $data = self::data();
        if ( empty( $data['cipher'] ) ) {
            return;
        }
        update_option( self::OPTION, array_merge( $data, $fields ), false );
    }

    /* ------------------------------------------------------------------ */
    /* Calls                                                                */
    /* ------------------------------------------------------------------ */

    private static function base_url() {
        $url = defined( 'MME_KIT_AI_API_URL' ) ? MME_KIT_AI_API_URL : self::BASE_URL;
        /**
         * Base URL of the MMe AI service.
         */
        return untrailingslashit( apply_filters( 'mme_kit_ai_base_url', $url ) );
    }

    /**
     * @param string     $method GET or POST.
     * @param string     $path   e.g. /api/v1/account
     * @param array|null $body
     * @return array|\WP_Error Decoded JSON body.
     */
    private static function request( $method, $path, $body = null ) {
        $key = self::get_key();
        if ( '' === $key ) {
            return self::error( 'no_key' );
        }
        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'MMeElementorKit/' . ( defined( 'EMS_ELEMENTS_SHARE_VERSION' ) ? EMS_ELEMENTS_SHARE_VERSION : 'dev' ) . '; ' . home_url( '/' ),
            ),
        );
        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }
        $response = wp_remote_request( self::base_url() . $path, $args );
        if ( is_wp_error( $response ) ) {
            return self::error( 'network' );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            return self::error_from_response( $code, is_array( $data ) ? $data : array() );
        }
        return is_array( $data ) ? $data : self::error( 'service' );
    }

    /**
     * Check the key and read the account and this month's usage.
     *
     * @return array|\WP_Error { ok, account, usage }
     */
    public static function check() {
        $data = self::request( 'GET', '/api/v1/account' );
        if ( is_wp_error( $data ) ) {
            self::update_data( array( 'checked' => time(), 'status' => str_replace( 'mme_kit_ai_', '', $data->get_error_code() ) ) );
            return $data;
        }
        $account = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $usage   = isset( $data['usage'] ) && is_array( $data['usage'] ) ? self::clean_usage( $data['usage'] ) : array();
        self::update_data( array( 'checked' => time(), 'status' => 'ok', 'account' => $account, 'usage' => $usage ) );
        return array( 'ok' => true, 'account' => $account, 'usage' => $usage );
    }

    private static function clean_usage( $usage ) {
        return array(
            'used'   => isset( $usage['used'] ) ? (int) $usage['used'] : 0,
            'quota'  => isset( $usage['quota'] ) ? (int) $usage['quota'] : 0,
            'period' => isset( $usage['period'] ) ? sanitize_text_field( $usage['period'] ) : '',
        );
    }

    /**
     * Start a generation on the MMe AI service.
     *
     * @param array $request brief, kind, site_name, output ('layout' or 'html_block'), primary_color.
     * @return array|\WP_Error { id, usage }
     */
    public static function start_job( $request ) {
        $body = array(
            'brief'         => (string) $request['brief'],
            'kind'          => (string) $request['kind'],
            'site_name'     => (string) $request['site_name'],
            'output'        => 'html_block' === $request['output'] ? 'html_block' : 'layout',
            'use_knowledge' => ! empty( $request['use_knowledge'] ),
        );
        if ( ! empty( $request['primary_color'] ) && preg_match( '/^#[0-9a-f]{6}$/i', $request['primary_color'] ) ) {
            $body['primary_color'] = $request['primary_color'];
        }
        $job = self::request( 'POST', '/api/v1/pages', $body );
        if ( is_wp_error( $job ) ) {
            return $job;
        }
        $usage = isset( $job['usage'] ) && is_array( $job['usage'] ) ? self::clean_usage( $job['usage'] ) : array();
        if ( $usage ) {
            self::update_data( array( 'usage' => $usage ) );
        }
        if ( empty( $job['id'] ) || ! self::is_job_id( $job['id'] ) ) {
            return self::error( 'service' );
        }
        return array( 'id' => $job['id'], 'usage' => $usage );
    }

    public static function is_job_id( $id ) {
        return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $id );
    }

    /**
     * Current state of a generation.
     *
     * @param string $id
     * @return array|\WP_Error { status: running|done, result } — a failed job comes back as a WP_Error.
     */
    public static function job_state( $id ) {
        if ( ! self::is_job_id( $id ) ) {
            return self::error( 'rejected' );
        }
        $state = self::request( 'GET', '/api/v1/pages/' . $id );
        if ( is_wp_error( $state ) ) {
            return $state;
        }
        $status = isset( $state['status'] ) ? $state['status'] : '';
        if ( 'error' === $status ) {
            $code    = isset( $state['error']['code'] ) ? sanitize_key( $state['error']['code'] ) : 'service';
            $message = isset( $state['error']['message'] ) ? sanitize_text_field( $state['error']['message'] ) : '';
            return self::error( $code, $message );
        }
        if ( 'done' === $status ) {
            return array( 'status' => 'done', 'result' => isset( $state['result'] ) && is_array( $state['result'] ) ? $state['result'] : array() );
        }
        return array( 'status' => 'running' );
    }

    /* ------------------------------------------------------------------ */
    /* Messages                                                             */
    /* ------------------------------------------------------------------ */

    private static function error_from_response( $code, $data ) {
        $detail = isset( $data['detail'] ) && is_array( $data['detail'] ) ? $data['detail'] : array();
        $reason = isset( $detail['code'] ) ? sanitize_key( $detail['code'] ) : '';
        $text   = isset( $detail['message'] ) ? sanitize_text_field( $detail['message'] ) : '';
        if ( $reason ) {
            return self::error( $reason, $text );
        }
        if ( 401 === $code || 403 === $code ) {
            return self::error( 'invalid_key' );
        }
        if ( 429 === $code ) {
            return self::error( 'busy' );
        }
        if ( 422 === $code || 400 === $code ) {
            return self::error( 'rejected' );
        }
        return self::error( 'service' );
    }

    /**
     * Error with a message for people. $detail (from the MMe service) is used for
     * the codes whose wording depends on the account, such as the monthly limit.
     *
     * @param string $code
     * @param string $detail
     * @return \WP_Error
     */
    public static function error( $code, $detail = '' ) {
        $messages = array(
            'no_key'         => __( 'No MMe API key on this site yet.', 'mme-elementor-kit' ),
            'invalid_key'    => __( 'The MMe API key is not valid or was revoked. Ask MMe for a new key.', 'mme-elementor-kit' ),
            'feature_off'    => __( 'AI page generation is not enabled for this MMe account. Contact MMe.', 'mme-elementor-kit' ),
            'quota_exceeded' => __( 'This month\'s AI page limit is used up. It resets next month, or contact MMe for more.', 'mme-elementor-kit' ),
            'busy'           => __( 'Another layout is still being written. Wait for it to finish, then try again.', 'mme-elementor-kit' ),
            'rate_limited'   => __( 'Too many requests. Wait a minute and try again.', 'mme-elementor-kit' ),
            'rejected'       => __( 'The request was refused. Try a shorter or simpler description.', 'mme-elementor-kit' ),
            'service'        => __( 'The MMe AI service is not answering right now. Try again in a few minutes.', 'mme-elementor-kit' ),
            'network'        => __( 'This site could not reach the MMe AI service (network or firewall).', 'mme-elementor-kit' ),
            'timeout'        => __( 'The AI took too long to answer. Try again, or ask for a smaller layout.', 'mme-elementor-kit' ),
            'interrupted'    => __( 'The AI service restarted while writing. Try again.', 'mme-elementor-kit' ),
            'empty'          => __( 'The AI returned an empty answer. Try again.', 'mme-elementor-kit' ),
            'bad_json'       => __( 'The AI answer could not be read as a layout. Try again.', 'mme-elementor-kit' ),
            'no_sections'    => __( 'The AI answer had no usable sections. Try describing the layout in more detail.', 'mme-elementor-kit' ),
        );
        $message = isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['service'];
        if ( 'quota_exceeded' === $code && '' !== $detail ) {
            $message = $detail;
        }
        return new \WP_Error( 'mme_kit_ai_' . $code, $message );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
