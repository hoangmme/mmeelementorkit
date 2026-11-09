<?php
/**
 * Ems Elementor Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

define( 'EMS_ELEMENTOR__FILE__', __FILE__ );
define( 'EMS_ELEMENTOR__DIR__', __DIR__ );


/**
 * Load EMS Elementor
 *
 * Load the widgets after Elementor (and other plugins) are loaded.
 *
 * @since 1.0.0
 */
function ems_elementor_load() {

	// Notice if the Elementor is not active.
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'ems_elementor_fail_load' );

		return;
	}

	// Check required version.
	$elementor_version_required = '1.8.0';
	if ( ! version_compare( ELEMENTOR_VERSION, $elementor_version_required, '>=' ) ) {
		add_action( 'admin_notices', 'ems_elementor_fail_load_out_of_date' );

		return;
	}
	
	// Require templates.
	require EMS_ELEMENTOR__DIR__ . '/templates/templates.php';
}

add_action( 'init', 'ems_elementor_load', -999 );


function ems_elementor_fail_load_out_of_date() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );
	$message      = '<p>' . __( 'MMe Elementor Kit is not working because you are using an old version of Elementor.', 'mme-elementor-kit' ) . '</p>';
	$message     .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $upgrade_link, __( 'Update Elementor Now', 'mme-elementor-kit' ) ) . '</p>';

	echo '<div class="error">' . $message . '</div>';
}
