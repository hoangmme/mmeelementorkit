<?php

namespace Ems_Elementor\Templates\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No access of directly access.

if ( ! class_exists( 'Ems_Elementor_Templates_Types' ) ) {
	
	/**
	 * Elementor Sections Templates Types.
	 *
	 * Templates types responsible for handling templates library tabs
	 *
	 * @since 1.0.0
	 */
	class Ems_Elementor_Templates_Types {
		
		/**
		 * Templates Types.
		 */
		private $types = null;
		
		/**
		 * Ems_Elementor_Templates_Types constructor.
		 *
		 * Get available types for the templates.
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function __construct() {
			
			$this->register_types();
			
		}
		
		/**
		 * Register default templates types.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function register_types() {
			
			$base_path = EMS_ELEMENTOR__DIR__ . '/templates/types/';
			
			require $base_path . 'base.php';
			
			$temp_types = array(
				__NAMESPACE__ . '\Ems_Elementor_Structure_Section' => $base_path . 'section.php',
			);
			
			array_walk( $temp_types, function ( $file, $class ) {
				require $file;
				$this->register_type( $class );
			} );
			
			do_action( 'ems-elementor-templates/types/register', $this );
			
		}
		
		/**
		 * Register templates type.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @param string $class
		 *
		 * @return void
		 */
		public function register_type( $class ) {
			
			$instance = new $class;
			
			$this->types[ $instance->get_id() ] = $instance;
			
			if ( true === $instance->is_location() ) {
				
				register_structure()->locations->register_location( $instance->location_name(), $instance );
				
			}
			
		}
		
		/**
		 * Returns all templates types data.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return array
		 */
		public function get_types() {
			
			return $this->types;
			
		}
		
		/**
		 * Returns all templates types data.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @param int $id
		 *
		 * @return object|bool
		 */
		public function get_type( $id ) {
			
			return isset( $this->types[ $id ] ) ? $this->types[ $id ] : false;
			
		}
		
		/**
		 * Return types prepared for templates library tabs
		 *
		 * @since  1.0.0
		 * @access public
		 * @return array
		 */
		public function get_types_for_popup() {
			
			$result = array();
			
			foreach ( $this->types as $id => $structure ) {
				$result[ $id ] = array(
					'title'    => $structure->get_plural_label(),
					'data'     => array(),
					'sources'  => $structure->get_sources(),
					'settings' => $structure->library_settings(),
				);
			}
			
			return $result;
			
		}
		
	}
	
}
