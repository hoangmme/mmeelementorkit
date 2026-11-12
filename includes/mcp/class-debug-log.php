<?php
namespace Ems_Elements_Share\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Error log that works without touching wp-config.php.
 *
 * - Fatal errors are always recorded (checked once at shutdown, nearly free).
 * - "Capture mode" (on for a limited time) also records warnings, notices,
 *   deprecations and JavaScript errors from the Elementor editor and preview.
 *
 * Entries are JSON lines in uploads/mme-kit-logs/, a folder closed to the web.
 */
class Ems_Debug_Log {

    /**
     * Option holding the timestamp until which capture mode is on.
     */
    const CAPTURE_OPTION = 'mme_kit_debug_capture_until';

    /**
     * Rotate the log above this size (bytes).
     */
    const MAX_SIZE = 524288;

    /**
     * Entries written per request at most, so a loop of notices can't flood the log.
     */
    const MAX_PER_REQUEST = 40;

    const FATAL_TYPES = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

    private static $instance = null;

    /**
     * Entries written by this request, keyed by signature.
     *
     * @var array
     */
    private $written = array();

    /**
     * Errors collected while a ability captures a block of code (see collect()).
     *
     * @var array|null
     */
    private $collected = null;

    /**
     * Notice shown on the MCP page after a form action: array( type, message ).
     *
     * @var array|null
     */
    private $notice = null;

