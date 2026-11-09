<?php

namespace Ems_Elementor\Templates\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'Ems_Elementor_Structure_Page' ) ) {
	
	/**
	 * Define Ems_Elementor_Structure_Page class
	 */
	class Ems_Elementor_Structure_Page extends Ems_Elementor_Structure_Base {
		
		/**
		 * Get Section id.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return string
		 */
		public function get_id() {
			return 'ems_elementor_pages';
		}
		
		/**
		 * Get Section Label.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return string
		 */
		public function get_single_label() {
			return __( 'Page', 'mme-elementor-kit' );
		}
		
		/**
		 * Get Section Plural Label.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return string
		 */
		public function get_plural_label() {
			return __( 'Pages', 'mme-elementor-kit' );
		}
		
		/**
		 * Get Sources.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return array
		 */
		public function get_sources() {
			return array( 'ems-elementor-pages-api' );
		}
		
		/**
		 * Get Document Types.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return array
		 */
		public function get_document_type() {
			return array(
				'class' => 'Ems_Elementor_Pages_Document',
				'file'  => require EMS_ELEMENTOR__DIR__ . '/templates/documents/page.php',
			);
		}
		
		/**
		 * Library settings for current structure.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return array
		 */
		public function library_settings() {
			
			return array(
				'show_title' => false,
			);
			
		}
		
	}
	
}
