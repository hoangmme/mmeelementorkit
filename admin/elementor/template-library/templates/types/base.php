<?php

namespace Ems_Elementor\Templates\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No access of directly access.

if ( ! class_exists( 'Ems_Elementor_Structure_Base' ) ) {
	
	/**
	 * Define Ems_Elementor_Structure_Base class
	 */
	abstract class Ems_Elementor_Structure_Base {
		
		/**
		 * get io.
		 *
		 * @abstract
		 * @since  1.0.0
		 * @access public
		 */
		abstract public function get_id();
		
		/**
		 * get single label.
		 *
		 * @abstract
		 * @since  1.0.0
		 * @access public
		 */
		abstract public function get_single_label();
		
		/**
		 * get plural label.
		 *
		 * @abstract
		 * @since  1.0.0
		 * @access public
		 */
		abstract public function get_plural_label();
		
		/**
		 * get Sources.
		 *
		 * @abstract
		 * @since  1.0.0
		 * @access public
		 */
		abstract public function get_sources();
		
		/**
		 * get Document.
		 *
		 * @abstract
		 * @since  1.0.0
		 * @access public
		 */
		abstract public function get_document_type();
		
		/**
		 * Is current structure could be outputed as location
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return boolean
		 */
		public function is_location() {
			return false;
		}
		
		/**
		 * Location name
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return boolean
		 */
		public function location_name() {
			return '';
		}
		
		/**
		 * Library settings for current structure
		 * @since 1.0.0
		 * @return array
		 */
		public function library_settings() {
			
			return array(
				'show_title' => true,
			);
			
		}
		
	}
	
}
