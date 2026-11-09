<?php

namespace Ems_Elementor\Templates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If class `Ems_Elementor_Templates` not created.
if ( ! class_exists( 'Ems_Elementor_Templates' ) ) {
	
	/**
	 * Sets up and initializes the plugin.
	 */
	class Ems_Elementor_Templates {
		
		/**
		 * Instance of the class
		 *
		 * @access private
		 * @since  1.0.0
		 *
		 */
		private static $instance = null;
		
		/**
		 * Holds API data
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 */
		public $api;
		
		/**
		 * Holds templates configuration data
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public $config;
		
		/**
		 * Holds templates assets
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public $assets;
		
		/**
		 * Templates Manager
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public $temp_manager;
		
		/**
		 * Holds templates types
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public $types;
		
		/**
		 * Construct
		 *
		 * Class Constructor
		 *
		 * @since  1.0.0
		 * @access public
		 */
		public function __construct() {
			
			add_action( 'init', array( $this, 'init' ) );
			
		}
		
		/**
		 * Init EMS Elementor Sections Templates
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function init() {
			
			$this->load_files();
			
			$this->set_config();
			
			$this->set_assets();
			
			$this->set_api();
			
			$this->set_types();
			
			$this->set_templates_manager();
			
		}
		
		/**
		 * Load required files for EMS Elementor Sections templates
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function load_files() {
			require EMS_ELEMENTOR__DIR__ . '/templates/classes/config.php';
			
			require EMS_ELEMENTOR__DIR__ . '/templates/classes/assets.php';
			
			require EMS_ELEMENTOR__DIR__ . '/templates/classes/manager.php';
			
			require EMS_ELEMENTOR__DIR__ . '/templates/types/manager.php';
			
			require EMS_ELEMENTOR__DIR__ . '/templates/classes/api.php';
			
		}
		
		/**
		 * Init `Ems_Elementor_Templates_Core_Config`
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function set_config() {
			
			$this->config = new Classes\Ems_Elementor_Templates_Core_Config();
			
		}
		
		/**
		 * Init `Ems_Elementor_Templates_Assets`
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function set_assets() {
			
			$this->assets = new Classes\Ems_Elementor_Templates_Assets();
			
		}
		
		/**
		 * Init `Ems_Elementor_Templates_API`
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function set_api() {
			
			$this->api = new Classes\Ems_Elementor_Templates_API();
			
		}
		
		/**
		 * Init `Ems_Elementor_Templates_Types`
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function set_types() {
			
			$this->types = new Types\Ems_Elementor_Templates_Types();
			
		}
		
		/**
		 * Init `Ems_Elementor_Templates_Manager`
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function set_templates_manager() {
			
			$this->temp_manager = new Classes\Ems_Elementor_Templates_Manager();
			
		}
		
		/**
		 * Get instance.
		 *
		 * Creates and returns an instance of the class.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return object
		 */
		public static function get_instance() {
			if ( self::$instance == null ) {
				self::$instance = new self;
			}
			
			return self::$instance;
		}
		
	}
	
}

if ( ! function_exists( 'ems_elementor_templates' ) ) {
	
	/**
	 * Triggers `get_instance` method
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	function ems_elementor_templates() {
		
		return Ems_Elementor_Templates::get_instance();
		
	}
	
}
ems_elementor_templates();