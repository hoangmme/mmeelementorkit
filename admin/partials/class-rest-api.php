<?php

namespace Ems_Elements_Share\Admin;

use Ems_Elements_Share\Admin\Ems_Key_Manager;

/**
 * Class responsible for Handling REST API.
 */
class Ems_Rest_Api {

    public function __construct() {
        // Hooks
        // Create Rest Api endpoints.
		add_action( 'rest_api_init', array($this, 'create_endpoints') );

		// Modify http request and inject secret key in it
		add_filter( 'http_request_args', array( $this, 'modify_request' ), 10, 2 );
    }


	public function modify_request( $args, $url ) {
		// If the rest url is our rest url except the template-json endpoint
		// Because template-json is sent from own website.
		if ( strpos($url, 'mme-kit/v1') !== false && strpos($url, 'template-json') == false ) {
			$server_key = get_option( 'ems_elements_share_server_key' );
            $args['body'] = array('server_key' => $server_key);
            return $args;
        }
        return $args;
	}


    public function create_endpoints() {
        // Templates
		register_rest_route( 'mme-kit/v1', 'templates/ems_elementor_sections', array(
			'methods'  => 'GET',
			'callback' => array($this, 'provide_templates'),
			'permission_callback' => '__return_true',
		));

		// Template categories
		register_rest_route( 'mme-kit/v1', 'categories/ems_elementor_sections', array(
			'methods'  => 'GET',
			'callback' => array($this, 'provide_categories'),
			'permission_callback' => '__return_true',
		));

        // Single template info
		register_rest_route( 'mme-kit/v1', 'template/(?P<id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array($this, 'provide_single_template_info'),
			'permission_callback' => '__return_true',
		));

        // Single template info
		register_rest_route( 'mme-kit/v1', 'template-json/(?P<template_id>\d+)', array(
			'methods'  => 'GET',
			'callback' => array($this, 'provide_template_json'),
			'permission_callback' => '__return_true',
		));

        // Connect
		register_rest_route( 'mme-kit/v1', 'connect-server/(?P<key>\w+)', array(
			'methods'  => 'GET',
			'callback' => array($this, 'connect_server_using_key'),
			'permission_callback' => '__return_true',
		));

