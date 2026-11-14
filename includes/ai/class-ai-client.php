<?php
namespace Ems_Elements_Share\Ai;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI account of this site: the encrypted API key and the calls to the AI service.
 *
 * The provider is an implementation detail and is never named in the admin UI:
 * every message shown to people goes through generic texts (see error()).
 *
 * The key is encrypted with libsodium (bundled with WordPress) using a key derived
 * from the site's security salts in wp-config.php, so a database copy alone does
 * not reveal it. It is never sent to the browser or written to a log.
 */
class Ems_Ai_Client {

    const OPTION = 'mme_kit_ai';

    /**
     * Service settings (internal).
     */
    const BASE_URL = 'https://api.deepseek.com';
    const MODEL    = 'deepseek-chat';

    private static $instance = null;

    /* ------------------------------------------------------------------ */
    /* Key storage                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Stored data: cipher, last4, saved, checked, status, balance.
     *
     * @return array
     */
    public static function data() {
        $data = get_option( self::OPTION, array() );
        return is_array( $data ) ? $data : array();
    }

    public static function has_key() {
        $data = self::data();
        return ! empty( $data['cipher'] );
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

    /**
     * Encrypt and store a key.
     *
     * @param string $key
     * @return boolean
     */
    public static function save_key( $key ) {
        $key = trim( (string) $key );
        if ( '' === $key || strlen( $key ) > 300 || preg_match( '/\s/', $key ) ) {
            return false;
        }
        $nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = sodium_crypto_secretbox( $key, $nonce, self::secret() );

        update_option( self::OPTION, array(
            'cipher' => base64_encode( $nonce . $cipher ),
            'last4'  => substr( $key, -4 ),
            'saved'  => time(),
        ), false );
        return true;
    }

    /**
     * Decrypted key, or '' (none, or unreadable because the salts changed).
     *
     * @return string
     */
    public static function get_key() {
        $data = self::data();
        if ( empty( $data['cipher'] ) ) {
            return '';
        }
        $raw = base64_decode( $data['cipher'], true );
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
     * Remember the result of the last check (shown on the page).
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
        /**
         * Internal: base URL of the AI service (used by tests).
         */
        return untrailingslashit( apply_filters( 'mme_kit_ai_base_url', self::BASE_URL ) );
    }

    /**
     * Check the key and read the account balance.
     *
     * @return array|\WP_Error { ok, balance }
     */
    public static function check() {
        $key = self::get_key();
        if ( '' === $key ) {
            return self::error( 'no_key' );
        }

        $response = wp_remote_get( self::base_url() . '/user/balance', array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Accept'        => 'application/json',
            ),
        ) );
        if ( is_wp_error( $response ) ) {
            self::update_data( array( 'checked' => time(), 'status' => 'network' ) );
            return self::error( 'network' );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            $error = self::error_for_status( $code );
            self::update_data( array( 'checked' => time(), 'status' => str_replace( 'mme_kit_ai_', '', $error->get_error_code() ) ) );
            return $error;
        }

        $body    = json_decode( wp_remote_retrieve_body( $response ), true );
        $balance = '';
        if ( ! empty( $body['balance_infos'][0] ) ) {
            $info    = $body['balance_infos'][0];
            $balance = trim( ( isset( $info['total_balance'] ) ? $info['total_balance'] : '' ) . ' ' . ( isset( $info['currency'] ) ? $info['currency'] : '' ) );
        }
        $available = ! isset( $body['is_available'] ) || $body['is_available'];
        $status    = $available ? 'ok' : 'no_balance';

