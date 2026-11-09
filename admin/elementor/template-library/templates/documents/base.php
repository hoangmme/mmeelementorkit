<?php

namespace Ems_Elementor\Templates\Documents;

use Elementor\Core\Base\Document as Document;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

class Ems_Elementor_Document_Base extends Document {
	
	/**
	 * Get name.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_name() {
		return '';
	}
	
	/**
	 * Get title.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_title() {
		return '';
	}
	
}
