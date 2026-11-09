<?php

use Ems_Elements_Share\Admin\Ems_Key_Manager;
use Ems_Elements_Share\Admin\Ems_Keys_Transfer;
use Ems_Elements_Share\Admin\Ems_Master_License;
use Ems_Elements_Share\Admin\Ems_Receiver_Manager;
use Ems_Elements_Share\Admin\Ems_Updater;

/**
 * Class responsible for handling menu items and pages.
 */
class Ems_Elements_Share_Menu_Handler {

	/**
	 * Slug of the master (server) page.
	 */
	const MASTER_SLUG = 'mme-elementor-kit';

	/**
	 * Slug of the client (receiver) page.
	 */
	const CLIENT_SLUG = 'mme-elementor-kit-client';

	/**
	 * Slug of the widgets page.
	 */
	const WIDGETS_SLUG = 'mme-elementor-kit-widgets';

	/**
	 * Slug of the updates page.
	 */
	const UPDATES_SLUG = 'mme-elementor-kit-updates';

	/**
	 * Register all menu items.
	 *
	 * @return void
	 */
	public function register_menu_items() {
		$capability = 'manage_options';

		add_menu_page( __( 'MMe Elementor Kit', 'mme-elementor-kit' ), __( 'MMe Kit', 'mme-elementor-kit' ), $capability, self::MASTER_SLUG, array( $this, 'render_master_page' ), 'dashicons-share', 11 );

		// The first submenu replaces the auto-generated duplicate of the parent item.
		add_submenu_page( self::MASTER_SLUG, __( 'Master (Server)', 'mme-elementor-kit' ), __( 'Master (Server)', 'mme-elementor-kit' ), $capability, self::MASTER_SLUG, array( $this, 'render_master_page' ) );
		add_submenu_page( self::MASTER_SLUG, __( 'Client', 'mme-elementor-kit' ), __( 'Client', 'mme-elementor-kit' ), $capability, self::CLIENT_SLUG, array( $this, 'render_client_page' ) );
		add_submenu_page( self::MASTER_SLUG, __( 'Widgets', 'mme-elementor-kit' ), __( 'Widgets', 'mme-elementor-kit' ), $capability, self::WIDGETS_SLUG, array( $this, 'render_widgets_page' ) );

		$update_count = Ems_Updater::get_instance()->has_update( false ) ? ' <span class="update-plugins count-1"><span class="plugin-count">1</span></span>' : '';
		add_submenu_page( self::MASTER_SLUG, __( 'Updates', 'mme-elementor-kit' ), __( 'Updates', 'mme-elementor-kit' ) . $update_count, 'update_plugins', self::UPDATES_SLUG, array( $this, 'render_updates_page' ) );
	}

	/**
	 * Whether the current admin request is one of the plugin pages.
	 *
	 * @return boolean
	 */
	public static function is_plugin_page() {
		return isset( $_GET['page'] ) && in_array( $_GET['page'], array( self::MASTER_SLUG, self::CLIENT_SLUG, self::WIDGETS_SLUG, self::UPDATES_SLUG ), true );
	}

	/**
	 * Output the master (server) page.
	 *
	 * @return void
	 */
	public function render_master_page() {
		$license     = Ems_Master_License::get_instance();
		$active_page = self::MASTER_SLUG;

		if ( ! $license->is_active() ) {
			require_once __DIR__ . '/templates/master-locked.php';
			return;
		}

		$key_manager   = Ems_Key_Manager::get_instance();
		$keys_transfer = Ems_Keys_Transfer::get_instance();
		require_once __DIR__ . '/templates/master-page.php';
	}

	/**
	 * Output the client (receiver) page.
	 *
	 * @return void
	 */
	public function render_client_page() {
		$receiver_manager = Ems_Receiver_Manager::get_instance();
		$active_page      = self::CLIENT_SLUG;
		require_once __DIR__ . '/templates/client-page.php';
	}

	/**
	 * Output the widgets page.
	 *
	 * @return void
	 */
	public function render_widgets_page() {
		$active_page = self::WIDGETS_SLUG;
		require_once __DIR__ . '/templates/widgets-page.php';
	}

	/**
	 * Output the updates page.
	 *
	 * @return void
	 */
	public function render_updates_page() {
		$updater     = Ems_Updater::get_instance();
		$active_page = self::UPDATES_SLUG;
		require_once __DIR__ . '/templates/updates-page.php';
	}
}
