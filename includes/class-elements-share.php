<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://mme.vn
 * @since      1.0.0
 *
 * @package    Ems_Elements_Share
 * @subpackage Ems_Elements_Share/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Ems_Elements_Share
 * @subpackage Ems_Elements_Share/includes
 * @author     MMe.vn <https://mme.vn>
 */
class Ems_Elements_Share {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Ems_Elements_Share_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'EMS_ELEMENTS_SHARE_VERSION' ) ) {
			$this->version = EMS_ELEMENTS_SHARE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'mme-elementor-kit';

		$this->load_dependencies();
		$this->define_mcp_hooks();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_widget_hooks();
		$this->define_area_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Ems_Elements_Share_Loader. Orchestrates the hooks of the plugin.
	 * - Ems_Elements_Share_i18n. Defines internationalization functionality.
	 * - Ems_Elements_Share_Admin. Defines all hooks for the admin area.
	 * - Ems_Elements_Share_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-elements-share-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-elements-share-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-elements-share-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-elements-share-public.php';

		/**
		 * The class responsible for registering the custom Elementor widgets.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'widgets/class-widgets-manager.php';

		/**
		 * The class responsible for extra Elementor areas per post, and its template functions.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'areas/class-areas-manager.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'areas/functions.php';

		/**
		 * Error log (no wp-config.php needed) and the MCP abilities (Abilities API, WordPress 6.9+).
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/mcp/class-debug-log.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/mcp/class-mcp-abilities.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ai/class-ai-client.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ai/class-ai-layout.php';

		$this->loader = new Ems_Elements_Share_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Ems_Elements_Share_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Ems_Elements_Share_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Ems_Elements_Share_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Check if plugin was updated
		$this->loader->add_action('plugins_loaded', $plugin_admin, 'if_plugin_updated');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Ems_Elements_Share_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Register the custom Elementor widgets (Info Table, ...).
	 *
	 * @since    1.1.0
	 * @access   private
	 */
	private function define_widget_hooks() {

		new \Ems_Elements_Share\Widgets\Ems_Widgets_Manager();

	}

	/**
	 * Start the error log and register the MCP abilities.
	 *
	 * @since    1.6.0
	 * @access   private
	 */
	private function define_mcp_hooks() {

		// First, so errors raised while the rest of the plugin loads are recorded too.
		\Ems_Elements_Share\Mcp\Ems_Debug_Log::get_instance();
		\Ems_Elements_Share\Mcp\Ems_Mcp_Abilities::get_instance();
		\Ems_Elements_Share\Ai\Ems_Ai_Layout::get_instance();

	}

	/**
	 * Register the extra Elementor areas per post (Bottom, ...).
	 *
	 * @since    1.4.0
	 * @access   private
	 */
	private function define_area_hooks() {

		\Ems_Elements_Share\Areas\Ems_Areas_Manager::get_instance();

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Ems_Elements_Share_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
