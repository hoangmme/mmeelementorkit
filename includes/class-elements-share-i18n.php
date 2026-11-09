<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://mme.vn
 * @since      1.0.0
 *
 * @package    Ems_Elements_Share
 * @subpackage Ems_Elements_Share/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Ems_Elements_Share
 * @subpackage Ems_Elements_Share/includes
 * @author     MMe.vn <https://mme.vn>
 */
class Ems_Elements_Share_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'mme-elementor-kit',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