        // Disonnect
		register_rest_route( 'mme-kit/v1', 'disconnect-server/(?P<key>\w+)', array(
			'methods'  => 'GET',
			'callback' => array($this, 'disconnect_server_using_key'),
			'permission_callback' => '__return_true',
		));
    }

	/**
	 * Provide connection response when key is saved in client side.
	 *
	 * @param object $request
	 * @return array
	 */
	public function connect_server_using_key( $request ) {
		$key = $request['key'];
		$key_manager = Ems_Key_Manager::get_instance();
		if ( !$key_manager->is_key_valid( $key ) ) {
			return array(
				'success' => false,
				'message' => __('Invalid secret key!', 'mme-elementor-kit')
			);
		}

		// Check for max site count
		if ( !$key_manager->has_site_limit_to_connect( $key ) ) {
			return array(
				'success' => false,
				'message' => __('Maximum site use reached for this key! Please deactivate this key from any site.', 'mme-elementor-kit')
			);
		}

		// Connect and increment site count.
		$key_manager->increment_site_count( $key );

		return array(
			'success' => true
		);
	}

	/**
	 * Provide disconnection response when key is deleted from client side.
	 *
	 * @param object $request
	 * @return array
	 */
	public function disconnect_server_using_key( $request ) {
		$key = $request['key'];
		$key_manager = Ems_Key_Manager::get_instance();

		// If key exists, disconnect and decrement site count.
		if ( $key_manager->is_key_valid( $key ) ) {
			// Connect and increment site count.
			$key_manager->decrement_site_count( $key );
		}

		return array(
			'success' => true
		);
	}

	/**
	 * Provides templates.
	 *
	 * @param object $request
	 * @return array
	 */
    public function provide_templates( $request ) {

		if ( !$request->get_param('server_key') ) {
			status_header( 401, __( 'Empty Secret key', 'mme-elementor-kit' ) );
			exit;
		}

		// Validate secret key
		$key = $request->get_param( 'server_key' );
		$key_manager = Ems_Key_Manager::get_instance();
		if ( !$key_manager->is_key_valid( $key ) ) {
			status_header( 429, __( 'Secret key not valid', 'mme-elementor-kit' ) );
			exit;
		}

		if ( !$key_manager->has_site_limit( $key ) ) {
			status_header( 402, __( 'Site limit reached', 'mme-elementor-kit' ) );
			exit;
		}

		$rule               = $key_manager->get_rule_from_key( $key );
		$template_select_by = $rule['template_select_by'];
		$selected_cats      = $rule['categories'];
		$selected_templates = $rule['templates'];
        
		if ( $template_select_by === 'categories' ) {
			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'elementor_library_category',
						'field'    => 'term_id',
						'terms'    => $selected_cats,
						'operator' => 'IN',
					),
				),
			);
		}
		else if ( $template_select_by === 'templates' ) {
			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				// An empty post__in is ignored by WP_Query and would expose every template.
				'post__in'       => ! empty( $selected_templates ) ? $selected_templates : array( 0 ),
			);
		}
		else {
			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			);
		}
        

		// Get posts
		$query = new \WP_Query( $args );
		$posts = $query->get_posts();

		$templates_array = array();
		if( is_array($posts) && count($posts) > 0 ) {
			foreach ($posts as $post) {
				// Find image url
				$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
				$image_url = $image[0];
				if ( !strlen( $image_url ) ) {
					$image_url = EMS_ELEMENTS_SHARE_URL . 'admin/img/placeholder.png';
				}

				// Construct categories array
				$categories = array();
				$post_categories = wp_get_post_terms( $post->ID, 'elementor_library_category' );
				foreach($post_categories as $c){
					$cat = get_category( $c );
					$categories[] = $cat->slug;
				}

				$single_post = array(
					"template_id"  => $post->ID,
					"title"        => $post->post_title,
					"source"       => "ems-elementor-sections-api",
					"type"         => "ems_elementor_sections",
					"preview"      => $image_url,
					"thumbnail"    => $image_url,
					"categories"   => $categories,
					"is_pro"       => "",
					"is_woo"       => "",
					"is_lifter"    => "",
					"is_learndash" => "",
					"is_tec"       => ""
				);
				$templates_array[] = $single_post;
			}
		}

		$big_array = array(
			"success" => true,
			"templates" => $templates_array
		);
		return $big_array;
    }

	/**
	 * Provide template categories.
	 *
	 * @param object $request
	 * @return array
	 */
    public function provide_categories( $request ) {
		if ( !$request->get_param('server_key') ) {
			status_header( 401, __( 'Empty Secret key', 'mme-elementor-kit' ) );
			exit;
		}

		// Validate secret key
		$key = $request->get_param( 'server_key' );
		$key_manager = Ems_Key_Manager::get_instance();
		if ( !$key_manager->is_key_valid( $key ) ) {
			status_header( 429, __( 'Secret key not valid', 'mme-elementor-kit' ) );
			exit;
		}

		if ( !$key_manager->has_site_limit( $key ) ) {
			status_header( 402, __( 'Site limit reached', 'mme-elementor-kit' ) );
			exit;
		}

		$rule               = $key_manager->get_rule_from_key( $key );
		$template_select_by = $rule['template_select_by'];
		$selected_cats      = $rule['categories'];
		$selected_templates = $rule['templates'];


		$terms = null;

		if ( $template_select_by === 'categories' ) {
			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'elementor_library_category',
						'field'    => 'term_id',
						'terms'    => $selected_cats,
						'operator' => 'IN',
					),
				),
			);
		}
		else if ( $template_select_by === 'templates' ) {
			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				// An empty post__in is ignored by WP_Query and would expose every template.
				'post__in'       => ! empty( $selected_templates ) ? $selected_templates : array( 0 ),
			);
		}
		else {
			$args = array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			);

			// Get terms in regular way because all templates are selected.
			$terms = get_terms([
				'taxonomy' => 'elementor_library_category',
				'hide_empty' => false,
			]);
		}


		$terms_array = array();
		// If terms already exists, that means all templates are selected,
		// So no need to get terms for each post again.
		if ( $terms !== null ) {
			if( is_array($terms) && count($terms) > 0 ) {
				foreach ($terms as $term) {
					$slug = $term->slug;
					$title = $term->name;
					$terms_array[$slug] = $title;
				}
			}
		}
		else {
			// Get posts
			$query = new \WP_Query( $args );
			$posts = $query->get_posts();
			
			if( is_array($posts) && count($posts) > 0 ) {
				foreach ($posts as $post) {
					$terms = get_the_terms($post->ID, 'elementor_library_category');
					if( is_array($terms) && count($terms) > 0 ) {
						foreach ($terms as $term) {
							$slug = $term->slug;
							$title = $term->name;
							$terms_array[$slug] = $title;
						}
					}
				}
			}
		}
		

		$big_array = array(
			"success" => true,
			"terms" => $terms_array
		);
		return $big_array;
    }

	/**
	 * Provides a single template info.
	 *
	 * @param object $request
	 * @return array
	 */
    public function provide_single_template_info( $request ) {

		if ( !$request->get_param('server_key') ) {
			status_header( 401, __( 'Empty Secret key', 'mme-elementor-kit' ) );
			exit;
		}

		// Validate secret key
		$key = $request->get_param( 'server_key' );
		$key_manager = Ems_Key_Manager::get_instance();
		if ( !$key_manager->is_key_valid( $key ) ) {
			status_header( 429, __( 'Secret key not valid', 'mme-elementor-kit' ) );
			exit;
		}

		if ( !$key_manager->has_site_limit( $key ) ) {
			status_header( 402, __( 'Site limit reached', 'mme-elementor-kit' ) );
			exit;
		}
        
        $template_id   = $request['id'];
        $json_file_url = add_query_arg( 'server_key', $key, get_rest_url( null, 'mme-kit/v1/template-json/' . $template_id ) );
        $request       = wp_remote_get($json_file_url);
        $response      = wp_remote_retrieve_body( $request );
        $response      = json_decode($response);

        $big_array = array(
            "success" => true,
        );
        return array_merge($big_array, (array)$response);
    }

    /**
     * Directly export a template into json.
     *
     * @param object $request
     * @return array
     */
    public function provide_template_json( $request ) {
        if ( !isset( $request['template_id'] ) ) {
            return;
        }

		if ( !$request->get_param('server_key') ) {
			status_header( 401, __( 'Empty Secret key', 'mme-elementor-kit' ) );
			exit;
		}

		// Validate secret key
		$key = $request->get_param( 'server_key' );
		$key_manager = Ems_Key_Manager::get_instance();
		if ( !$key_manager->is_key_valid( $key ) ) {
			status_header( 429, __( 'Secret key not valid', 'mme-elementor-kit' ) );
			exit;
		}

        if ( !class_exists('\Elementor\TemplateLibrary\Source_Local') ) {
            require_once ELEMENTOR_PATH . 'includes/template-library/sources/base.php';
            require_once ELEMENTOR_PATH . 'includes/template-library/sources/local.php';
        }

        $local_template_manager = new \Elementor\TemplateLibrary\Source_Local();
        $local_template_manager->export_template($request['template_id']);
    }
}


?>