<?php

use Ems_Elements_Share\Admin\Ems_Key_Manager;
use Ems_Elements_Share\Admin\Ems_Keys_Transfer;
use Ems_Elements_Share\Admin\Ems_Receiver_Manager;

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
	}

	/**
	 * Whether the current admin request is one of the plugin pages.
	 *
	 * @return boolean
	 */
	public static function is_plugin_page() {
		return isset( $_GET['page'] ) && in_array( $_GET['page'], array( self::MASTER_SLUG, self::CLIENT_SLUG ), true );
	}

	/**
	 * Output the master (server) page.
	 *
	 * @return void
	 */
	public function render_master_page() {
		$key_manager   = Ems_Key_Manager::get_instance();
		$keys_transfer = Ems_Keys_Transfer::get_instance();
		$active_page   = self::MASTER_SLUG;
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
}