    public function __construct() {
        register_shutdown_function( array( $this, 'record_fatal' ) );

        if ( $this->is_capturing() ) {
            set_error_handler( array( $this, 'handle_error' ) );
            add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'print_js_catcher' ) );
            add_action( 'elementor/preview/enqueue_scripts', array( $this, 'print_js_catcher' ) );
        }

        if ( is_admin() && false === get_option( self::CAPTURE_OPTION ) ) {
            add_option( self::CAPTURE_OPTION, 0 );
        }

        add_action( 'wp_ajax_mme_kit_log_js', array( $this, 'ajax_log_js' ) );
        add_action( 'admin_init', array( $this, 'handle_form' ) );
        add_action( 'ems_elements_share_mcp_notice', array( $this, 'add_notice' ) );
    }

    /**
     * Whether capture mode is on.
     *
     * @return boolean
     */
    public function is_capturing() {
        return (int) get_option( self::CAPTURE_OPTION, 0 ) > time();
    }

    /**
     * Timestamp until which capture mode is on, 0 when off.
     *
     * @return int
     */
    public function capture_until() {
        $until = (int) get_option( self::CAPTURE_OPTION, 0 );
        return $until > time() ? $until : 0;
    }

    /**
     * Turn capture mode on for some minutes, or off with 0.
     *
     * @param int $minutes
     * @return int Timestamp until which it is on, 0 when off.
     */
    public function set_capture( $minutes ) {
        $minutes = max( 0, min( 1440, (int) $minutes ) );
        // Kept (autoloaded) even when off: a missing option would cost a query on every request.
        $until = $minutes ? time() + $minutes * MINUTE_IN_SECONDS : 0;
        update_option( self::CAPTURE_OPTION, $until, true );
        return $until;
    }

    /**
     * Log folder, created on first use and closed to the web.
     *
     * @return string Empty when uploads is not writable.
     */
    private function get_dir() {
        $uploads = wp_upload_dir( null, false );
        if ( empty( $uploads['basedir'] ) ) {
            return '';
        }
        $dir = trailingslashit( $uploads['basedir'] ) . 'mme-kit-logs';
        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                return '';
            }
            @file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
            @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
        }
        return $dir;
    }

    /**
     * First line of the log: a .php file that exits, so the log can't be read
     * over the web even where .htaccess is ignored (nginx).
     */
    const FILE_HEADER = "<?php exit; ?>\n";

    /**
     * Log file path.
     *
     * @return string Empty when uploads is not writable.
     */
    public function get_file() {
        $dir = $this->get_dir();
        return $dir ? $dir . '/errors-' . substr( wp_hash( 'mme-kit-debug-log' ), 0, 12 ) . '.php' : '';
    }

    /**
     * Append one entry.
     *
     * @param array $entry { source, type, message, file?, line?, context? }
     * @return void
     */
    public function write( $entry ) {
        $signature = md5( $entry['source'] . $entry['message'] . ( isset( $entry['file'] ) ? $entry['file'] . $entry['line'] : '' ) );
        if ( isset( $this->written[ $signature ] ) || count( $this->written ) >= self::MAX_PER_REQUEST ) {
            return;
        }
        $this->written[ $signature ] = true;

        $file = $this->get_file();
        if ( ! $file ) {
            return;
        }

        $entry = array_merge( array(
            'time'    => gmdate( 'c' ),
            'request' => ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] . ' ' : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? substr( wp_unslash( $_SERVER['REQUEST_URI'] ), 0, 300 ) : '' ),
            'version' => EMS_ELEMENTS_SHARE_VERSION,
        ), $entry );
        $entry['message'] = substr( (string) $entry['message'], 0, 4000 );

        if ( ! file_exists( $file ) ) {
            file_put_contents( $file, self::FILE_HEADER, LOCK_EX );
        } elseif ( filesize( $file ) > self::MAX_SIZE ) {
            // Keep the newer half.
            $contents = (string) file_get_contents( $file );
            $contents = substr( $contents, (int) ( strlen( $contents ) / 2 ) );
            $contents = substr( $contents, strpos( $contents, "\n" ) + 1 );
            file_put_contents( $file, self::FILE_HEADER . $contents, LOCK_EX );
        }

        file_put_contents( $file, wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX );
    }

    /**
     * Newest entries first.
     *
     * @param array $args { limit, source: all|php|js, since: unix timestamp, search }
     * @return array
     */
    public function read( $args = array() ) {
        $args = wp_parse_args( $args, array( 'limit' => 50, 'source' => 'all', 'since' => 0, 'search' => '' ) );
        $file = $this->get_file();
        if ( ! $file || ! file_exists( $file ) ) {
            return array();
        }

        $lines   = array_reverse( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) );
        $entries = array();
        foreach ( $lines as $line ) {
            $entry = '{' === substr( $line, 0, 1 ) ? json_decode( $line, true ) : null;
            if ( ! is_array( $entry ) ) {
                continue;
            }
            if ( 'all' !== $args['source'] && $entry['source'] !== $args['source'] ) {
                continue;
            }
            if ( $args['since'] && strtotime( $entry['time'] ) < (int) $args['since'] ) {
                break;
            }
            if ( '' !== $args['search'] && false === stripos( $line, $args['search'] ) ) {
                continue;
            }
            $entries[] = $entry;
            if ( count( $entries ) >= max( 1, (int) $args['limit'] ) ) {
                break;
            }
        }
        return $entries;
    }

    /**
     * Delete the log.
     *
     * @return void
     */
    public function clear() {
        $file = $this->get_file();
        if ( $file && file_exists( $file ) ) {
            unlink( $file );
        }
    }

    /**
     * Readable name of a PHP error level.
     *
     * @param int $type
     * @return string
     */
    private function type_name( $type ) {
        $names = array(
            E_ERROR             => 'Fatal error',
            E_PARSE             => 'Parse error',
            E_CORE_ERROR        => 'Core error',
            E_COMPILE_ERROR     => 'Compile error',
            E_USER_ERROR        => 'User error',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_WARNING           => 'Warning',
            E_USER_WARNING      => 'User warning',
            E_NOTICE            => 'Notice',
            E_USER_NOTICE       => 'User notice',
            E_DEPRECATED        => 'Deprecated',
            E_USER_DEPRECATED   => 'User deprecated',
        );
        return isset( $names[ $type ] ) ? $names[ $type ] : 'Error ' . $type;
    }

    /**
     * Shutdown: record the fatal error that ended the request, if any.
     *
     * @return void
     */
    public function record_fatal() {
        $error = error_get_last();
        if ( ! $error || ! in_array( $error['type'], self::FATAL_TYPES, true ) ) {
            return;
        }
        $entry = array(
            'source'  => 'php',
            'type'    => $this->type_name( $error['type'] ),
            'message' => $error['message'],
            'file'    => $error['file'],
            'line'    => $error['line'],
        );
        if ( is_array( $this->collected ) ) {
            $this->collected[] = $entry;
        }
        $this->write( $entry );
    }

    /**
     * Error handler: records the error, then lets PHP / the previous handler handle it as usual.
     *
     * @param int    $type
     * @param string $message
     * @param string $file
     * @param int    $line
     * @return boolean
     */
    public function handle_error( $type, $message, $file = '', $line = 0 ) {
        // Respect @-silenced calls (error_reporting() is lowered inside them).
        if ( ! ( error_reporting() & $type ) && null === $this->collected ) {
            return false;
        }
        $entry = array(
            'source'  => 'php',
            'type'    => $this->type_name( $type ),
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
        );
        if ( is_array( $this->collected ) ) {
            $this->collected[] = $entry;
        }
        if ( $this->is_capturing() ) {
            $this->write( $entry );
        }
        return false;
    }

    /**
     * Run a callback and return what it printed / returned with every PHP error it raised.
     *
     * @param callable $callback
     * @return array { result, output, errors }
     */
    public function collect( $callback ) {
        $this->collected = array();
        set_error_handler( array( $this, 'handle_error' ) );
        ob_start();
        try {
            $result = call_user_func( $callback );
        } catch ( \Throwable $e ) {
            $result             = null;
            $this->collected[] = array(
                'source'  => 'php',
                'type'    => get_class( $e ),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => substr( $e->getTraceAsString(), 0, 4000 ),
            );
        }
        $output = ob_get_clean();
        restore_error_handler();

        $errors          = $this->collected;
        $this->collected = null;
        return array( 'result' => $result, 'output' => $output, 'errors' => $errors );
    }

    /**
     * Catch JavaScript errors in the Elementor editor and preview while capture mode is on.
     *
     * @return void
     */
    public function print_js_catcher() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }
        $config = array(
            'url'   => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'mme_kit_log_js' ),
            'where' => doing_action( 'elementor/editor/after_enqueue_scripts' ) ? 'editor' : 'preview',
        );
        wp_register_script( 'mme-kit-js-catcher', false, array(), EMS_ELEMENTS_SHARE_VERSION, false );
        wp_enqueue_script( 'mme-kit-js-catcher' );
        wp_add_inline_script( 'mme-kit-js-catcher', 'window.mmeKitJsCatcher=' . wp_json_encode( $config ) . ';' . <<<'JS'
(function (c) {
  var sent = 0, seen = {};
  function text(v) {
    if (v instanceof Error) return v.name + ': ' + v.message;
    if (typeof v === 'string') return v;
    try { return JSON.stringify(v); } catch (e) { return String(v); }
  }
  function send(type, message, extra) {
    message = String(message || '').slice(0, 2000);
    if (!message || seen[message] || ++sent > 30) return;
    seen[message] = 1;
    var data = new FormData();
    data.append('action', 'mme_kit_log_js');
    data.append('_wpnonce', c.nonce);
    data.append('type', type);
    data.append('message', message);
    data.append('context', JSON.stringify(Object.assign({ where: c.where, page: location.href }, extra || {})).slice(0, 2000));
    if (!(navigator.sendBeacon && navigator.sendBeacon(c.url, data))) {
      fetch(c.url, { method: 'POST', body: data, credentials: 'same-origin' });
    }
  }
  window.addEventListener('error', function (e) {
    if (e.target && e.target !== window && (e.target.src || e.target.href)) {
      send('Resource failed', (e.target.src || e.target.href));
      return;
    }
    send('Error', e.message, { file: e.filename, line: e.lineno, col: e.colno, stack: e.error && e.error.stack ? String(e.error.stack).slice(0, 1500) : '' });
  }, true);
  window.addEventListener('unhandledrejection', function (e) {
    send('Unhandled rejection', text(e.reason), { stack: e.reason && e.reason.stack ? String(e.reason.stack).slice(0, 1500) : '' });
  });
  var original = console.error;
  console.error = function () {
    send('console.error', Array.prototype.map.call(arguments, text).join(' '));
    return original.apply(console, arguments);
  };
})(window.mmeKitJsCatcher);
JS
        );
    }

    /**
     * Receive one JavaScript error.
     *
     * @return void
     */
    public function ajax_log_js() {
        if ( ! check_ajax_referer( 'mme_kit_log_js', false, false ) || ! current_user_can( 'edit_posts' ) || ! $this->is_capturing() ) {
            wp_send_json_error( null, 403 );
        }
        $context = isset( $_POST['context'] ) ? json_decode( wp_unslash( $_POST['context'] ), true ) : array();
        $this->write( array(
            'source'  => 'js',
            'type'    => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'Error',
            'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
            'context' => is_array( $context ) ? map_deep( $context, 'sanitize_textarea_field' ) : array(),
        ) );
        wp_send_json_success();
    }

    /**
     * Capture on / off and clear buttons on the MCP page.
     *
     * @return void
     */
    public function handle_form() {
        if ( ! ( isset( $_POST['action'] ) && $_POST['action'] === 'mme_kit_debug_log' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Are you cheating?', 'mme-elementor-kit' ) );
        }
        check_admin_referer( 'mme_kit_debug_log', '_wp_nonce_debug_log' );

        $do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
        if ( 'start' === $do ) {
            $this->set_capture( 60 );
            $this->notice = array( 'success', __( 'Capture mode is on for 60 minutes. Reproduce the problem now (open the editor, view the page).', 'mme-elementor-kit' ) );
        } elseif ( 'stop' === $do ) {
            $this->set_capture( 0 );
            $this->notice = array( 'success', __( 'Capture mode is off. Fatal errors are still recorded.', 'mme-elementor-kit' ) );
        } elseif ( 'clear' === $do ) {
            $this->clear();
            $this->notice = array( 'success', __( 'Log cleared.', 'mme-elementor-kit' ) );
        }
    }

    /**
     * Display the form result.
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
            <span class="dashicons dashicons-yes-alt"></span>
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