        self::update_data( array( 'checked' => time(), 'status' => $status, 'balance' => $balance ) );
        return $available ? array( 'ok' => true, 'balance' => $balance ) : self::error( 'no_balance' );
    }

    /**
     * Chat completion returning JSON text, streamed so long answers do not hit
     * proxy timeouts. $on_progress( int $chars ) is called while text arrives.
     *
     * @param array    $messages
     * @param callable $on_progress
     * @return string|\WP_Error The answer's text.
     */
    public static function complete_json( $messages, $on_progress = null ) {
        $key = self::get_key();
        if ( '' === $key ) {
            return self::error( 'no_key' );
        }

        $payload = wp_json_encode( array(
            'model'           => self::MODEL,
            'messages'        => $messages,
            'response_format' => array( 'type' => 'json_object' ),
            'temperature'     => 0.7,
            'max_tokens'      => 8000,
            'stream'          => true,
        ) );

        if ( ! function_exists( 'curl_init' ) ) {
            return self::complete_without_stream( $key, $payload );
        }

        $content = '';
        $buffer  = '';
        $raw     = '';
        $ch      = curl_init( self::base_url() . '/chat/completions' );
        curl_setopt_array( $ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'Authorization: Bearer ' . $key,
            ),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 240,
            CURLOPT_WRITEFUNCTION  => function ( $ch, $chunk ) use ( &$content, &$buffer, &$raw, $on_progress ) {
                $raw    .= strlen( $raw ) < 4000 ? $chunk : '';
                $buffer .= $chunk;
                while ( false !== ( $pos = strpos( $buffer, "\n" ) ) ) {
                    $line   = trim( substr( $buffer, 0, $pos ) );
                    $buffer = substr( $buffer, $pos + 1 );
                    if ( 0 !== strpos( $line, 'data:' ) ) {
                        continue; // Blank lines and ": keep-alive" comments.
                    }
                    $data = trim( substr( $line, 5 ) );
                    if ( '[DONE]' === $data ) {
                        continue;
                    }
                    $event = json_decode( $data, true );
                    if ( isset( $event['choices'][0]['delta']['content'] ) ) {
                        $content .= $event['choices'][0]['delta']['content'];
                    }
                }
                if ( $on_progress ) {
                    call_user_func( $on_progress, strlen( $content ) );
                }
                return strlen( $chunk );
            },
        ) );
        $ok    = curl_exec( $ch );
        $code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $errno = curl_errno( $ch );
        curl_close( $ch );

        if ( ! $ok || $errno ) {
            return self::error( CURLE_OPERATION_TIMEDOUT === $errno ? 'timeout' : 'network' );
        }
        if ( 200 !== $code ) {
            return self::error_for_status( $code );
        }
        if ( '' === trim( $content ) ) {
            return self::error( 'empty' );
        }
        return $content;
    }

    private static function complete_without_stream( $key, $payload ) {
        $payload  = str_replace( '"stream":true', '"stream":false', $payload );
        $response = wp_remote_post( self::base_url() . '/chat/completions', array(
            'timeout' => 240,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ),
            'body'    => $payload,
        ) );
        if ( is_wp_error( $response ) ) {
            return self::error( 'network' );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return self::error_for_status( $code );
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['choices'][0]['message']['content'] ) ) {
            return self::error( 'empty' );
        }
        return $body['choices'][0]['message']['content'];
    }

    /* ------------------------------------------------------------------ */
    /* Generic messages                                                     */
    /* ------------------------------------------------------------------ */

    private static function error_for_status( $code ) {
        switch ( $code ) {
            case 401:
            case 403:
                return self::error( 'invalid_key' );
            case 402:
                return self::error( 'no_balance' );
            case 429:
                return self::error( 'busy' );
            case 400:
            case 422:
                return self::error( 'rejected' );
        }
        return self::error( 'service' );
    }

    /**
     * Error with a message that does not name the AI service.
     *
     * @param string $code
     * @return \WP_Error
     */
    public static function error( $code ) {
        $messages = array(
            'no_key'      => __( 'No AI API key on this site yet.', 'mme-elementor-kit' ),
            'invalid_key' => __( 'The AI API key is not valid. Check it, or create a new one in your AI account.', 'mme-elementor-kit' ),
            'no_balance'  => __( 'The AI account has no balance left. Top it up, then try again.', 'mme-elementor-kit' ),
            'busy'        => __( 'The AI service is busy (too many requests). Wait a minute and try again.', 'mme-elementor-kit' ),
            'rejected'    => __( 'The AI service refused the request. Try a shorter or simpler description.', 'mme-elementor-kit' ),
            'service'     => __( 'The AI service is not answering right now. Try again in a few minutes.', 'mme-elementor-kit' ),
            'network'     => __( 'This site could not reach the AI service (network or firewall).', 'mme-elementor-kit' ),
            'timeout'     => __( 'The AI took too long to answer. Try again, or ask for a smaller layout.', 'mme-elementor-kit' ),
            'empty'       => __( 'The AI returned an empty answer. Try again.', 'mme-elementor-kit' ),
            'bad_json'    => __( 'The AI answer could not be read as a layout. Try again.', 'mme-elementor-kit' ),
            'no_sections' => __( 'The AI answer had no usable sections. Try describing the layout in more detail.', 'mme-elementor-kit' ),
        );
        return new \WP_Error( 'mme_kit_ai_' . $code, isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['service'] );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
