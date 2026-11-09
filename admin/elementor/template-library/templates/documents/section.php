<?php

namespace Ems_Elementor\Templates\Documents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

class Ems_Elementor_Sections_Document extends Ems_Elementor_Document_Base {
	
	/**
	 * Get Elementor Section name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_name() {
		return 'ems_elementor_sections_page';
	}
	
	/**
	 * Get Elementor Section title.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_title() {
		return __( 'Template', 'mme-elementor-kit' );
	}
	
}