<?php

use Ems_Elements_Share\Admin\Ems_Template_Library;
use Ems_Elements_Share\Admin\Ems_Rest_Api;
use Ems_Elements_Share\Admin\Ems_Key_Manager;
use Ems_Elements_Share\Admin\Ems_Keys_Transfer;
use Ems_Elements_Share\Admin\Ems_Master_License;
use Ems_Elements_Share\Admin\Ems_Receiver_Manager;
use Ems_Elements_Share\Admin\Ems_Updater;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://mme.vn
 * @since      1.0.0
 *
 * @package    Ems_Elements_Share
 * @subpackage Ems_Elements_Share/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Ems_Elements_Share
 * @subpackage Ems_Elements_Share/admin
 * @author     MMe.vn <https://mme.vn>
 */
class Ems_Elements_Share_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->include_partials();
		$this->setup_hooks();

	}

	/**
	 * Include partial classes.
	 *
	 * @return void
	 */
	public function include_partials() {
		// Menu handler class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-elements-share-menu-handler.php';

		// Template library class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-template-library.php';

		// Master license class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-master-license.php';

		// Key Handler class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-key-manager.php';

		// Key export / import class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-keys-transfer.php';

		// Receiver handler class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-receiver-manager.php';

		// Rest Api Handler class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-rest-api.php';

		// GitHub updater class
		require_once EMS_ELEMENTS_SHARE_PATH . 'admin/partials/class-updater.php';
	}

	/**
	 * Setup all action and filter hooks.
	 *
	 * @return void
	 */
	public function setup_hooks() {

		// Initialize master license
		Ems_Master_License::get_instance();

		// Initialize key manager
		Ems_Key_Manager::get_instance();

		// Initialize key export / import
		Ems_Keys_Transfer::get_instance();

		// Initialize receiver manager
		Ems_Receiver_Manager::get_instance();

		// Admin menu
		$menu_handler = new Ems_Elements_Share_Menu_Handler();
		add_action( 'admin_menu', array( $menu_handler, 'register_menu_items' ) );

		// Initialize template library class
		new Ems_Template_Library();

		// Initialize rest api
		new Ems_Rest_Api();

		// Initialize GitHub updater
		Ems_Updater::get_instance();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Ems_Elements_Share_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Ems_Elements_Share_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( ! Ems_Elements_Share_Menu_Handler::is_plugin_page() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'mme-kit-select2-css', plugin_dir_url( __FILE__ ) . 'css/select2.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'mme-kit-admin-css', plugin_dir_url( __FILE__ ) . 'css/mme-kit-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Ems_Elements_Share_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Ems_Elements_Share_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( ! Ems_Elements_Share_Menu_Handler::is_plugin_page() ) {
			return;
		}

		wp_enqueue_script( 'mme-kit-promise-polyfill', plugin_dir_url( __FILE__ ) . 'js/promise-polyfill.js', array( 'jquery' ), null, true );
		wp_enqueue_script( 'mme-kit-alert', plugin_dir_url( __FILE__ ) . 'js/sweetalert2.all.min.js', array( 'jquery', 'mme-kit-promise-polyfill' ), null, true );
		wp_enqueue_script( 'mme-kit-select2-js', plugin_dir_url( __FILE__ ) . 'js/select2.js', array( 'jquery' ), null, true );
		wp_enqueue_script( 'mme-kit-admin-js', plugin_dir_url( __FILE__ ) . 'js/mme-kit-admin.js',
		array( 'jquery', 'mme-kit-select2-js', 'mme-kit-alert', 'mme-kit-promise-polyfill' ), $this->version, true );

		$translations = array(
			'nonce'           => wp_create_nonce( 'ems_elements_share_admin' ),
			'delete_confirm'  => __( 'Are you sure?', 'mme-elementor-kit' ),
			'delete_text'     => __( 'Sites using this key will lose access after you save.', 'mme-elementor-kit' ),
			'cancel_btn'      => __( 'Cancel', 'mme-elementor-kit' ),
			'delete_btn'      => __( 'Yes, delete it!', 'mme-elementor-kit' ),
			'reset_confirm'   => __( 'Reset connected site count?', 'mme-elementor-kit' ),
			'reset_text'      => __( 'Use this when a client site was removed without disconnecting.', 'mme-elementor-kit' ),
			'reset_btn'       => __( 'Yes, reset', 'mme-elementor-kit' ),
			'copied'          => __( 'Copied!', 'mme-elementor-kit' ),
			'copy'            => __( 'Copy', 'mme-elementor-kit' ),
			'loading'         => __( 'Loading templates from master…', 'mme-elementor-kit' ),
			'no_templates'    => __( 'The master site has not shared any templates with this key yet.', 'mme-elementor-kit' ),
			'templates_count' => __( '%d templates available', 'mme-elementor-kit' ),
			'request_failed'  => __( 'Request failed. Please try again.', 'mme-elementor-kit' ),
			'replace_confirm' => __( 'Replace all keys?', 'mme-elementor-kit' ),
			'replace_text'    => __( 'Keys that are not in the file will be deleted and their client sites will lose access.', 'mme-elementor-kit' ),
			'replace_btn'     => __( 'Yes, replace', 'mme-elementor-kit' ),
			'ai_short'        => __( 'Describe the layout in a sentence or more.', 'mme-elementor-kit' ),
			'ai_start'        => __( 'Sending to the AI…', 'mme-elementor-kit' ),
			'ai_progress'     => __( 'Writing the layout… %d characters', 'mme-elementor-kit' ),
			'ai_done'         => __( 'Done: %1$d sections added to %2$s.', 'mme-elementor-kit' ),
			'ai_edit'         => __( 'Edit in Elementor', 'mme-elementor-kit' ),
			'ai_view'         => __( 'Preview', 'mme-elementor-kit' ),
			'ai_failed'       => __( 'The request stopped before the layout was ready. Try again.', 'mme-elementor-kit' ),
		);
		wp_localize_script( 'mme-kit-admin-js', 'mme_kit', $translations );
	}

	/**
	 * Check if plugin updated to a new version.
	 *
	 * @return void
	 */
	public function if_plugin_updated() {
		if(get_option('Ems_Elements_Share_Version') !== EMS_ELEMENTS_SHARE_VERSION) {
			// Plugin has been updated


			// Update the version
			update_option('Ems_Elements_Share_Version', EMS_ELEMENTS_SHARE_VERSION);
		}
	}

}
