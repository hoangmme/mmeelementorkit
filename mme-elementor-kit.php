<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://mme.vn
 * @since             1.0.0
 * @package           Ems_Elements_Share
 *
 * @wordpress-plugin
 * Plugin Name:       MMe Elementor Kit
 * Plugin URI:        https://mme.vn
 * Description:       Bộ công cụ Elementor của MMe.vn: chia sẻ template từ site Master sang các site Client và widget động (Info Table ACF/SCF).
 * Version:           1.2.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Requires Plugins:  elementor
 * Author:            MMe.vn
 * Author URI:        https://mme.vn
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mme-elementor-kit
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'EMS_ELEMENTS_SHARE_VERSION', '1.2.0' );

/**
 * Define plugin path and plugin url.
 */
define( 'EMS_ELEMENTS_SHARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'EMS_ELEMENTS_SHARE_URL', plugin_dir_url( __FILE__ ) );
define( 'EMS_ELEMENTS_SHARE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-elements-share-activator.php
 */
function ems_elements_share_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-elements-share-activator.php';
	Ems_Elements_Share_Activator::activate();
	add_option('Ems_Elements_Share_Version', EMS_ELEMENTS_SHARE_VERSION);
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-elements-share-deactivator.php
 */
function ems_elements_share_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-elements-share-deactivator.php';
	Ems_Elements_Share_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'ems_elements_share_activate' );
register_deactivation_hook( __FILE__, 'ems_elements_share_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-elements-share.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function ems_elements_share_run() {

	$plugin = new Ems_Elements_Share();
	$plugin->run();

}
ems_elements_share_run();