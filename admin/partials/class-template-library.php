<?php 
namespace Ems_Elements_Share\Admin;

/**
 * Class responsible for adding Elementor Template Library.
 */
class Ems_Template_Library {

    public function __construct() {
        
        // Hooks
        // Include template library
		add_action( 'plugins_loaded', array($this, 'include_library') );
    }

    /**
     * Include the library files.
     *
     * @return void
     */
    public function include_library() {
        if( did_action( 'elementor/loaded' ) ) {
			require_once( EMS_ELEMENTS_SHARE_PATH . 'admin/elementor/template-library/elementor-helper.php');
			$this->_elementor_helper = new \Ems_TemplateLibrary\Ems_ElementorHelper();
			require_once( EMS_ELEMENTS_SHARE_PATH . 'admin/elementor/template-library/elementor.php' );
		}
    }
}


?>